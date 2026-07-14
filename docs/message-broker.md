# Message Broker

This project provides all the necessary tools needed for inter-service communication. It allows you to send events and
listen in on them.<br>
Please note that if you want to send an event through one service and receive it with the same service (which is
basically more of a job than an event), you should not use this solution, but rather an in-service queue.

**Shared network address**: common-resources-rabbitmq-1:5672
<br>
**GUI**: http://localhost:15672

## Usage in Laravel app

### Installation

1. Publish the config file:
   `php artisan vendor:publish --provider="Emoti\CommonResources\CommonResourcesServiceProvider"`
2. Fill in the necessary config variables.
3. Create `storage/app/private/.gitignore` file
   ```
   *
   !.gitignore
   ```

### Listening for events

1. Create a listener
   ```php
   <?php
      
   declare(strict_types=1);
   
   namespace App\Listeners;
   
   use Emoti\CommonResources\Queue\EmotiListenerInterface;
   use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
   use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;

   final readonly class ProductAddedToUpsellGroupListener implements EmotiListenerInterface
   {
       public function handle(EmotiEventInterface $event): void
       {
           assert($event instanceof ProductAddedToUpsellGroup);

           dump('Event received!');
       }
   }
   ```

2. Plug in the listener in _config/common-resources.php_:
   ```php
   'bindings' => [
       // event from common-resources package => listener from a project
       ProductAddedToUpsellGroup::class  => ProductAddedToUpsellGroupListener::class,
   ],
   ```

3. Listen for events:
   `php artisan common-resources:queue-external-work`<br>
   You can create a Makefile command for this: `make queue-external-work`

### Publishing events

```php
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;

$event = new ProductAddedToUpsellGroup(
    productId: 123,
    upsellGroupId: 456,
);
$event->dispatch(Site::PL);
```

## Usage in no-framework app

### Installation

1. Create the config file manually in _config/common-resources.php_, by copying the contents from this repository.
2. Fill in the necessary config variables.
3. Create `storage/app/private/.gitignore` file
   ```
   *
   !.gitignore
   ```

### Listening for events

1. Create a listener
   ```php
   <?php

   declare(strict_types=1);

   namespace App\Listeners;

   use Emoti\CommonResources\Queue\EmotiListenerInterface;
   use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
   use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;

   final readonly class ProductAddedToUpsellGroupListener implements EmotiListenerInterface
   {
       public function handle(EmotiEventInterface $event): void
       {
           assert($event instanceof ProductAddedToUpsellGroup);

           dump('Event received!');
       }
   }
   ```

2. Plug in the listener in _config/common-resources.php_:
   ```php
   'bindings' => [
       // event from common-resources package => listener from a project
       ProductAddedToUpsellGroup::class  => ProductAddedToUpsellGroupListener::class,
   ],
   ```

3. Create a PHP script to listen for events:
   ```php
   <?php
   
   require(dirname(__FILE__) . '/../system/init.inc.php');
   
   use Emoti\CommonResources\Queue\Consumer\RabbitMQConsumer;
   use function Sentry\captureException;
   
   (new RabbitMqConsumer())->consume(
       captureException: fn(Exception $e) => captureException($e)
   );
   ```
4. Run the script: `php cli/queue-external-work.php localhost:7000`

### Publishing events

```php
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;

$site = Site::fromLongNameUnderscoreCode(PROJECT);
$event = new ProductAddedToUpsellGroup(
    productId: 123,
    upsellGroupId: 456,
);
$event->dispatch($site);
```

## Event sequencing (order lifecycle)

The RabbitMQ transport is at-least-once: an event can be redelivered (consumer
scale-out, DLQ replay, nacks). For most events that is harmless, but the order
lifecycle events (`OrderPaid`, `OrderCancelled`, `OrderDeleted`, `OrderRestored`)
each undo an earlier one, so a **stale redelivery arriving after its inverse**
would otherwise re-apply an effect that has already been reversed. The `sequence`
field lets consumers drop those.

### Two independent axes

An order has two orthogonal, independently reversible states, each with its own
counter — an event on one axis says nothing about the other:

| Axis | Events | Meaning |
|------|--------|---------|
| **Payment** | `OrderPaid` / `OrderCancelled` | order is paid / marked unpaid |
| **Existence** | `OrderRestored` / `OrderDeleted` | order exists / is deleted |

The producer (agcore) keeps one monotonic counter **per order, per axis** and
stamps every event with the next value on its axis. Counters survive
delete/undelete.

### The contract

- `sequence` is a **positive** integer. `0` is the sentinel for
  *unsequenced / unknown* — emitted by producers predating the field, or by
  replayed old messages. Negative values are out of contract.
- A consumer keeps, **per order and per axis**, the highest `sequence` it has
  applied (its watermark), and **drops any event whose `sequence` is
  strictly-older than that watermark** ("drop strictly-older"). An event with
  `sequence = 0` is always applied — there is no staleness information to compare.
- Because the two axes have separate counters, staleness is judged
  **within an axis only**: an `OrderDeleted` (existence) is never compared
  against an `OrderPaid` (payment) watermark.

### `OrderRestored.isPaid` is an unsequenced snapshot

`OrderRestored` carries `isPaid` — the order's payment status *at restore time* —
so a consumer can reconcile the earn (payment-axis) effect on undelete without
waiting for a separate `OrderPaid`. But `isPaid` is only a **snapshot guarded by
the existence-axis `sequence`**; it carries **no payment-axis sequence**. A
consumer must therefore treat it as intent, not as an authoritative payment-axis
event: apply it idempotently and let a subsequent real `OrderPaid` /
`OrderCancelled` (with its own payment `sequence`) be the source of truth for the
payment axis. Do not let an `isPaid` snapshot override a newer payment-axis event.

### Operational requirements

- The order-lifecycle queue **must have exactly one consumer** (single writer per
  order), and the **DLQ must not be blind-replayed** — the per-axis sequence makes
  a stale replay *safe to drop*, not free to reorder against a live newer event.
