<?php

declare(strict_types=1);

namespace Emoti\CommonResources;

use Emoti\CommonResources\Commands\ExternalQueueWork;
use Emoti\CommonResources\Queue\Consumer\ConsumerInterface;
use Emoti\CommonResources\Queue\Consumer\RabbitMQConsumer;
use Emoti\CommonResources\Queue\Listeners\QueueJobMetricsSubscriber;
use Emoti\CommonResources\Services\Monitoring\ErrorReporterInterface;
use Emoti\CommonResources\Services\Monitoring\MetricsReporterInterface;
use Emoti\CommonResources\Services\Monitoring\QueueJobMetrics;
use Emoti\CommonResources\Services\Monitoring\SentryErrorReporter;
use Emoti\CommonResources\Services\Monitoring\SentryMetricsReporter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

final class CommonResourcesServiceProvider extends LaravelServiceProvider
{
    /** @var array<class-string, class-string> */
    public $bindings = [
        ConsumerInterface::class => RabbitMQConsumer::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/common-resources.php',
            'common-resources',
        );

        $this->app->bind(
            ErrorReporterInterface::class,
            config('common-resources.error_reporter', SentryErrorReporter::class),
        );

        $this->app->bind(
            MetricsReporterInterface::class,
            config('common-resources.metrics_reporter', SentryMetricsReporter::class),
        );

        // One instance per process: QueueJobMetrics owns the time-based flush clock shared by
        // the Laravel subscriber and the external consumer, the subscriber owns the in-flight
        // job start times.
        $this->app->singleton(QueueJobMetrics::class);
        $this->app->singleton(QueueJobMetricsSubscriber::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/common-resources.php' => config_path('common-resources.php'),
        ]);

        // Always registered. The kill switch is Sentry's `enable_metrics` option
        // (SENTRY_ENABLE_METRICS=false), enforced by SentryMetricsReporter.
        $this->app->make(QueueJobMetricsSubscriber::class)
            ->subscribe($this->app->make(Dispatcher::class));

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExternalQueueWork::class,
            ]);
        }
    }
}