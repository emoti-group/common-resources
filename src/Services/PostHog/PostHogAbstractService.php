<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\PostHog;

use Emoti\CommonResources\DTO\PostHog\CaptureData;
use Emoti\CommonResources\DTO\PostHog\PostHogBreadcrumb;
use Emoti\CommonResources\DTO\PostHog\PropertiesData;
use Emoti\CommonResources\Enums\FeatureFlag;
use Emoti\CommonResources\Enums\PostHogEvent;
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Exceptions\PostHog\PostHogNotInitializedException;
use Exception;
use Illuminate\Support\Str;
use Log;
use PostHog\PostHog;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Ramsey\Uuid\Guid\Guid;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

abstract class PostHogAbstractService
{
    protected string $distinctId;
    /** @var list<PostHogBreadcrumb> $breadcrumbs */
    protected array $breadcrumbs = [];
    protected bool $initialized = false;
    protected string $project;
    protected string $environment;
    protected bool $debug = false;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function init(
        string $project,
        string $environment,
        Site $site,
        ?string $host,
        ?string $apiKey,
        ?string $ip,
        bool $isTrustedVisitor = false,
        array $headers = [],
        bool $enabled = true,
        ?string $ownDistinctId = null,
        bool $debug = false
    ): void
    {
        if (!$enabled || empty($host) || empty($apiKey)) return;

        $this->environment = $environment;
        $this->project = $project;
        $this->debug = $debug;

        PostHog::init(
            $apiKey,
            [
                'host' => $host,
                'debug' => $debug,
            ],
        );

        $origin = $headers['origin'] ?? '';
        if (is_array($origin)) {
            $origin = $origin[0] ?? '';
        }
        $referer = $headers['referer'] ?? '';
        if (is_array($referer)) {
            $referer = $referer[0] ?? '';
        }

        $this->setDistinctId($ip, $ownDistinctId);
        $this->initialized = true;
        $this->setIdentification($environment, $site, $isTrustedVisitor, $headers, $origin, $referer);
    }

    public function setDistinctId(?string $ip, ?string $ownId = null): string
    {
        if (!isset($this->distinctId) && empty($ownId)) {
            $this->distinctId = ('IP_' . $this->uuidFromIp($ip ?? 'unknown'));
        } elseif (!empty($ownId)) {
            $this->distinctId = $ownId;
        }

        return $this->distinctId;
    }

    protected function getDistinctIdFromCookie(array $cookies): ?string
    {
        $posthugCookieKey = array_values(
            array_filter(
                array_keys($cookies),
                fn($key) => str_starts_with($key, 'ph_phc_') && str_ends_with($key, '_posthog'),
            ),
        )[0] ?? null;

        if (empty($posthugCookieKey) || empty($cookies[$posthugCookieKey])) {
            return null;
        }

        return json_decode($cookies[$posthugCookieKey])->distinct_id ?? null;
    }

    public function getDistinctId(): string
    {
        return $this->distinctId;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function capture(
        PostHogEvent $event,
        ?array $properties = null
    ): bool
    {
        try {
            if (!$this->initialized) {
                return false;
            }

            $captureData = $this->prepareCaptureData($event, $properties);

            return PostHog::capture($captureData->toArray());
        } catch (Throwable $e) {
            if ($this->debug) {
                throw $e;
            }
            
            return false;
        }
    }

    /**
     * @throws PostHogNotInitializedException
     * @throws Exception
     */
    public function getFeatureFlag(FeatureFlag $featureFlag): bool
    {
        $variant = $this->getFeatureVariant($featureFlag);

        return $variant !== 'control';
    }

    public function getFeatureVariant(FeatureFlag $featureFlag): ?string
    {
        if (!$this->initialized) {
            throw new PostHogNotInitializedException('Cannot get feature flag; PostHog not initialized.');
        }

        $callback = function () use ($featureFlag) {
            $variant = PostHog::getFeatureFlag(
                $featureFlag->value,
                $this->getDistinctId(),
            );

            if (is_bool($variant)) {
                return $variant ? 'feature' : 'control';
            }

            return $variant;
        };

        if (function_exists('cache')) {
            $variant = cache()->remember(
                'posthog_feature_flag|' . $this->getDistinctId() . '|' . $featureFlag->value,
                60,
                $callback,
            );
        } else {
            $variant = $callback();
        }

        (new PostHogBreadcrumb(
            level: 'info',
            type: 'default',
            category: 'feature_flag',
            message: 'Feature flag ' . $featureFlag->value . ' checked',
            metadata: [
                'feature_flag' => $featureFlag->value,
                'result' => $variant,
            ]
        ))->push($this);

        return $variant;
    }

    private function prepareCaptureData(PostHogEvent $event, ?array $properties): CaptureData
    {
        return new CaptureData(...[
            'distinctId' => $this->getDistinctId(),
            'event' => $event,
            'properties' => $this->preparePropertiesData($properties),
        ]);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function setIdentification(
        string $environment,
        Site $site,
        bool $isTrusted,
        array $headers,
        string $origin,
        string $referer
    ): void
    {
        if (!$this->initialized) {
            return;
        }

        $cacheAvailable = function_exists('cache');

        $distinctId = $this->getDistinctId();
        $properties = [
            'environment' => $environment,
            'site' => $site->value,
            'header_features_enable' => array_filter(explode(',', $headers['X-Features-Enable'] ?? '')),
            'header_features_disable' => array_filter(explode(',', $headers['X-Features-Enable'] ?? '')),
            'trusted_visitor' => $isTrusted,
            'referer' => $referer,
            'origin' => $origin,
            'platform' => $this->project,
        ];

        $cacheKey = 'posthog_identificator|' . $distinctId;
        $propertiesSignature = !$cacheAvailable ?: md5(json_encode($properties));

        if (
            !$cacheAvailable
            || cache()->get($cacheKey) !== $propertiesSignature
        ) {
            PostHog::identify([
                'distinctId' => $distinctId,
                'properties' => $properties,
            ]);

            if ($cacheAvailable) {
                cache()->put($cacheKey, $propertiesSignature, 86400);
            }
        }
    }

    protected function preparePropertiesData(?array $properties): PropertiesData
    {
        $properties ??= [];

        $basicData = [
            'environment' => $this->environment,
            'platform' => $this->project,
            'breadcrumbs' => $this->getBreadcrumbs(),
            '$current_url' => request()->fullUrl(),
            'search_params' => request()->query(),
            'post_body' => request()->post(),
            'route_controller' => request()->route()?->getControllerClass() ?? 'null',
            'route_action' => request()->route()?->getActionMethod() ?? 'null',
            'route_params' => request()->route()?->parameters() ?? [],
        ];

        return PropertiesData::fromArrays($properties, $basicData);
    }

    public function captureException(Throwable $e, ?array $hint = null): bool
    {
        return $this->capture(PostHogEvent::EXCEPTION, [
            'hint' => $hint,
            '$exception_fingerprint' => md5(
                get_class($e) . $e->getFile() . $e->getMessage(),
            ),
            '$exception_level' => 'error',
            '$exception_list' => [
                [
                    'type' => get_class($e),
                    'value' => $e->getMessage(),
                    'mechanism' => [
                        'handled' => false,
                        'synthetic' => false,
                    ],
                    'stacktrace' => [
                        'type' => 'raw',
                        'frames' => $this->prepareFrames($e),
                    ],
                ],
            ],
        ]);
    }

    private function prepareFrames(Throwable $e): array
    {
        $frames = [];

        $frames[] = [
            'platform' => 'custom',
            'lang' => 'php',
            'filename' => $e->getFile(),
            'class' => get_class($e),
            'function' => 'throw',
            'lineno' => $e->getLine(),
        ];

        foreach ($e->getTrace() as $t) {
            $frames[] = [
                'platform' => 'custom',
                'lang' => 'php',
                'filename' => $t['file'] ?? 'null',
                'class' => $t['class'] ?? 'null',
                'function' => $t['function'],
                'type' => $t['type'] ?? 'null',
                'lineno' => $t['line'] ?? 'null',
            ];
        }

        return $frames;
    }

    public function addBreadcrumb(PostHogBreadcrumb $breadcrumb): void
    {
        $this->breadcrumbs[] = $breadcrumb;
    }

    protected function getBreadcrumbs(): array
    {
        return array_map(
            fn(PostHogBreadcrumb $b) => $b->toArray(),
            $this->breadcrumbs,
        );
    }

    /**
     * Generate a UUIDv5 from an IP address.
     */
    private function uuidFromIp(string $ip): string
    {
        return Guid::uuid5(
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            $ip . Str::random(),
        )
            ->toString();
    }
}
