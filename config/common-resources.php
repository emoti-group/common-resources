<?php

declare(strict_types=1);

use Emoti\CommonResources\Services\Monitoring\SentryErrorReporter;
use Emoti\CommonResources\Services\Monitoring\SentryMetricsReporter;

return [
    /**
     * env, project_name and rabbitmq.external_queue are needed to build the queue and exchange names.
     * - queue name will look like this: production.reviews-api.external (env.project_name.external_queue)
     * - exchange name will look like this: production.gifts (env.exchange)
     */
    'env' => env('APP_ENV', 'local'),
    'project_name' => env('PROJECT_NAME'),

    /**
     * Do not change the exchange value.
     * Exchange is only one for each env (local.gifts, staging.gifts and production.gifts).
     */
    'rabbitmq' => [
        'exchange' => 'gifts',
        'host' => env('RABBITMQ_HOST', 'common-resources-rabbitmq-1'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER') ?? env('RABBITMQ_USERNAME') ?? 'dev',
        'password' => env('RABBITMQ_PASSWORD', 'dev'),
    ],

    /**
     * Bindings define which events each named queue listens to and which listener handles them.
     * Each key is a queue name — it becomes part of the RabbitMQ queue: {env}.{project_name}.{queue_name}
     * Run a separate consumer process per queue:
     *   php artisan common-resources:queue-external:work critical
     *   php artisan common-resources:queue-external:work background_tasks
     *
     * The keys inside each group are event classes from common-resources.
     * The values are listener classes from the project.
     */
    'bindings' => [
        // 'critical' => [
        //     SomeEvent::class => SomeEventListener::class,
        // ],
        // 'background_tasks' => [
        //     AnotherEvent::class => AnotherEventListener::class,
        // ],
    ],

    /**
     * The implementation bound to ErrorReporterInterface (used by the ErrorReporter facade).
     * Defaults to Sentry. Override per-project to migrate a single repository to a
     * different monitoring vendor without affecting the others — point this at any
     * class implementing ErrorReporterInterface.
     */
    'error_reporter' => SentryErrorReporter::class,

    /**
     * The implementation bound to MetricsReporterInterface (used by the MetricsReporter facade
     * and by the queue job metrics emitted for every Laravel queue job and every external-queue
     * message — schema in Services\Monitoring\QueueJobMetrics). Same override rule as error_reporter.
     *
     * There is no on/off flag here on purpose. Disable metrics with Sentry's own option
     * ('enable_metrics' => false / SENTRY_ENABLE_METRICS=false), which turns every call into
     * a no-op, and set 'metric_flush_threshold' in config/sentry.php so the SDK buffer
     * auto-flushes instead of dropping its oldest entries.
     */
    'metrics_reporter' => SentryMetricsReporter::class,
];
