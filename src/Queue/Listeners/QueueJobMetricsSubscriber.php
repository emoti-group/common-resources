<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Listeners;

use Emoti\CommonResources\Services\Monitoring\QueueJobMetrics;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\JobTimedOut;

/**
 * Emits one {@see QueueJobMetrics} entry per attempt of a Laravel queue job (Horizon,
 * `queue:work`, sync driver).
 *
 * Outcome of an attempt, derived from `Illuminate\Queue\Worker` and `Illuminate\Queue\SyncQueue`:
 *  - success = JobProcessed while the job was neither released nor failed
 *  - failure = JobFailed (the worker fires it once when the attempt exhausts
 *              maxTries / retryUntil / maxExceptions or when the job calls `$this->fail()`;
 *              the sync queue fires it after JobExceptionOccurred)
 *  - retry   = JobReleasedAfterException (the worker put the job back), JobProcessed on a
 *              job that released itself manually, or JobTimedOut on a job the worker did not
 *              fail (fires from the alarm handler right before the process is killed, so the
 *              buffer is flushed there)
 *
 * JobExceptionOccurred alone is NOT an outcome: the worker fires it after JobFailed, the sync
 * queue fires it before JobFailed, and a released job gets JobReleasedAfterException next.
 * Its exception is kept and attached to the outcome that follows.
 *
 * Start times are keyed per job instance: a `dispatchSync` inside a handler nests a second
 * JobProcessing/JobProcessed pair through the same dispatcher.
 */
final class QueueJobMetricsSubscriber
{
    /** Hard cap on in-flight entries in case a job never gets a terminal event. */
    private const MAX_TRACKED_JOBS = 1000;

    /** @var array<string, int> start time (hrtime ns) per in-flight job key */
    private array $startedAt = [];

    /** @var array<string, \Throwable> exception seen on JobExceptionOccurred, consumed by the outcome that follows */
    private array $pendingException = [];

    public function __construct(
        private readonly QueueJobMetrics $metrics,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(JobProcessing::class, [$this, 'onProcessing']);
        $events->listen(JobProcessed::class, [$this, 'onProcessed']);
        $events->listen(JobFailed::class, [$this, 'onFailed']);
        $events->listen(JobExceptionOccurred::class, [$this, 'onExceptionOccurred']);
        $events->listen(JobReleasedAfterException::class, [$this, 'onReleasedAfterException']);
        $events->listen(JobTimedOut::class, [$this, 'onTimedOut']);
    }

    public function onProcessing(JobProcessing $event): void
    {
        if (\count($this->startedAt) >= self::MAX_TRACKED_JOBS) {
            array_shift($this->startedAt);
            array_shift($this->pendingException);
        }

        $this->startedAt[self::key($event->job)] = hrtime(true);
    }

    public function onProcessed(JobProcessed $event): void
    {
        $job = $event->job;
        [$durationMs] = $this->finish($job);

        if ($job->hasFailed()) {
            return; // already recorded by onFailed()
        }

        if ($job->isReleased()) {
            // Manual `$this->release()` inside handle(): this attempt is over, the job runs again.
            $this->metrics->recordRetry(
                self::jobName($job),
                (string) $job->getQueue(),
                QueueJobMetrics::RUNTIME_LARAVEL,
                $job->attempts(),
                $durationMs,
                null,
            );

            return;
        }

        $this->metrics->recordSuccess(
            self::jobName($job),
            (string) $job->getQueue(),
            QueueJobMetrics::RUNTIME_LARAVEL,
            $job->attempts(),
            $durationMs,
        );
    }

    public function onFailed(JobFailed $event): void
    {
        $job = $event->job;
        [$durationMs] = $this->finish($job);

        $this->metrics->recordFailure(
            self::jobName($job),
            (string) $job->getQueue(),
            QueueJobMetrics::RUNTIME_LARAVEL,
            $job->attempts(),
            $durationMs,
            $event->exception,
        );
    }

    public function onExceptionOccurred(JobExceptionOccurred $event): void
    {
        $job = $event->job;

        if ($job->hasFailed()) {
            return; // recorded by onFailed()
        }

        $key = self::key($job);

        if ($job->isDeleted()) {
            // Deleted itself and then threw: no terminal event will follow.
            unset($this->startedAt[$key], $this->pendingException[$key]);

            return;
        }

        $this->pendingException[$key] = $event->exception;
    }

    public function onReleasedAfterException(JobReleasedAfterException $event): void
    {
        $job = $event->job;
        [$durationMs, $exception] = $this->finish($job);

        $this->metrics->recordRetry(
            self::jobName($job),
            (string) $job->getQueue(),
            QueueJobMetrics::RUNTIME_LARAVEL,
            $job->attempts(),
            $durationMs,
            $exception,
        );
    }

    public function onTimedOut(JobTimedOut $event): void
    {
        $job = $event->job;
        [$durationMs] = $this->finish($job);

        // The worker already ran markJobAsFailedIf*() for this job; if none of them failed
        // it, the message goes back to the queue after the process dies.
        if (! $job->hasFailed()) {
            $this->metrics->recordRetry(
                self::jobName($job),
                (string) $job->getQueue(),
                QueueJobMetrics::RUNTIME_LARAVEL,
                $job->attempts(),
                $durationMs,
                null,
            );
        }

        // The process is killed right after this event: send what we have (best effort,
        // bounded by the SDK's HTTP timeout).
        $this->metrics->flush();
    }

    /**
     * Closes the in-flight entry of the job: elapsed time since JobProcessing (0.0 when the
     * start was never seen) and the exception kept from JobExceptionOccurred, if any.
     *
     * @return array{0: float, 1: \Throwable|null}
     */
    private function finish(Job $job): array
    {
        $key = self::key($job);
        $startedAt = $this->startedAt[$key] ?? null;
        $exception = $this->pendingException[$key] ?? null;
        unset($this->startedAt[$key], $this->pendingException[$key]);

        return [
            $startedAt === null ? 0.0 : (hrtime(true) - $startedAt) / 1_000_000,
            $exception,
        ];
    }

    /**
     * The queued job class, not `resolveName()`: that one returns the payload's `displayName`,
     * which a job may override with ids or free text (and closures report file:line), i.e.
     * unbounded attribute cardinality.
     */
    private static function jobName(Job $job): string
    {
        $class = (string) $job->resolveQueuedJobClass();

        return $class !== '' ? $class : (string) $job->resolveName();
    }

    private static function key(Job $job): string
    {
        // uuid() may be null or ""; neither may collapse two jobs onto one key.
        $uuid = (string) $job->uuid();

        return $uuid !== '' ? $uuid : (string) spl_object_id($job);
    }
}
