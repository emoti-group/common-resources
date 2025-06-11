<?php

declare(strict_types=1);

namespace Emoti\CommonResources;

use Emoti\CommonResources\Commands\ExternalQueueWork;
use Emoti\CommonResources\Queue\Consumer\ConsumerInterface;
use Emoti\CommonResources\Queue\Consumer\RabbitMQConsumer;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

final class CommonResourcesServiceProvider extends LaravelServiceProvider
{
    /** @var array<class-string, class-string> */
    public $bindings = [
        ConsumerInterface::class => RabbitMQConsumer::class,
    ];

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/common-resources.php' => config_path('common-resources.php'),
        ]);

        $this->mergeConfigFrom(
            __DIR__ . '/../config/common-resources.php',
            'common-resources',
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExternalQueueWork::class,
            ]);
        }
    }
}