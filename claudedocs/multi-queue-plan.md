# Plan: Multi-Queue Support per Project

## Goal

Allow a consuming project to have multiple named RabbitMQ queues instead of a single `external` queue. This lets high-priority and low-priority work be separated and scaled independently.

## Queue Naming

Queues follow the existing pattern with the queue name as the suffix:

```
{env}.{project_name}.{queue_name}
```

Examples:
- `local.gifts-api.critical`
- `local.gifts-api.background_tasks`
- `production.reviews-api.product_update`

## Config Change

`config/common-resources.php` — bindings are grouped by queue name instead of being a flat map:

```php
// BEFORE
'bindings' => [
    ProductUpdated::class => ProductUpdatedListener::class,
    OrderPaid::class      => OrderPaidListener::class,
],

// AFTER
'bindings' => [
    'critical' => [
        ProductUpdated::class => ProductUpdatedListener::class,
    ],
    'background_tasks' => [
        OrderPaid::class => OrderPaidListener::class,
    ],
],
```

The `rabbitmq.external_queue` config key is removed — the queue name now comes from the command argument.

## Command Change

`ExternalQueueWork` gains a required argument:

```bash
php artisan common-resources:queue-external:work critical
php artisan common-resources:queue-external:work background_tasks
```

The argument is passed through to the consumer so it knows which queue group to set up.

## Files to Change

| File | Change |
|------|--------|
| `config/common-resources.php` | Group bindings by queue name; remove `rabbitmq.external_queue` |
| `src/Commands/ExternalQueueWork.php` | Add required `{queue}` argument; pass it to `consume()` |
| `src/Queue/Consumer/ConsumerInterface.php` | Add `string $queueName` parameter to `consume()` |
| `src/Queue/Consumer/RabbitMQConsumer.php` | Accept `$queueName`; pass to `RabbitMQSetupper`; filter bindings by queue name |
| `src/Queue/Client/RabbitMQSetupper.php` | Accept `$queueName`; use it as queue suffix; filter routing keys to only those in the named group |

`RabbitMQClient::declareQueue()` already accepts a `$queueSuffix` string — no change needed there.

## Bindings File

`storage/app/private/rabbitmq_bindings.json` currently tracks bound routing keys to detect removals. With multiple queues, this file needs to be keyed per queue name (e.g., `rabbitmq_bindings_{queue_name}.json`) so each queue's bindings are tracked independently.

## Backward Compatibility

Projects not yet migrated to the grouped bindings format will break — the config structure is a breaking change. Consuming projects must update their `config/common-resources.php` and their process manager/Makefile commands when upgrading.
