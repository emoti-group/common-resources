<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderDeleted;
use Emoti\CommonResources\Queue\Message;
use PHPUnit\Framework\TestCase;

final class OrderDeletedTest extends TestCase
{
    public function test_round_trip_preserves_constructor_fields(): void
    {
        $event = new OrderDeleted(id: 99, site: Site::PL, isB2b: true);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderDeleted::fromArray($event->toArray());

        $this->assertSame(99, $restored->id);
        $this->assertSame(Site::PL, $restored->site);
        $this->assertTrue($restored->isB2b);
    }

    public function test_round_trip_preserves_non_b2b(): void
    {
        $event = new OrderDeleted(id: 1, site: Site::EE, isB2b: false);
        $event->setSite(Site::EE);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderDeleted::fromArray($event->toArray());

        $this->assertFalse($restored->isB2b);
    }

    public function test_routing_name_is_order_deleted(): void
    {
        $this->assertSame('order.deleted', OrderDeleted::routingName());
    }

    public function test_version_is_one(): void
    {
        $this->assertSame(1, OrderDeleted::version());
    }

    public function test_resource_id_returns_id(): void
    {
        $event = new OrderDeleted(id: 42, site: Site::PL, isB2b: false);

        $this->assertSame(42, $event->resourceId());
    }

    public function test_round_trip_preserves_order_uuid(): void
    {
        $event = new OrderDeleted(
            id: 99,
            site: Site::PL,
            isB2b: true,
            orderUuid: '11111111-2222-3333-4444-555555555555',
        );
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderDeleted::fromArray($event->toArray());

        $this->assertSame('11111111-2222-3333-4444-555555555555', $restored->orderUuid);
    }

    public function test_from_array_defaults_order_uuid_to_null_when_absent(): void
    {
        // Simulates an "old" message produced before orderUuid existed.
        $event = new OrderDeleted(id: 7, site: Site::PL);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();
        unset($array['data']['orderUuid']);

        $restored = OrderDeleted::fromArray($array);

        $this->assertSame(7, $restored->id);
        $this->assertNull($restored->orderUuid);
    }

    public function test_sequence_defaults_to_zero(): void
    {
        $event = new OrderDeleted(id: 7, site: Site::PL);

        $this->assertSame(0, $event->sequence);
    }

    public function test_round_trip_preserves_non_zero_sequence(): void
    {
        $event = new OrderDeleted(id: 99, site: Site::PL, isB2b: true, sequence: 5);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderDeleted::fromArray($event->toArray());

        $this->assertSame(5, $restored->sequence);
    }

    public function test_from_array_defaults_sequence_to_zero_when_absent(): void
    {
        // Simulates an "old" message produced before sequence existed (or a DLQ replay).
        $event = new OrderDeleted(id: 7, site: Site::PL);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();
        unset($array['data']['sequence']);

        $restored = OrderDeleted::fromArray($array);

        $this->assertSame(7, $restored->id);
        $this->assertSame(0, $restored->sequence);
    }

    public function test_to_array_pins_wire_format(): void
    {
        // Under the reflection-based ArrayableTrait the property names ARE the
        // wire keys: renaming a property would break consumers while object
        // round-trip tests kept passing.
        $event = new OrderDeleted(
            id: 99,
            site: Site::PL,
            isB2b: true,
            orderUuid: '11111111-2222-3333-4444-555555555555',
            sequence: 5,
        );
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();

        $this->assertSame(
            ['site', 'sendAt', 'data', 'resourceId', 'resourceUuid', 'version', 'eventId', 'routingKey'],
            array_keys($array),
        );
        $this->assertSame([
            'id' => 99,
            'site' => Site::PL,
            'isB2b' => true,
            'orderUuid' => '11111111-2222-3333-4444-555555555555',
            'sequence' => 5,
        ], $array['data']);
        $this->assertSame('order.deleted.v1', $array['routingKey']);
    }

    public function test_message_json_round_trip_preserves_fields(): void
    {
        // Exercises the real wire boundary (Message::toJson/fromJson) instead of
        // a PHP-array round trip.
        $event = new OrderDeleted(
            id: 99,
            site: Site::PL,
            isB2b: true,
            orderUuid: '11111111-2222-3333-4444-555555555555',
            sequence: 5,
        );
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $message = Message::fromJson((new Message($event->toArray(), OrderDeleted::class))->toJson());

        $this->assertSame(OrderDeleted::class, $message->class);

        $restored = OrderDeleted::fromArray($message->content);

        $this->assertSame(99, $restored->id);
        $this->assertSame(Site::PL, $restored->site);
        $this->assertTrue($restored->isB2b);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $restored->orderUuid);
        $this->assertSame(5, $restored->sequence);
    }
}
