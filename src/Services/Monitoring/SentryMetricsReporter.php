<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\Monitoring;

use Sentry\Metrics\Types\Metric;
use Sentry\SentrySdk;
use Sentry\Unit;

use function Sentry\traceMetrics;

/**
 * Sentry Application Metrics implementation of {@see MetricsReporterInterface}.
 *
 * Together with {@see SentryErrorReporter} this is the only place in the Laravel
 * projects that references `\Sentry\*`.
 *
 * Buffering/flushing is the SDK's job: set `metric_flush_threshold` in the Sentry
 * options so the buffer auto-flushes instead of silently dropping the oldest of
 * 1000 entries. `flush()` here is for long-lived workers that must not sit on
 * buffered data (time-based flush, shutdown).
 */
final class SentryMetricsReporter implements MetricsReporterInterface
{
    /**
     * Attributes the SDK copies from the current scope onto every metric regardless of
     * `send_default_pii`. They are PII and high-cardinality, so services drop them.
     */
    private const USER_ATTRIBUTES = ['user.id', 'user.email', 'user.name'];

    /**
     * `before_send_metric` callback for the services' Sentry config. Keeps `\Sentry\*` out of
     * the projects while stripping the user attributes:
     *
     *   'before_send_metric' => [SentryMetricsReporter::class, 'beforeSendMetric'],
     */
    public static function beforeSendMetric(Metric $metric): Metric
    {
        $attributes = $metric->getAttributes();

        foreach (self::USER_ATTRIBUTES as $key) {
            $attributes->forget($key);
        }

        return $metric;
    }

    public function count(string $name, int|float $value = 1, array $attributes = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        traceMetrics()->count($name, $value, $attributes);
    }

    public function distribution(
        string $name,
        int|float $value,
        array $attributes = [],
        string $unit = self::UNIT_NONE,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        traceMetrics()->distribution($name, $value, $attributes, $this->toUnit($unit));
    }

    public function gauge(
        string $name,
        int|float $value,
        array $attributes = [],
        string $unit = self::UNIT_NONE,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        traceMetrics()->gauge($name, $value, $attributes, $this->toUnit($unit));
    }

    public function flush(): void
    {
        traceMetrics()->flush();
    }

    /**
     * Kill switch: the `enable_metrics` Sentry option (SENTRY_ENABLE_METRICS in the Laravel
     * config). Since sentry/sentry 4.31 the SDK no longer gates the manual metrics API on it
     * (the getter is deprecated, kept until 5.0), so this reporter enforces it. No client
     * (no DSN) also means nothing to emit.
     */
    private function enabled(): bool
    {
        $client = SentrySdk::getCurrentHub()->getClient();

        if ($client === null) {
            return false;
        }

        return $client->getOptions()->getEnableMetrics();
    }

    private function toUnit(string $unit): ?Unit
    {
        return match ($unit) {
            self::UNIT_MILLISECOND => Unit::millisecond(),
            self::UNIT_SECOND => Unit::second(),
            self::UNIT_BYTE => Unit::byte(),
            default => null,
        };
    }
}
