<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;
use PHPUnit\Framework\TestCase;

final class EventSerializationTest extends TestCase
{
    public function test_to_array_from_array_round_trip_preserves_constructor_fields(): void
    {
        $event = new ProductAddedToUpsellGroup(productId: 42, upsellGroupId: 7);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = ProductAddedToUpsellGroup::fromArray($event->toArray());

        $this->assertSame(42, $restored->productId);
        $this->assertSame(7, $restored->upsellGroupId);
    }

    public function test_to_array_from_array_round_trip_preserves_extra_properties(): void
    {
        $event = new ProductAddedToUpsellGroup(productId: 1, upsellGroupId: 2);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = ProductAddedToUpsellGroup::fromArray($event->toArray());

        $this->assertSame(Site::PL, $restored->site());
        $this->assertEquals($event->eventId(), $restored->eventId());
        $this->assertTrue($event->sendAt()->isSameSecond($restored->sendAt()));
    }

    public function test_to_array_contains_expected_top_level_keys(): void
    {
        $event = new ProductAddedToUpsellGroup(productId: 1, upsellGroupId: 2);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $array = $event->toArray();

        $this->assertArrayHasKey('site', $array);
        $this->assertArrayHasKey('sendAt', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('resourceId', $array);
        $this->assertArrayHasKey('version', $array);
        $this->assertArrayHasKey('eventId', $array);
        $this->assertArrayHasKey('routingKey', $array);
    }

    public function test_resource_id_matches_product_id(): void
    {
        $event = new ProductAddedToUpsellGroup(productId: 99, upsellGroupId: 3);

        $this->assertSame(99, $event->resourceId());
    }
}
