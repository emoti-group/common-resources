<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderCancelled;
use PHPUnit\Framework\TestCase;

final class OrderCancelledTest extends TestCase
{
    public function test_round_trip_preserves_constructor_fields(): void
    {
        $event = new OrderCancelled(id: 99, site: Site::PL, isB2b: true);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderCancelled::fromArray($event->toArray());

        $this->assertSame(99, $restored->id);
        $this->assertSame(Site::PL, $restored->site);
        $this->assertTrue($restored->isB2b);
    }

    public function test_round_trip_preserves_non_b2b(): void
    {
        $event = new OrderCancelled(id: 1, site: Site::EE, isB2b: false);
        $event->setSite(Site::EE);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderCancelled::fromArray($event->toArray());

        $this->assertFalse($restored->isB2b);
    }

    public function test_routing_name_is_order_cancelled(): void
    {
        $this->assertSame('order.cancelled', OrderCancelled::routingName());
    }

    public function test_resource_id_returns_id(): void
    {
        $event = new OrderCancelled(id: 42, site: Site::PL, isB2b: false);

        $this->assertSame(42, $event->resourceId());
    }
}
