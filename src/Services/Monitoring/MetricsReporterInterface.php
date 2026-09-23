<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\Monitoring;

/**
 * Project-owned abstraction over the metrics vendor (currently Sentry Application Metrics).
 *
 * Call sites depend on this interface only — no `\Sentry\*` type may appear at a
 * call site. Swapping the vendor means providing a new implementation and
 * rebinding it via the `common-resources.metrics_reporter` config key.
 *
 * Attribute values must stay low-cardinality (class names, queue names, statuses).
 * Never pass ids, uuids, message payloads, e-mails or exception messages.
 */
interface MetricsReporterInterface
{
    /**
     * Unit vocabulary of this abstraction. Implementations map these to the vendor's units.
     */
    public const UNIT_MILLISECOND = 'millisecond';
    public const UNIT_SECOND = 'second';
    public const UNIT_BYTE = 'byte';
    public const UNIT_NONE = 'none';

    /**
     * Increment a counter ("how many times something happened").
     *
     * @param array<string, int|float|string|bool|null> $attributes
     */
    public function count(string $name, int|float $value = 1, array $attributes = []): void;

    /**
     * Record a value for statistical aggregation (p50/p95/p99, avg, min, max).
     *
     * @param array<string, int|float|string|bool|null> $attributes
     */
    public function distribution(
        string $name,
        int|float $value,
        array $attributes = [],
        string $unit = self::UNIT_NONE,
    ): void;

    /**
     * Record a point-in-time value (queue depth, heartbeat).
     *
     * @param array<string, int|float|string|bool|null> $attributes
     */
    public function gauge(
        string $name,
        int|float $value,
        array $attributes = [],
        string $unit = self::UNIT_NONE,
    ): void;

    /**
     * Send buffered metrics to the vendor now. Long-lived processes must call this
     * periodically and on shutdown; short-lived ones may rely on the vendor's own flush.
     */
    public function flush(): void;
}
