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
   
   use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
   use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;
   
   final readonly class ProductAddedToUpsellGroupListener implements EmotiEventInterface
   {
       public function handle(ProductAddedToUpsellGroup $event): void
       {
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

use Emoti\CommonResources\Queue\EmotiListenerInterface; use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;

final readonly class ProductAddedToUpsellGroupListener implements EmotiListenerInterface
{
public function handle(ProductAddedToUpsellGroup $event): void
{
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
