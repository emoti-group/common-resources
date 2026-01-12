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
use Throwable;

abstract class PostHogAbstractService
{
    protected ?string $distinctId = null;
    /** @var list<PostHogBreadcrumb> $breadcrumbs */
    protected array $breadcrumbs = [];
    protected bool $initialized = false;
    protected string $project;
    protected string $environment;
    protected ?string $ip;
    protected ?string $origin = null;
    protected ?string $referer = null;
    protected array $headers;
    protected Site $site;
    protected bool $isTrusted = false;
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
        if (!$enabled || empty($host) || empty($apiKey) || $this->isInitialized()) return;
        
        $this->isTrusted = $isTrustedVisitor;
        $this->headers = $headers;
        $this->environment = $environment;
        $this->project = $project;
        $this->site = $site;
        $this->debug = $debug;
        $this->ip = $ip;

        PostHog::init(
            $apiKey,
            [
                'host' => $host,
                'debug' => $debug,
            ],
        );

        $origin = $headers['origin'] ?? '';
        if (is_array($origin)) {
            $this->origin = $origin[0] ?? '';
        }
        $referer = $headers['referer'] ?? '';
        if (is_array($referer)) {
            $this->referer = $referer[0] ?? '';
        }

        $this->setDistinctId($ownDistinctId);
        $this->initialized = true;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setIdentification(): void
    {
        if (!$this->initialized) {
            return;
        }

        $cacheAvailable = function_exists('cache');

        $distinctId = $this->getDistinctId();
        $properties = $this->getIdentificationProperties();

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

    public function getDistinctId(): string
    {
        return $this->distinctId;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setDistinctId(?string $ownId = null): string
    {
        if (empty($this->distinctId) && empty($ownId)) {
            $this->distinctId = ($this->environment . '_' . $this->site->value . '_IP_' . $this->uuidFromIp($this->ip ?? 'unknown'));

            $this->setIdentification();
        } elseif (!empty($ownId)) {
            $this->distinctId = $ownId;
        }

        return $this->distinctId;
    }

    public function resetDistinctId(): void
    {
        $this->distinctId = null;

        $this->updatedDistinctId();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function updateDistinctId(?string $newDistinctId): void
    {
        if (!$this->isInitialized()) {
            return;
        }

        $oldDistinctId = $this->distinctId;
        if (!is_null($newDistinctId)) {
            $this->setDistinctId($this->site->value . '_' . $newDistinctId);
        } else {
            $this->setDistinctId();
        }

        if (!is_null($newDistinctId)) {
            $this->alias($oldDistinctId);
        }

        $this->updatedDistinctId();
    }

    public function addBreadcrumb(PostHogBreadcrumb $breadcrumb): void
    {
        $this->breadcrumbs[] = $breadcrumb;
    }

    public function capture(PostHogEvent $event, ?array $properties = null): bool
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

    /**
     * @throws PostHogNotInitializedException
     */
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

    /**
     * @throws PostHogNotInitializedException
     * @throws Exception
     */
    public function getFeatureFlag(FeatureFlag $featureFlag): bool
    {
        $variant = $this->getFeatureVariant($featureFlag);

        return $variant !== 'control';
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

    protected function extendIdentificationProperties(): array
    {
        return [];
    }

    protected function updatedDistinctId(): void
    {
        // Implement in child classes if needed
    }

    protected function getIdentificationProperties(): array
    {
        $properties = [
            'environment' => $this->environment,
            'site' => $this->site->value,
            'header_features_enable' => array_filter(explode(',', $this->headers['X-Features-Enable'] ?? '')),
            'header_features_disable' => array_filter(explode(',', $this->headers['X-Features-Enable'] ?? '')),
            'platform' => $this->project,
        ];

        return array_merge($properties, $this->extendIdentificationProperties());
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
            'route_controller' => request()->route()?->getControllerClass() ?? 'null',
            'route_action' => request()->route()?->getActionMethod() ?? 'null',
            'route_params' => request()->route()?->parameters() ?? [],
            'origin' => $this->origin,
            'referer' => $this->referer,
            'trusted_visitor' => $this->isTrusted,
        ];

        return PropertiesData::fromArrays($properties, $basicData);
    }

    protected function getBreadcrumbs(): array
    {
        return array_map(
            fn(PostHogBreadcrumb $b) => $b->toArray(),
            $this->breadcrumbs,
        );
    }

    /**
     * @throws Exception
     */
    private function alias(string $oldDistinctId): void
    {
        PostHog::alias([
            'distinctId' => $this->getDistinctId(),
            'alias' => $oldDistinctId,
        ]);
    }

    private function prepareCaptureData(PostHogEvent $event, ?array $properties): CaptureData
    {
        return new CaptureData(...[
            'distinctId' => $this->getDistinctId(),
            'event' => $event,
            'properties' => $this->preparePropertiesData($properties),
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
