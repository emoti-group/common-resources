<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events;

use Emoti\CommonResources\Enums\LocationType;
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Location\LocationUpdated;
use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;
use Emoti\CommonResources\Queue\Events\System\ExternalQueueRestartRequested;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ArrayableTraitTest extends TestCase
{
    public function test_from_array_uses_constructor_default_when_field_is_absent(): void
    {
        $event = ExternalQueueRestartRequested::fromArray([]);

        $this->assertSame('', $event->reason);
    }

    public function test_from_array_deserializes_backed_enum_field(): void
    {
        $id = Uuid::uuid4();
        $array = [
            'site'   => 'pl',
            'sendAt' => '2024-01-01T00:00:00+00:00',
            'eventId' => Uuid::uuid4()->toString(),
            'data'   => [
                'id'                  => $id->toString(),
                'name'                => ['en' => 'Test City'],
                'type'                => 'city',
                'marketingCategoryId' => null,
                'geometry'            => null,
                'site'                => 'pl',
            ],
        ];

        $restored = LocationUpdated::fromArray($array);

        $this->assertSame(LocationType::CITY, $restored->type);
    }

    public function test_from_array_deserializes_uuid_field(): void
    {
        $id = Uuid::uuid4();
        $array = [
            'site'   => 'pl',
            'sendAt' => '2024-01-01T00:00:00+00:00',
            'eventId' => Uuid::uuid4()->toString(),
            'data'   => [
                'id'                  => $id->toString(),
                'name'                => ['en' => 'Test City'],
                'type'                => 'city',
                'marketingCategoryId' => null,
                'geometry'            => null,
                'site'                => 'pl',
            ],
        ];

        $restored = LocationUpdated::fromArray($array);

        $this->assertInstanceOf(UuidInterface::class, $restored->id);
        $this->assertSame($id->toString(), $restored->id->toString());
    }

    public function test_from_array_handles_nullable_object_as_null(): void
    {
        $id = Uuid::uuid4();
        $array = [
            'site'   => 'pl',
            'sendAt' => '2024-01-01T00:00:00+00:00',
            'eventId' => Uuid::uuid4()->toString(),
            'data'   => [
                'id'                  => $id->toString(),
                'name'                => ['en' => 'Test City'],
                'type'                => 'city',
                'marketingCategoryId' => null,
                'geometry'            => null,
                'site'                => 'pl',
            ],
        ];

        $restored = LocationUpdated::fromArray($array);

        $this->assertNull($restored->geometry);
    }

    public function test_data_returns_only_constructor_parameters(): void
    {
        $event = new ProductAddedToUpsellGroup(productId: 1, upsellGroupId: 2);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $keys = array_keys($event->data());

        $this->assertSame(['productId', 'upsellGroupId'], $keys);
        $this->assertNotContains('site', $keys);
        $this->assertNotContains('eventId', $keys);
        $this->assertNotContains('sendAt', $keys);
    }
}
