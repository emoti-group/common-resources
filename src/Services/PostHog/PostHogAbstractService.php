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
    protected ?string $apiKey;
    protected string $environment;
    protected ?string $ip;
    protected ?string $origin = null;
    protected ?string $referer = null;
    protected array $lastFeaturesFlagVariants = [];
    protected array $currentFeaturesFlagVariants = [];
    protected array $headers;
    protected Site $site;
    protected bool $isTrusted = false;
    protected bool $debug = false;

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
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
        array $lastFeaturesFlagVariants = [],
        bool $enabled = true,
        ?string $ownDistinctId = null,
        bool $debug = false
    ): void
    {
        if (!$enabled || empty($host) || empty($apiKey) || $this->isInitialized()) return;

        $this->apiKey = $apiKey;
        $this->isTrusted = $isTrustedVisitor;
        $this->headers = $headers;
        $this->lastFeaturesFlagVariants = $lastFeaturesFlagVariants;
        $this->environment = $environment;
        $this->project = $project;
        $this->site = $site;
        $this->debug = $debug;
        $this->ip = $ip;

        $origin = $headers['origin'] ?? '';
        if (is_array($origin)) {
            $this->origin = $origin[0] ?? '';
        }
        $referer = $headers['referer'] ?? '';
        if (is_array($referer)) {
            $this->referer = $referer[0] ?? '';
        }

        PostHog::init(
            $apiKey,
            [
                'host' => $host,
                'debug' => $debug,
            ],
        );

        $this->initialized = true;

        $this->setDistinctId($ownDistinctId);
    }

    /**
     * @throws Exception
     */
    public function setIdentification(): void
    {
        if (!$this->isInitialized()) {
            return;
        }

        $distinctId = $this->getDistinctId();
        $properties = $this->getIdentificationProperties();

        PostHog::identify([
            'distinctId' => $distinctId,
            'properties' => $properties,
        ]);
    }

    public function getDistinctId(): string
    {
        return $this->distinctId;
    }

    /**
     * @throws Exception
     */
    public function setDistinctId(?string $ownId = null): ?string
    {
        if (!$this->isInitialized()) {
            return null;
        }

        if (empty($this->distinctId) && empty($ownId)) {
            $this->distinctId = ($this->environment . '_' . $this->site->value . '_IP_' . $this->uuidFromIp($this->ip ?? 'unknown'));

            $this->setIdentification();
            $this->updatedDistinctId();
        } elseif (!empty($ownId)) {
            $this->distinctId = $ownId;

            $this->updatedDistinctId();
        }

        return $this->distinctId;
    }

    /**
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
    }

    public function resetDistinctId(): void
    {
        $this->distinctId = null;

        $this->updatedDistinctId();
    }

    public function addBreadcrumb(PostHogBreadcrumb $breadcrumb): void
    {
        $this->breadcrumbs[] = $breadcrumb;
    }

    /**
     * @throws Throwable
     */
    public function capture(PostHogEvent $event, ?array $properties = null): bool
    {
        if (!$this->isInitialized()) {
            return false;
        }

        try {
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

    public function getAllKnownFeaturesFlagVariants(): array
    {
        return $this->lastFeaturesFlagVariants;
    }

    /**
     * @throws PostHogNotInitializedException
     * @throws Exception
     */
    public function getFeatureVariant(FeatureFlag $featureFlag): ?string
    {
        if (!$this->initialized) {
            return 'control';
        }

        if (array_key_exists($featureFlag->value, $this->currentFeaturesFlagVariants)) {
            return $this->currentFeaturesFlagVariants[$featureFlag->value];
        }

        $currentVariant = null;
        if (array_key_exists($featureFlag->value, $this->lastFeaturesFlagVariants)) {
            $currentVariant = $this->getFeatureVariantFromPostHog($featureFlag, false);
        }

        if (is_null($currentVariant) || $this->lastFeaturesFlagVariants[$featureFlag->value] !== $currentVariant) {
            $currentVariant = $this->getFeatureVariantFromPostHog($featureFlag, true);
        }

        $this->lastFeaturesFlagVariants[$featureFlag->value] = $currentVariant;
        $this->currentFeaturesFlagVariants[$featureFlag->value] = $currentVariant;

        return $currentVariant;
    }

    /**
     * @throws Exception
     */
    private function getFeatureVariantFromPostHog(FeatureFlag $featureFlag, bool $sendFeatureFlagEvents = false): ?string
    {
        $variant = PostHog::getFeatureFlag(
            $featureFlag->value,
            $this->getDistinctId(),
            sendFeatureFlagEvents: $sendFeatureFlagEvents
        );

        if (is_bool($variant)) {
            return $variant ? 'feature' : 'control';
        }

        return $variant;
    }

    protected function phCookieName(?string $apiKey = null): string
    {
        return 'ph_' . ($apiKey ?? $this->apiKey) . '_posthog';
    }

    protected function extendIdentificationProperties(): array
    {
        // Implement in child classes if needed
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
            'platform' => $this->project,
        ];

        return array_merge($properties, $this->extendIdentificationProperties());
    }

    protected function preparePropertiesData(?array $properties): PropertiesData
    {
        // Implement in child classes if needed

        $properties ??= [];
        return PropertiesData::fromArrays($properties);
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
