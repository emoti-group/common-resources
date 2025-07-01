<?php

declare(strict_types=1);

return [
    /**
     * env, project_name and rabbitmq.external_queue are needed to build the queue and exchange names.
     * - queue name will look like this: production.reviews-api.external (env.project_name.external_queue)
     * - exchange name will look like this: production.gifts (env.exchange)
     */
    'env' => env('APP_ENV', 'local'),
    'project_name' => env('PROJECT_NAME'),

    /**
     * Do not change the exchange and external_queue values.
     * Exchange is only one for each env (local.gifts, staging.gifts and production.gifts).
     * The external_queue should be the same for each project, to easily identify which queue receives the external events.
     */
    'rabbitmq' => [
        'exchange' => 'gifts',
        'external_queue' => 'external',
        'host' => env('RABBITMQ_HOST', 'common-resources-rabbitmq-1'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER') ?? env('RABBITMQ_USERNAME') ?? 'dev',
        'password' => env('RABBITMQ_PASSWORD', 'dev'),
    ],

    /**
     * The bindings are used to set to which events the project should listen to.
     * If you specify at least one event here, then the RabbitMQ exchange will register that it should route this specific event to this project's external queue.
     *
     * The keys are the event classes from the common-resources package.
     * The values are the listener classes from the project.
     */
    'bindings' => [
        // event::class => listener::class,
    ],
];
