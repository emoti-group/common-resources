<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use Emoti\CommonResources\CommonResourcesServiceProvider;
use Emoti\CommonResources\Facades\MetricsReporter;
use Emoti\CommonResources\Services\Monitoring\MetricsReporterInterface;
use Emoti\CommonResources\Services\Monitoring\SentryMetricsReporter;
use Tests\TestCase;

final class MetricsReporterConfigOverrideTest extends TestCase
{
    public function test_sentry_is_the_default_implementation(): void
    {
        $this->assertInstanceOf(
            SentryMetricsReporter::class,
            $this->app->make(MetricsReporterInterface::class),
        );
    }

    public function test_config_key_overrides_the_bound_implementation(): void
    {
        config()->set('common-resources.metrics_reporter', FakeMetricsReporter::class);

        (new CommonResourcesServiceProvider($this->app))->register();

        $this->assertInstanceOf(
            FakeMetricsReporter::class,
            $this->app->make(MetricsReporterInterface::class),
        );
    }

    public function test_facade_resolves_the_bound_reporter(): void
    {
        $fake = new FakeMetricsReporter();
        $this->app->instance(MetricsReporterInterface::class, $fake);

        MetricsReporter::count('orders.created', 2, ['site' => 'pl']);

        $this->assertSame(
            [['name' => 'orders.created', 'value' => 2, 'attributes' => ['site' => 'pl']]],
            $fake->counts,
        );
    }
}
