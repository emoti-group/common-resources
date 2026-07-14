<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderRestored;
use Emoti\CommonResources\Queue\Message;
use PHPUnit\Framework\TestCase;

final class OrderRestoredTest extends TestCase
{
    public function test_round_trip_preserves_constructor_fields(): void
    {
        $event = new OrderRestored(id: 99, isB2b: true);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderRestored::fromArray($event->toArray());

        $this->assertSame(99, $restored->id);
        $this->assertSame(Site::PL, $restored->site());
        $this->assertTrue($restored->isB2b);
    }

    public function test_round_trip_preserves_non_b2b(): void
    {
        $event = new OrderRestored(id: 1, isB2b: false);
        $event->setSite(Site::EE);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderRestored::fromArray($event->toArray());

        $this->assertFalse($restored->isB2b);
    }

    public function test_routing_name_is_order_restored(): void
    {
        $this->assertSame('order.restored', OrderRestored::routingName());
    }

    public function test_version_is_one(): void
    {
        $this->assertSame(1, OrderRestored::version());
    }

    public function test_resource_id_returns_id(): void
    {
        $event = new OrderRestored(id: 42, isB2b: false);

        $this->assertSame(42, $event->resourceId());
    }

    public function test_round_trip_preserves_order_uuid(): void
    {
        $event = new OrderRestored(
            id: 99,
            isB2b: true,
            orderUuid: '11111111-2222-3333-4444-555555555555',
        );
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderRestored::fromArray($event->toArray());

        $this->assertSame('11111111-2222-3333-4444-555555555555', $restored->orderUuid);
    }

    public function test_from_array_defaults_order_uuid_to_null_when_absent(): void
    {
        // Simulates a message produced by an emitter without orderUuid support.
        $event = new OrderRestored(id: 7);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();
        unset($array['data']['orderUuid']);

        $restored = OrderRestored::fromArray($array);

        $this->assertSame(7, $restored->id);
        $this->assertNull($restored->orderUuid);
    }

    public function test_is_paid_defaults_to_false(): void
    {
        $event = new OrderRestored(id: 7);

        $this->assertFalse($event->isPaid);
    }

    public function test_round_trip_preserves_is_paid_true(): void
    {
        $event = new OrderRestored(id: 99, isB2b: true, isPaid: true);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderRestored::fromArray($event->toArray());

        $this->assertTrue($restored->isPaid);
    }

    public function test_round_trip_preserves_is_paid_false(): void
    {
        $event = new OrderRestored(id: 99, isB2b: true, isPaid: false);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderRestored::fromArray($event->toArray());

        $this->assertFalse($restored->isPaid);
    }

    public function test_from_array_defaults_is_paid_to_false_when_absent(): void
    {
        // Simulates a message produced by an emitter without isPaid support.
        $event = new OrderRestored(id: 7);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();
        unset($array['data']['isPaid']);

        $restored = OrderRestored::fromArray($array);

        $this->assertSame(7, $restored->id);
        $this->assertFalse($restored->isPaid);
    }

    public function test_sequence_defaults_to_zero(): void
    {
        $event = new OrderRestored(id: 7);

        $this->assertSame(0, $event->sequence);
    }

    public function test_round_trip_preserves_non_zero_sequence(): void
    {
        $event = new OrderRestored(id: 99, isB2b: true, isPaid: true, sequence: 5);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderRestored::fromArray($event->toArray());

        $this->assertSame(5, $restored->sequence);
    }

    public function test_from_array_defaults_sequence_to_zero_when_absent(): void
    {
        // Simulates an "old" message produced before sequence existed (or a DLQ replay).
        $event = new OrderRestored(id: 7);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();
        unset($array['data']['sequence']);

        $restored = OrderRestored::fromArray($array);

        $this->assertSame(7, $restored->id);
        $this->assertSame(0, $restored->sequence);
    }

    public function test_to_array_pins_wire_format(): void
    {
        // Under the reflection-based ArrayableTrait the property names ARE the
        // wire keys: renaming a property would break consumers while object
        // round-trip tests kept passing.
        $event = new OrderRestored(
            id: 99,
            isB2b: true,
            orderUuid: '11111111-2222-3333-4444-555555555555',
            isPaid: true,
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
            'isB2b' => true,
            'orderUuid' => '11111111-2222-3333-4444-555555555555',
            'isPaid' => true,
            'sequence' => 5,
        ], $array['data']);
        $this->assertSame('order.restored.v1', $array['routingKey']);
    }

    public function test_message_json_round_trip_preserves_fields(): void
    {
        // Exercises the real wire boundary (Message::toJson/fromJson) instead of
        // a PHP-array round trip.
        $event = new OrderRestored(
            id: 99,
            isB2b: true,
            orderUuid: '11111111-2222-3333-4444-555555555555',
            isPaid: true,
            sequence: 5,
        );
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $message = Message::fromJson((new Message($event->toArray(), OrderRestored::class))->toJson());

        $this->assertSame(OrderRestored::class, $message->class);

        $restored = OrderRestored::fromArray($message->content);

        $this->assertSame(99, $restored->id);
        $this->assertSame(Site::PL, $restored->site());
        $this->assertTrue($restored->isB2b);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $restored->orderUuid);
        $this->assertTrue($restored->isPaid);
        $this->assertSame(5, $restored->sequence);
    }
}
