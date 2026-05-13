<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events;

use Carbon\CarbonImmutable;
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

final class ExtraPropertiesTraitTest extends TestCase
{
    private function makeEvent(): ProductAddedToUpsellGroup
    {
        return new ProductAddedToUpsellGroup(productId: 1, upsellGroupId: 2);
    }

    public function test_set_event_id_generates_a_valid_uuid(): void
    {
        $event = $this->makeEvent();
        $event->setEventId();

        $this->assertInstanceOf(UuidInterface::class, $event->eventId());
    }

    public function test_set_event_id_generates_unique_ids_on_each_call(): void
    {
        $event1 = $this->makeEvent();
        $event1->setEventId();

        $event2 = $this->makeEvent();
        $event2->setEventId();

        $this->assertNotSame($event1->eventId()->toString(), $event2->eventId()->toString());
    }

    public function test_set_send_at_sets_a_carbon_immutable_close_to_now(): void
    {
        $before = CarbonImmutable::now();
        $event = $this->makeEvent();
        $event->setSendAt();
        $after = CarbonImmutable::now();

        $this->assertInstanceOf(CarbonImmutable::class, $event->sendAt());
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $event->sendAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp() + 2, $event->sendAt()->getTimestamp());
    }

    public function test_set_site_is_returned_by_site(): void
    {
        $event = $this->makeEvent();
        $event->setSite(Site::PL);

        $this->assertSame(Site::PL, $event->site());
    }
}
