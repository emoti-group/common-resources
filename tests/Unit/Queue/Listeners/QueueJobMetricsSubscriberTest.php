<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Listeners;

use Emoti\CommonResources\Queue\Listeners\QueueJobMetricsSubscriber;
use Emoti\CommonResources\Services\Monitoring\QueueJobMetrics;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\JobTimedOut;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Unit\Services\Monitoring\FakeMetricsReporter;

final class QueueJobMetricsSubscriberTest extends TestCase
{
    private FakeMetricsReporter $reporter;
    private Dispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = new FakeMetricsReporter();
        $this->events = new Dispatcher();
        (new QueueJobMetricsSubscriber(new QueueJobMetrics($this->reporter)))->subscribe($this->events);
    }

    public function test_processed_job_is_one_success_entry_with_measured_duration(): void
    {
        $job = $this->job('App\Jobs\IndexItemJob', 'critical', attempts: 1);

        $this->events->dispatch(new JobProcessing('rabbitmq', $job));
        usleep(2_000);
        $this->events->dispatch(new JobProcessed('rabbitmq', $job));

        $entries = $this->reporter->distributions;
        $this->assertCount(1, $entries);
        $this->assertSame([
            'job' => 'App\Jobs\IndexItemJob',
            'queue' => 'critical',
            'runtime' => 'laravel',
            'attempt' => 1,
            'outcome' => 'success',
        ], $entries[0]['attributes']);
        $this->assertGreaterThanOrEqual(2.0, $entries[0]['value']);
        $this->assertLessThan(1000.0, $entries[0]['value']);
    }

    public function test_job_released_by_the_worker_after_an_exception_is_one_retry_entry_with_its_duration(): void
    {
        // Worker order: JobExceptionOccurred, then release() + JobReleasedAfterException.
        $job = $this->job('App\Jobs\IndexItemJob', 'critical', attempts: 1);

        $this->events->dispatch(new JobProcessing('rabbitmq', $job));
        usleep(2_000);
        $this->events->dispatch(new JobExceptionOccurred('rabbitmq', $job, new RuntimeException('es down')));

        // Nothing is decided yet after the exception alone.
        $this->assertSame([], $this->reporter->distributions);

        $this->events->dispatch(new JobReleasedAfterException('rabbitmq', $job, 30));

        $entries = $this->outcomes('retry');
        $this->assertCount(1, $entries);
        $this->assertSame(1, $entries[0]['attributes']['attempt']);
        $this->assertSame(RuntimeException::class, $entries[0]['attributes']['error.type']);
        $this->assertGreaterThanOrEqual(2.0, $entries[0]['value']);
        $this->assertCount(1, $this->reporter->distributions);
    }

    public function test_sync_queue_order_exception_then_failed_is_one_failure_entry(): void
    {
        // SyncQueue::handleException fires JobExceptionOccurred BEFORE fail() marks the job failed.
        $failed = false;
        $job = $this->createMock(Job::class);
        $job->method('resolveQueuedJobClass')->willReturn('App\Jobs\SyncJob');
        $job->method('getQueue')->willReturn('sync');
        $job->method('attempts')->willReturn(1);
        $job->method('isReleased')->willReturn(false);
        $job->method('isDeleted')->willReturn(false);
        $job->method('uuid')->willReturn('sync-1');
        $job->method('hasFailed')->willReturnCallback(static fn (): bool => $failed);

        $exception = new RuntimeException('boom');

        $this->events->dispatch(new JobProcessing('sync', $job));
        usleep(2_000);
        $this->events->dispatch(new JobExceptionOccurred('sync', $job, $exception));
        $failed = true;
        $this->events->dispatch(new JobFailed('sync', $job, $exception));

        $this->assertCount(1, $this->reporter->distributions);
        $entry = $this->reporter->distributions[0];
        $this->assertSame('failure', $entry['attributes']['outcome']);
        $this->assertSame(RuntimeException::class, $entry['attributes']['error.type']);
        $this->assertGreaterThanOrEqual(2.0, $entry['value']);
    }

    public function test_final_failure_is_recorded_once_even_though_the_worker_fires_two_events(): void
    {
        // Worker order on the last attempt: fail() -> JobFailed, then JobExceptionOccurred.
        $job = $this->job('App\Jobs\IndexItemJob', 'critical', attempts: 3, hasFailed: true);
        $exception = new RuntimeException('still down');

        $this->events->dispatch(new JobProcessing('rabbitmq', $job));
        $this->events->dispatch(new JobFailed('rabbitmq', $job, $exception));
        $this->events->dispatch(new JobExceptionOccurred('rabbitmq', $job, $exception));

        $this->assertCount(1, $this->reporter->distributions);
        $entry = $this->reporter->distributions[0];
        $this->assertSame('failure', $entry['attributes']['outcome']);
        $this->assertSame(3, $entry['attributes']['attempt']);
        $this->assertSame(RuntimeException::class, $entry['attributes']['error.type']);
    }

    public function test_job_that_called_fail_itself_is_not_also_a_success(): void
    {
        // `$this->fail()` inside handle(): JobFailed fires, then the worker still fires JobProcessed.
        $job = $this->job('App\Jobs\IndexItemJob', 'critical', attempts: 1, hasFailed: true);

        $this->events->dispatch(new JobProcessing('rabbitmq', $job));
        $this->events->dispatch(new JobFailed('rabbitmq', $job, new RuntimeException('invalid payload')));
        $this->events->dispatch(new JobProcessed('rabbitmq', $job));

        $this->assertCount(1, $this->reporter->distributions);
        $this->assertSame('failure', $this->reporter->distributions[0]['attributes']['outcome']);
    }

    public function test_manual_release_is_a_retry_entry_without_error_type(): void
    {
        $job = $this->job('App\Jobs\IndexItemJob', 'critical', attempts: 1, isReleased: true);

        $this->events->dispatch(new JobProcessing('rabbitmq', $job));
        $this->events->dispatch(new JobProcessed('rabbitmq', $job));

        $entries = $this->outcomes('retry');
        $this->assertCount(1, $entries);
        $this->assertArrayNotHasKey('error.type', $entries[0]['attributes']);
        $this->assertSame([], $this->outcomes('success'));
    }

    public function test_job_class_is_preferred_over_the_payload_display_name(): void
    {
        $job = $this->job('App\Jobs\IndexItemJob', 'critical', attempts: 1);

        $this->events->dispatch(new JobProcessing('rabbitmq', $job));
        $this->events->dispatch(new JobProcessed('rabbitmq', $job));

        $this->assertSame('App\Jobs\IndexItemJob', $this->reporter->distributions[0]['attributes']['job']);
    }

    public function test_nested_sync_job_does_not_steal_the_outer_start_time(): void
    {
        $outer = $this->job('App\Jobs\OuterJob', 'default', attempts: 1, uuid: 'outer');
        $inner = $this->job('App\Jobs\InnerJob', 'sync', attempts: 1, uuid: 'inner');

        $this->events->dispatch(new JobProcessing('rabbitmq', $outer));
        usleep(3_000);
        $this->events->dispatch(new JobProcessing('sync', $inner));
        $this->events->dispatch(new JobProcessed('sync', $inner));
        $this->events->dispatch(new JobProcessed('rabbitmq', $outer));

        $byJob = $this->durationsByJob();
        $this->assertCount(2, $byJob);
        $this->assertGreaterThanOrEqual(3.0, $byJob['App\Jobs\OuterJob']);
        $this->assertLessThan($byJob['App\Jobs\OuterJob'], $byJob['App\Jobs\InnerJob']);
    }

    public function test_empty_uuid_does_not_make_two_jobs_share_a_start_time(): void
    {
        $first = $this->job('App\Jobs\A', 'default', attempts: 1, uuid: '');
        $second = $this->job('App\Jobs\B', 'default', attempts: 1, uuid: '');

        $this->events->dispatch(new JobProcessing('rabbitmq', $first));
        usleep(3_000);
        $this->events->dispatch(new JobProcessing('rabbitmq', $second));
        $this->events->dispatch(new JobProcessed('rabbitmq', $second));
        $this->events->dispatch(new JobProcessed('rabbitmq', $first));

        $byJob = $this->durationsByJob();
        $this->assertGreaterThanOrEqual(3.0, $byJob['App\Jobs\A']);
        $this->assertLessThan($byJob['App\Jobs\A'], $byJob['App\Jobs\B']);
    }

    public function test_timed_out_job_is_a_retry_entry_and_flushes_before_the_worker_dies(): void
    {
        $job = $this->job('App\Jobs\SlowJob', 'default', attempts: 1);

        $this->events->dispatch(new JobProcessing('rabbitmq', $job));
        $this->events->dispatch(new JobTimedOut('rabbitmq', $job));

        $this->assertCount(1, $this->outcomes('retry'));
        $this->assertSame(1, $this->reporter->flushes);
    }

    public function test_timed_out_job_that_was_failed_by_the_worker_only_flushes(): void
    {
        $job = $this->job('App\Jobs\SlowJob', 'default', attempts: 3, hasFailed: true);

        $this->events->dispatch(new JobProcessing('rabbitmq', $job));
        $this->events->dispatch(new JobFailed('rabbitmq', $job, new RuntimeException('timeout')));
        $this->events->dispatch(new JobTimedOut('rabbitmq', $job));

        $this->assertSame([], $this->outcomes('retry'));
        $this->assertCount(1, $this->outcomes('failure'));
        $this->assertSame(1, $this->reporter->flushes);
    }

    public function test_processed_without_processing_still_counts_with_zero_duration(): void
    {
        $job = $this->job('App\Jobs\IndexItemJob', 'critical', attempts: 1);

        $this->events->dispatch(new JobProcessed('rabbitmq', $job));

        $this->assertCount(1, $this->outcomes('success'));
        $this->assertSame(0.0, $this->reporter->distributions[0]['value']);
    }

    /** @return list<array<string, mixed>> */
    private function outcomes(string $outcome): array
    {
        return array_values(array_filter(
            $this->reporter->distributions,
            static fn (array $d): bool => $d['attributes']['outcome'] === $outcome,
        ));
    }

    /** @return array<string, float> */
    private function durationsByJob(): array
    {
        $byJob = [];
        foreach ($this->reporter->distributions as $d) {
            $byJob[$d['attributes']['job']] = $d['value'];
        }

        return $byJob;
    }

    private function job(
        string $name,
        string $queue,
        int $attempts,
        bool $hasFailed = false,
        bool $isReleased = false,
        ?string $uuid = null,
    ): Job {
        return $this->createConfiguredMock(Job::class, [
            'resolveQueuedJobClass' => $name,
            // displayName() may carry ids or free text; the subscriber must not use it.
            'resolveName' => 'Display name with id 12345',
            'getQueue' => $queue,
            'attempts' => $attempts,
            'hasFailed' => $hasFailed,
            'isReleased' => $isReleased,
            'isDeleted' => false,
            'uuid' => $uuid,
        ]);
    }
}
