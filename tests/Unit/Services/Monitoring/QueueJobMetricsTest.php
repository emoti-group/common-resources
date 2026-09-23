<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use Emoti\CommonResources\Services\Monitoring\MetricsReporterInterface;
use Emoti\CommonResources\Services\Monitoring\QueueJobMetrics;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QueueJobMetricsTest extends TestCase
{
    private FakeMetricsReporter $reporter;
    private QueueJobMetrics $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = new FakeMetricsReporter();
        $this->metrics = new QueueJobMetrics($this->reporter);
    }

    public function test_success_is_one_duration_entry_with_the_attempt_and_outcome(): void
    {
        $this->metrics->recordSuccess('App\Jobs\IndexItemJob', 'critical', QueueJobMetrics::RUNTIME_LARAVEL, 2, 12.3456);

        $this->assertSame([[
            'name' => QueueJobMetrics::METRIC_PROCESSED,
            'value' => 12.346,
            'attributes' => [
                'job' => 'App\Jobs\IndexItemJob',
                'queue' => 'critical',
                'runtime' => 'laravel',
                'attempt' => 2,
                'outcome' => 'success',
            ],
            'unit' => MetricsReporterInterface::UNIT_MILLISECOND,
        ]], $this->reporter->distributions);
        $this->assertSame([], $this->reporter->counts);
    }

    public function test_failure_adds_the_error_type_but_never_the_message(): void
    {
        $this->metrics->recordFailure(
            'App\Listeners\SyncOrder',
            'background_jobs',
            QueueJobMetrics::RUNTIME_EXTERNAL,
            4,
            250.0,
            new RuntimeException('connection refused to host 10.0.0.1'),
        );

        $entry = $this->reporter->distributions[0];
        $this->assertSame('failure', $entry['attributes']['outcome']);
        $this->assertSame(4, $entry['attributes']['attempt']);
        $this->assertSame(RuntimeException::class, $entry['attributes']['error.type']);
        $this->assertStringNotContainsString('10.0.0.1', json_encode($entry));
    }

    public function test_retry_is_a_duration_entry_of_its_own_attempt(): void
    {
        $this->metrics->recordRetry('Job', 'default', QueueJobMetrics::RUNTIME_EXTERNAL, 2, 30.5, new RuntimeException('boom'));

        $this->assertSame([[
            'name' => QueueJobMetrics::METRIC_PROCESSED,
            'value' => 30.5,
            'attributes' => [
                'job' => 'Job',
                'queue' => 'default',
                'runtime' => 'external',
                'attempt' => 2,
                'outcome' => 'retry',
                'error.type' => RuntimeException::class,
            ],
            'unit' => MetricsReporterInterface::UNIT_MILLISECOND,
        ]], $this->reporter->distributions);
    }

    public function test_retry_without_exception_has_no_error_type(): void
    {
        $this->metrics->recordRetry('Job', 'default', QueueJobMetrics::RUNTIME_LARAVEL, 1, 1.0, null);

        $this->assertArrayNotHasKey('error.type', $this->reporter->distributions[0]['attributes']);
    }

    public function test_anonymous_exception_class_is_stripped_to_its_parent(): void
    {
        $error = new class('x') extends InvalidArgumentException {};

        $this->assertSame(InvalidArgumentException::class, QueueJobMetrics::errorType($error));
    }

    public function test_skipped_is_attempt_zero_with_zero_duration(): void
    {
        $this->metrics->recordSkipped('Emoti\CommonResources\Queue\Events\Order\OrderPaid', 'critical', QueueJobMetrics::RUNTIME_EXTERNAL);

        $entry = $this->reporter->distributions[0];
        $this->assertSame('skipped', $entry['attributes']['outcome']);
        $this->assertSame(0, $entry['attributes']['attempt']);
        $this->assertSame(0.0, $entry['value']);
    }

    public function test_reporter_failures_never_propagate(): void
    {
        $this->reporter->throws = true;

        $this->metrics->recordSuccess('Job', 'default', QueueJobMetrics::RUNTIME_LARAVEL, 1, 1.0);
        $this->metrics->recordFailure('Job', 'default', QueueJobMetrics::RUNTIME_LARAVEL, 1, 1.0, new RuntimeException());
        $this->metrics->recordRetry('Job', 'default', QueueJobMetrics::RUNTIME_LARAVEL, 1, 1.0, null);
        $this->metrics->recordSkipped('Job', 'default', QueueJobMetrics::RUNTIME_LARAVEL);
        $this->metrics->flush();
        $this->metrics->flushIfOlderThan(0);

        $this->assertSame([], $this->reporter->distributions);
    }

    public function test_flush_if_older_than_flushes_immediately_the_first_time_then_only_when_due(): void
    {
        // Never flushed yet: the first metric of a fresh process must not wait for the interval.
        $this->metrics->flushIfOlderThan(3600);
        $this->assertSame(1, $this->reporter->flushes);

        // The clock restarts after a flush.
        $this->metrics->flushIfOlderThan(3600);
        $this->assertSame(1, $this->reporter->flushes);

        $this->metrics->flushIfOlderThan(0);
        $this->assertSame(2, $this->reporter->flushes);
    }
}
