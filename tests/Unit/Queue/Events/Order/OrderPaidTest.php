<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderPaid;
use PHPUnit\Framework\TestCase;

final class OrderPaidTest extends TestCase
{
    public function test_round_trip_preserves_id_and_site_constructor_field(): void
    {
        $event = new OrderPaid(id: 99, site: Site::PL);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = OrderPaid::fromArray($event->toArray());

        $this->assertSame(99, $restored->id);
        $this->assertSame(Site::PL, $restored->site);
    }

    public function test_extra_properties_site_is_independent(): void
    {
        $event = new OrderPaid(id: 1, site: Site::PL);
        $event->setSite(Site::EE);

        $this->assertSame(Site::EE, $event->site());
    }

    public function test_resource_id_returns_id(): void
    {
        $event = new OrderPaid(id: 42, site: Site::PL);

        $this->assertSame(42, $event->resourceId());
    }
}
