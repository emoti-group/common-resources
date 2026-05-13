# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

**emoti/common-resources** is a shared PHP library (`Emoti\CommonResources\`) for the Emoti microservices ecosystem. It serves two purposes:

1. **Shared PHP library** — Events, enums, DTOs, services, and traits consumed by multiple Laravel and no-framework PHP microservices via Composer symlink.
2. **Shared Docker infrastructure** — A central `docker-compose.yml` that runs RabbitMQ, Elasticsearch, and Traefik on a shared Docker network (`common-resources-network`) for local development.

**PHP:** ^8.1 | **Key deps:** `php-amqplib`, `spatie/laravel-data`, `ramsey/uuid`

## Source Structure

```
src/
├── Queue/
│   ├── Events/          # Domain events (Product/, Order/, Location/, Cache/, System/)
│   │   └── AbstractEmotiEvent.php
│   ├── Publisher/       # RabbitMQPublisher + PublisherInterface
│   ├── Consumer/        # RabbitMQConsumer + ConsumerInterface
│   └── Client/          # RabbitMQ connection setup
├── Enums/               # Type-safe constants (Lang, Site, ProductStatus, PromotionType, FeatureFlag, …)
├── DTO/                 # Data Transfer Objects (GeoJsonGeometryDTO, …)
├── Services/            # GeoJsonLineHelper, LocationsHelper, PdfService/
├── Traits/              # SingletonTrait, ArrayableEnumTrait
├── Commands/            # ExternalQueueWork artisan command
└── CommonResourcesServiceProvider.php
```

## Architecture: Events & Message Broker

- **Exchange naming:** `{env}.gifts` (e.g. `local.gifts`, `production.gifts`)
- **External queue naming:** `{env}.{project_name}.external`
- All events extend `AbstractEmotiEvent` and mix in:
  - `ArrayableTrait` — array serialization
  - `DispatchableTrait` — `$event->dispatch(Site::PL)` sends to RabbitMQ
  - `ExtraPropertiesTrait` — dynamic extra properties
- Namespace visibility is enforced via `DaveLiddament\PhpLanguageExtensions\NamespaceVisibility` attributes — event internals are restricted to `Emoti\CommonResources\Queue`.

### Publishing an event (Laravel)

```php
use Emoti\CommonResources\Queue\Events\Product\ProductUpdated;
use Emoti\CommonResources\Enums\Site;

$event = new ProductUpdated(productId: 123);
$event->dispatch(Site::PL);
```

### Consuming events (Laravel)

1. Publish config: `php artisan vendor:publish --provider="Emoti\CommonResources\CommonResourcesServiceProvider"`
2. Implement `EmotiListenerInterface` and register in `config/common-resources.php` under `bindings`.
3. Run: `php artisan common-resources:queue-external-work`

See `docs/message-broker.md` for full usage examples including no-framework apps.

## Local Development Setup

This package is developed via symlink in consuming projects — changes are live immediately.

```bash
# Start shared services (RabbitMQ, Elasticsearch, Traefik)
docker-compose up -d

# Stop services
docker-compose down
```

**To use this package locally in a consuming project:**

1. Place `common-resources/` next to the consuming project directory.
2. Mount in the consuming project's `docker-compose.yml`:
   ```yaml
   volumes:
     - ../common-resources:/var/www/common-resources:rw
   ```
3. Add to consuming project's `composer.json`:
   ```json
   "repositories": [{ "type": "path", "url": "../common-resources", "options": { "symlink": true } }]
   ```
4. `composer require emoti/common-resources:@dev`

## Services Reference

- **RabbitMQ** — `common-resources-rabbitmq-1:5672` | UI: http://localhost:15672 (user: dev, pass: dev)
- **Elasticsearch** — `common-resources-elasticsearch-1:9200` | UI: https://elasticvue.com/
- **Traefik** — UI: http://localhost:8081

## No Tests

This library has no test suite. Code quality relies on PHP strict typing (all files use `declare(strict_types=1)`), interface contracts, and PHPStan with `dave-liddament/phpstan-php-language-extensions`.

## Adding a New Event

1. Create a class in `src/Queue/Events/{Domain}/` extending `AbstractEmotiEvent`.
2. Add the required constructor properties and use the standard traits.
3. Update consuming services' `config/common-resources.php` bindings.
