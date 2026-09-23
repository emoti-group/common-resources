<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services\Monitoring;

use Throwable;

/**
 * The one place that defines the queue-job metric and its attribute schema, so every
 * consumer (Laravel queue worker, common-resources external RabbitMQ consumer, agcore's
 * own consumer) emits identical series and one Sentry dashboard covers them all.
 *
 * Framework-free on purpose: `new QueueJobMetrics(new SentryMetricsReporter())` works in
 * plain PHP, and the Laravel container auto-wires it through {@see MetricsReporterInterface}.
 *
 * One metric, one entry per ATTEMPT:
 *
 *   `queue.job.processed` distribution (ms) — handler execution time of that attempt
 *   attrs: job, queue, runtime, attempt, outcome, error.type (retry/failure only)
 *
 * so in Sentry: jobs = count() with `outcome:!retry`, failures = count() with
 * `outcome:failure`, retries = count() with `outcome:retry`, latency = p95() with the
 * outcome you care about. Sentry stores every entry and aggregates at query time, so the
 * extra attributes cost nothing until you group by them.
 *
 * `job` and `error.type` are FQCNs (short names collide across namespaces), always taken
 * from an instantiated object, never from the message payload.
 *
 * Every reporter call is guarded: metrics must never fail, nack or slow down a job.
 */
final class QueueJobMetrics
{
    public const METRIC_PROCESSED = 'queue.job.processed';

    public const OUTCOME_SUCCESS = 'success';
    /** The attempt failed and the message will be tried again. */
    public const OUTCOME_RETRY = 'retry';
    /** The attempt failed and the message will not be tried again. */
    public const OUTCOME_FAILURE = 'failure';
    /** Message acknowledged without running a handler (e.g. no listener bound to the event). */
    public const OUTCOME_SKIPPED = 'skipped';

    public const RUNTIME_LARAVEL = 'laravel';
    public const RUNTIME_EXTERNAL = 'external';
    /** agcore's own RabbitMQ consumer (non-Laravel app, uses this class directly). */
    public const RUNTIME_AGCORE = 'agcore';

    public const ATTR_JOB = 'job';
    public const ATTR_QUEUE = 'queue';
    public const ATTR_RUNTIME = 'runtime';
    public const ATTR_ATTEMPT = 'attempt';
    public const ATTR_OUTCOME = 'outcome';
    public const ATTR_ERROR_TYPE = 'error.type';

    private float $lastFlushAt;

    public function __construct(
        private readonly MetricsReporterInterface $reporter,
    ) {
        $this->lastFlushAt = microtime(true);
    }

    public function recordSuccess(string $job, string $queue, string $runtime, int $attempt, float $durationMs): void
    {
        $this->recordAttempt($job, $queue, $runtime, $attempt, self::OUTCOME_SUCCESS, $durationMs, null);
    }

    public function recordRetry(
        string $job,
        string $queue,
        string $runtime,
        int $attempt,
        float $durationMs,
        ?Throwable $error,
    ): void {
        $this->recordAttempt($job, $queue, $runtime, $attempt, self::OUTCOME_RETRY, $durationMs, $error);
    }

    public function recordFailure(
        string $job,
        string $queue,
        string $runtime,
        int $attempt,
        float $durationMs,
        ?Throwable $error,
    ): void {
        $this->recordAttempt($job, $queue, $runtime, $attempt, self::OUTCOME_FAILURE, $durationMs, $error);
    }

    /**
     * Counted (attempt 0, duration 0) so that a missing binding shows up on the dashboard
     * instead of vanishing silently.
     */
    public function recordSkipped(string $job, string $queue, string $runtime): void
    {
        $this->recordAttempt($job, $queue, $runtime, 0, self::OUTCOME_SKIPPED, 0.0, null);
    }

    /**
     * Force-send buffered metrics (shutdown, timeout handlers).
     */
    public function flush(): void
    {
        $this->guard(function (): void {
            $this->reporter->flush();
            $this->lastFlushAt = microtime(true);
        });
    }

    /**
     * Time-based flush for long-lived consumers with low traffic, where a size-based
     * SDK threshold might not be reached for hours. Cheap when nothing is due.
     */
    public function flushIfOlderThan(int $seconds): void
    {
        if (microtime(true) - $this->lastFlushAt >= $seconds) {
            $this->flush();
        }
    }

    /**
     * Exception class without the `@anonymous/path/to/file.php:12$0` suffix PHP appends to
     * anonymous classes — that suffix is a new attribute value per file/line.
     */
    public static function errorType(Throwable $error): string
    {
        $class = $error::class;
        $at = strpos($class, '@');

        return $at === false ? $class : substr($class, 0, $at);
    }

    private function recordAttempt(
        string $job,
        string $queue,
        string $runtime,
        int $attempt,
        string $outcome,
        float $durationMs,
        ?Throwable $error,
    ): void {
        $attributes = [
            self::ATTR_JOB => $job,
            self::ATTR_QUEUE => $queue,
            self::ATTR_RUNTIME => $runtime,
            self::ATTR_ATTEMPT => $attempt,
            self::ATTR_OUTCOME => $outcome,
        ];

        if ($error !== null) {
            $attributes[self::ATTR_ERROR_TYPE] = self::errorType($error);
        }

        $this->guard(fn () => $this->reporter->distribution(
            self::METRIC_PROCESSED,
            round($durationMs, 3),
            $attributes,
            MetricsReporterInterface::UNIT_MILLISECOND,
        ));
    }

    /**
     * @param callable(): void $emit
     */
    private function guard(callable $emit): void
    {
        try {
            $emit();
        } catch (Throwable) {
            // Metrics are best-effort. A broken transport must never fail a job.
        }
    }
}
