<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderPaid;
use Emoti\CommonResources\Queue\Message;
use PHPUnit\Framework\TestCase;

final class OrderPaidTest extends TestCase
{
    public function test_round_trip_preserves_constructor_fields(): void
    {
        $event = new OrderPaid(id: 99, site: Site::PL, isB2b: true, eligibleAmountCents: 8000);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderPaid::fromArray($event->toArray());

        $this->assertSame(99, $restored->id);
        $this->assertSame(Site::PL, $restored->site);
        $this->assertTrue($restored->isB2b);
        $this->assertSame(8000, $restored->eligibleAmountCents);
    }

    public function test_from_array_defaults_new_fields_when_absent(): void
    {
        // Simulates an "old" message produced before the fields existed: the data
        // payload carries only id + site. Defaults must kick in instead of leaving
        // the typed properties uninitialized.
        $event = new OrderPaid(id: 7, site: Site::PL);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();
        unset($array['data']['isB2b'], $array['data']['eligibleAmountCents']);

        $restored = OrderPaid::fromArray($array);

        $this->assertSame(7, $restored->id);
        $this->assertFalse($restored->isB2b);
        $this->assertSame(0, $restored->eligibleAmountCents);
    }

    public function test_round_trip_preserves_non_b2b_and_zero_eligible_amount(): void
    {
        $event = new OrderPaid(id: 1, site: Site::EE, isB2b: false, eligibleAmountCents: 0);
        $event->setSite(Site::EE);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderPaid::fromArray($event->toArray());

        $this->assertFalse($restored->isB2b);
        $this->assertSame(0, $restored->eligibleAmountCents);
    }

    public function test_extra_properties_site_is_independent(): void
    {
        $event = new OrderPaid(id: 1, site: Site::PL, isB2b: false, eligibleAmountCents: 0);
        $event->setSite(Site::EE);

        $this->assertSame(Site::EE, $event->site());
    }

    public function test_routing_name_is_order_paid(): void
    {
        $this->assertSame('order.paid', OrderPaid::routingName());
    }

    public function test_version_is_one(): void
    {
        $this->assertSame(1, OrderPaid::version());
    }

    public function test_resource_id_returns_id(): void
    {
        $event = new OrderPaid(id: 42, site: Site::PL, isB2b: false, eligibleAmountCents: 0);

        $this->assertSame(42, $event->resourceId());
    }

    public function test_sequence_defaults_to_zero(): void
    {
        $event = new OrderPaid(id: 7, site: Site::PL);

        $this->assertSame(0, $event->sequence);
    }

    public function test_round_trip_preserves_non_zero_sequence(): void
    {
        $event = new OrderPaid(id: 99, site: Site::PL, isB2b: true, eligibleAmountCents: 8000, sequence: 5);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderPaid::fromArray($event->toArray());

        $this->assertSame(5, $restored->sequence);
    }

    public function test_from_array_defaults_sequence_to_zero_when_absent(): void
    {
        // Simulates an "old" message produced before sequence existed (or a DLQ replay).
        $event = new OrderPaid(id: 7, site: Site::PL);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();
        unset($array['data']['sequence']);

        $restored = OrderPaid::fromArray($array);

        $this->assertSame(7, $restored->id);
        $this->assertSame(0, $restored->sequence);
    }

    public function test_to_array_pins_wire_format(): void
    {
        // Under the reflection-based ArrayableTrait the property names ARE the
        // wire keys: renaming a property would break consumers while object
        // round-trip tests kept passing.
        $event = new OrderPaid(
            id: 99,
            site: Site::PL,
            isB2b: true,
            eligibleAmountCents: 8000,
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
            'eligibleAmountCents' => 8000,
            'orderUuid' => '11111111-2222-3333-4444-555555555555',
            'sequence' => 5,
        ], $array['data']);
        $this->assertSame('order.paid.v1', $array['routingKey']);
    }

    public function test_message_json_round_trip_preserves_sequence_and_wire_shape(): void
    {
        // The riskiest compat surface is `sequence`, freshly added to this
        // released event — exercise the real wire boundary (Message JSON), not
        // just an in-memory array round trip.
        $event = new OrderPaid(
            id: 99,
            site: Site::PL,
            isB2b: true,
            eligibleAmountCents: 8000,
            orderUuid: '11111111-2222-3333-4444-555555555555',
            sequence: 5,
        );
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $json = (new Message($event->toArray(), OrderPaid::class))->toJson();

        // Pin the actual wire bytes: site is the enum *value* string, sequence an int.
        $wire = json_decode($json, true)['content']['data'];
        $this->assertSame('pl', $wire['site']);
        $this->assertSame(5, $wire['sequence']);

        $restored = OrderPaid::fromArray(Message::fromJson($json)->content);

        $this->assertSame(99, $restored->id);
        $this->assertSame(Site::PL, $restored->site);
        $this->assertSame(5, $restored->sequence);
    }
}
