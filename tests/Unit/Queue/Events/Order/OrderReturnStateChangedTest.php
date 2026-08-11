<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderReturnStateChanged;
use Emoti\CommonResources\Queue\Message;
use PHPUnit\Framework\TestCase;

final class OrderReturnStateChangedTest extends TestCase
{
    private function make(array $overrides = []): OrderReturnStateChanged
    {
        $site = $overrides['site'] ?? Site::PL;

        $event = new OrderReturnStateChanged(
            id: $overrides['id'] ?? 4321,
            isB2b: $overrides['isB2b'] ?? false,
            // The loyalty-discount pair and the eligible-amount pair are kept at
            // clearly different magnitudes throughout this file (never all four
            // equal) so a bug that transposes the two pairs fails visibly instead
            // of the coincidence masking it.
            returnedLoyaltyDiscountCents: $overrides['returnedLoyaltyDiscountCents'] ?? 3333,
            orderLoyaltyDiscountCents: $overrides['orderLoyaltyDiscountCents'] ?? 8800,
            returnedEligibleAmountCents: $overrides['returnedEligibleAmountCents'] ?? 2500,
            orderEligibleAmountCents: $overrides['orderEligibleAmountCents'] ?? 10000,
            // array_key_exists, not `??` — an explicit null must reach the payload.
            orderUuid: array_key_exists('orderUuid', $overrides)
                ? $overrides['orderUuid']
                : 'aaaa1111-bbbb-2222-cccc-333344445555',
            sequence: $overrides['sequence'] ?? 7,
        );
        // setSite() is REQUIRED, not decoration: `site` is not a constructor
        // parameter, so the envelope copy is the only place it lives. dispatch()
        // does this in production.
        $event->setSite($site);
        $event->setEventId();
        $event->setSendAt();

        return $event;
    }

    public function test_routing_name_is_order_returns_changed(): void
    {
        $this->assertSame('order.returns_changed', OrderReturnStateChanged::routingName());
    }

    public function test_version_is_one(): void
    {
        $this->assertSame(1, OrderReturnStateChanged::version());
    }

    public function test_resource_id_returns_id(): void
    {
        $this->assertSame(4321, $this->make()->resourceId());
    }

    public function test_resource_uuid_is_null(): void
    {
        $this->assertNull($this->make()->resourceUuid());
    }

    public function test_round_trip_preserves_constructor_fields(): void
    {
        $restored = OrderReturnStateChanged::fromArray($this->make()->toArray());

        $this->assertSame(4321, $restored->id);
        // site() not ->site: with `site` out of the constructor it is the trait's
        // protected property, reachable only through the accessor.
        $this->assertSame(Site::PL, $restored->site());
        $this->assertFalse($restored->isB2b);
        $this->assertSame(3333, $restored->returnedLoyaltyDiscountCents);
        $this->assertSame(8800, $restored->orderLoyaltyDiscountCents);
        $this->assertSame(2500, $restored->returnedEligibleAmountCents);
        $this->assertSame(10000, $restored->orderEligibleAmountCents);
        $this->assertSame('aaaa1111-bbbb-2222-cccc-333344445555', $restored->orderUuid);
        $this->assertSame(7, $restored->sequence);
    }

    public function test_to_array_pins_wire_format(): void
    {
        $array = $this->make()->toArray();

        // The envelope's own keys, in order — a consumer reads `data` out of this
        // shape, so a change here is a wire change even if `data` is untouched.
        $this->assertSame(
            ['site', 'sendAt', 'data', 'resourceId', 'resourceUuid', 'version', 'eventId', 'routingKey'],
            array_keys($array),
        );
        $this->assertSame('order.returns_changed.v1', $array['routingKey']);

        // `data` mirrors the constructor parameters in order. `site` is absent by
        // design: it lives at envelope level only, which is why the round-trip
        // below still restores it.
        $this->assertSame(
            [
                'id' => 4321,
                'isB2b' => false,
                'returnedLoyaltyDiscountCents' => 3333,
                'orderLoyaltyDiscountCents' => 8800,
                'returnedEligibleAmountCents' => 2500,
                'orderEligibleAmountCents' => 10000,
                'orderUuid' => 'aaaa1111-bbbb-2222-cccc-333344445555',
                'sequence' => 7,
            ],
            $array['data'],
        );
    }

    public function test_sequence_defaults_to_zero_on_construction(): void
    {
        // Distinct from the fromArray() default below: this is the CONSTRUCTOR's
        // default, which is what a producer that forgets to stamp one would send.
        $event = new OrderReturnStateChanged(id: 4321);

        $this->assertSame(0, $event->sequence);
    }

    public function test_from_array_defaults_the_optional_fields_when_absent(): void
    {
        // A gifts-api deployed ahead of agcore must not fatal on a short payload.
        // `id` stays present: it is required and agcore always sends it.
        $array = $this->make()->toArray();
        $array['data'] = ['id' => 4321];

        $restored = OrderReturnStateChanged::fromArray($array);

        $this->assertSame(4321, $restored->id);
        $this->assertFalse($restored->isB2b);
        $this->assertSame(0, $restored->returnedLoyaltyDiscountCents);
        $this->assertSame(0, $restored->orderLoyaltyDiscountCents);
        $this->assertSame(0, $restored->returnedEligibleAmountCents);
        $this->assertSame(0, $restored->orderEligibleAmountCents);
        $this->assertNull($restored->orderUuid);
        $this->assertSame(0, $restored->sequence);
    }

    public function test_site_survives_an_empty_data_payload(): void
    {
        // fromArray() prefers the envelope copy, which is why `site` needs no default.
        $array = $this->make(['site' => Site::EE])->toArray();
        $array['data'] = ['id' => 4321];

        $this->assertSame(Site::EE, OrderReturnStateChanged::fromArray($array)->site());
    }

    public function test_b2b_flag_round_trips(): void
    {
        $this->assertTrue(OrderReturnStateChanged::fromArray($this->make(['isB2b' => true])->toArray())->isB2b);
    }

    public function test_null_order_uuid_round_trips(): void
    {
        $this->assertNull(OrderReturnStateChanged::fromArray($this->make(['orderUuid' => null])->toArray())->orderUuid);
    }

    public function test_message_json_round_trip_preserves_fields(): void
    {
        // Exercise the real wire boundary (the Message envelope), not just an
        // in-memory array round trip — mirrors OrderPaidTest's sibling test.
        $event = $this->make();

        $json = (new Message($event->toArray(), OrderReturnStateChanged::class))->toJson();

        // Pin the actual wire bytes. `site` is stringified at ENVELOPE level and is
        // absent from `data`; the payload figures live in `data`.
        $content = json_decode($json, true)['content'];
        $this->assertSame('pl', $content['site']);
        $this->assertArrayNotHasKey('site', $content['data']);
        $this->assertSame(7, $content['data']['sequence']);

        $restored = OrderReturnStateChanged::fromArray(Message::fromJson($json)->content);

        $this->assertSame(4321, $restored->id);
        $this->assertSame(Site::PL, $restored->site());
        $this->assertSame(7, $restored->sequence);
    }

    public function test_a_fully_returned_order_closes_both_pairs_exactly(): void
    {
        // There is no full-return flag: equality of the integer sums is the signal.
        // The two pairs use clearly different magnitudes (and differ from the
        // `make()` defaults) so a bug that transposes the loyalty-discount pair
        // with the eligible-amount pair — whether field-for-field or pair-for-pair
        // — fails visibly instead of the coincidence masking it.
        $restored = OrderReturnStateChanged::fromArray($this->make([
            'returnedLoyaltyDiscountCents' => 6000,
            'orderLoyaltyDiscountCents' => 6000,
            'returnedEligibleAmountCents' => 15000,
            'orderEligibleAmountCents' => 15000,
        ])->toArray());

        $this->assertSame(6000, $restored->orderLoyaltyDiscountCents);
        $this->assertSame(6000, $restored->returnedLoyaltyDiscountCents);
        $this->assertSame(15000, $restored->orderEligibleAmountCents);
        $this->assertSame(15000, $restored->returnedEligibleAmountCents);

        $this->assertSame($restored->orderLoyaltyDiscountCents, $restored->returnedLoyaltyDiscountCents);
        $this->assertSame($restored->orderEligibleAmountCents, $restored->returnedEligibleAmountCents);
    }
}
