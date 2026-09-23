<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use Emoti\CommonResources\Services\Monitoring\MetricsReporterInterface;
use Emoti\CommonResources\Services\Monitoring\SentryMetricsReporter;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Metrics\Types\CounterMetric;
use Sentry\Metrics\Types\DistributionMetric;
use Sentry\Metrics\Types\GaugeMetric;
use Sentry\Metrics\Types\Metric;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * Drives the real SDK aggregator and intercepts every metric in `before_send_metric`
 * (returning null so nothing is buffered or sent).
 */
final class SentryMetricsReporterTest extends TestCase
{
    /** @var list<Metric> */
    private array $captured = [];
    private HubInterface $previousHub;
    private SentryMetricsReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousHub = SentrySdk::getCurrentHub();

        SentrySdk::setCurrentHub(new Hub($this->client()));
        $this->reporter = new SentryMetricsReporter();
    }

    protected function tearDown(): void
    {
        SentrySdk::setCurrentHub($this->previousHub);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function client(array $options = []): ClientInterface
    {
        return ClientBuilder::create($options + [
            'dsn' => 'https://public@sentry.invalid/1',
            // No error/exception handler integrations: they would leak out of the test.
            'default_integrations' => false,
            'before_send_metric' => function (Metric $metric): ?Metric {
                $this->captured[] = $metric;

                return null;
            },
        ])->getClient();
    }

    public function test_count_becomes_a_counter_metric_with_attributes(): void
    {
        $this->reporter->count('queue.job.processed', 1, ['job' => 'App\Jobs\Foo', 'attempts' => 2, 'ok' => true]);

        $this->assertCount(1, $this->captured);
        $metric = $this->captured[0];
        $this->assertSame(CounterMetric::TYPE, $metric->getType());
        $this->assertSame('queue.job.processed', $metric->getName());
        $this->assertSame(1, $metric->getValue());
        $this->assertNull($metric->getUnit());

        $attributes = $metric->getAttributes()->toSimpleArray();
        $this->assertSame('App\Jobs\Foo', $attributes['job']);
        $this->assertSame(2, $attributes['attempts']);
        $this->assertTrue($attributes['ok']);
    }

    public function test_distribution_maps_the_millisecond_unit(): void
    {
        $this->reporter->distribution('queue.job.processed', 12.5, ['job' => 'x'], MetricsReporterInterface::UNIT_MILLISECOND);

        $metric = $this->captured[0];
        $this->assertSame(DistributionMetric::TYPE, $metric->getType());
        $this->assertSame(12.5, $metric->getValue());
        $this->assertSame('millisecond', (string) $metric->getUnit());
    }

    public function test_gauge_maps_the_second_unit_and_none_means_no_unit(): void
    {
        $this->reporter->gauge('queue.depth', 42, ['queue' => 'critical'], MetricsReporterInterface::UNIT_SECOND);
        $this->reporter->gauge('queue.consumer.heartbeat', 1, ['queue' => 'critical']);

        $this->assertSame(GaugeMetric::TYPE, $this->captured[0]->getType());
        $this->assertSame('second', (string) $this->captured[0]->getUnit());
        $this->assertNull($this->captured[1]->getUnit());
    }

    public function test_flush_with_an_empty_buffer_is_a_no_op(): void
    {
        $this->reporter->flush();

        $this->assertSame([], $this->captured);
    }

    public function test_enable_metrics_false_turns_every_call_into_a_no_op(): void
    {
        SentrySdk::setCurrentHub(new Hub($this->client(['enable_metrics' => false])));

        $this->reporter->count('queue.job.processed');
        $this->reporter->distribution('queue.job.processed', 1.0);
        $this->reporter->gauge('queue.depth', 1);

        $this->assertSame([], $this->captured);
    }

    public function test_without_a_client_nothing_is_emitted(): void
    {
        SentrySdk::setCurrentHub(new Hub(null));

        $this->reporter->count('queue.job.processed');
        $this->reporter->flush();

        $this->assertSame([], $this->captured);
    }

    public function test_before_send_metric_strips_the_user_attributes_the_sdk_adds_from_scope(): void
    {
        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope): void {
            $scope->setUser(['id' => 42, 'email' => 'someone@example.com', 'username' => 'someone']);
        });

        $this->reporter->count('queue.job.processed', 1, ['job' => 'x']);

        // The SDK really did attach the user before our callback would run.
        $raw = $this->captured[0]->getAttributes()->toSimpleArray();
        $this->assertSame('someone@example.com', $raw['user.email']);

        $cleaned = SentryMetricsReporter::beforeSendMetric($this->captured[0])->getAttributes()->toSimpleArray();
        $this->assertArrayNotHasKey('user.id', $cleaned);
        $this->assertArrayNotHasKey('user.email', $cleaned);
        $this->assertArrayNotHasKey('user.name', $cleaned);
        $this->assertSame('x', $cleaned['job']);
    }
}
