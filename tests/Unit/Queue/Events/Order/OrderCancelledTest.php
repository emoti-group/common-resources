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

    public function test_round_trip_preserves_order_uuid(): void
    {
        $event = new OrderCancelled(
            id: 99,
            site: Site::PL,
            isB2b: true,
            orderUuid: '11111111-2222-3333-4444-555555555555',
        );
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderCancelled::fromArray($event->toArray());

        $this->assertSame('11111111-2222-3333-4444-555555555555', $restored->orderUuid);
    }

    public function test_from_array_defaults_order_uuid_to_null_when_absent(): void
    {
        // Simulates an "old" message produced before orderUuid existed.
        $event = new OrderCancelled(id: 7, site: Site::PL);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();
        unset($array['data']['orderUuid']);

        $restored = OrderCancelled::fromArray($array);

        $this->assertSame(7, $restored->id);
        $this->assertNull($restored->orderUuid);
    }
}
