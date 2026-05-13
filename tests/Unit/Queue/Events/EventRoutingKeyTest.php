<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events;

use Emoti\CommonResources\Queue\Events\Cache\CloudflareCachePurgeRequested;
use Emoti\CommonResources\Queue\Events\Location\LocationUpdated;
use Emoti\CommonResources\Queue\Events\Order\OrderPaid;
use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;
use Emoti\CommonResources\Queue\Events\Product\ProductRemovedFromUpsellGroup;
use Emoti\CommonResources\Queue\Events\Product\ProductUpdated;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Emoti\CommonResources\Queue\Events\System\ExternalQueueRestartRequested;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventRoutingKeyTest extends TestCase
{
    public function test_routing_key_follows_name_dot_version_format(): void
    {
        $this->assertSame(
            ProductAddedToUpsellGroup::routingName() . '.v' . ProductAddedToUpsellGroup::version(),
            ProductAddedToUpsellGroup::routingKey(),
        );
    }

    #[DataProvider('eventRoutingKeyProvider')]
    public function test_routing_keys_are_stable(string $eventClass, string $expectedKey): void
    {
        /** @var class-string<EmotiEventInterface> $eventClass */
        $this->assertSame($expectedKey, $eventClass::routingKey());
    }

    public static function eventRoutingKeyProvider(): array
    {
        return [
            [ProductAddedToUpsellGroup::class, 'product.added_to_upsell_group.v1'],
            [ProductRemovedFromUpsellGroup::class, 'product.removed_from_upsell_group.v1'],
            [ProductUpdated::class, 'product.updated.v1'],
            [OrderPaid::class, 'order.paid.v1'],
            [LocationUpdated::class, 'location.updated.v1'],
            [CloudflareCachePurgeRequested::class, 'cloudflare.cache_purge_requested.v1'],
            [ExternalQueueRestartRequested::class, 'system.restart.v1'],
        ];
    }
}
