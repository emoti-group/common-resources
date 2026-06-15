<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderPaid;
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

    public function test_resource_id_returns_id(): void
    {
        $event = new OrderPaid(id: 42, site: Site::PL, isB2b: false, eligibleAmountCents: 0);

        $this->assertSame(42, $event->resourceId());
    }
}
