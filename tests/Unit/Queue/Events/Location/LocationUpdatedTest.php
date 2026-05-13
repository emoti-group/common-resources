<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Location;

use Emoti\CommonResources\Enums\LocationType;
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Location\LocationUpdated;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class LocationUpdatedTest extends TestCase
{
    private function makeSerializedArray(string $idString): array
    {
        return [
            'site'    => 'pl',
            'sendAt'  => '2024-01-01T00:00:00+00:00',
            'eventId' => Uuid::uuid4()->toString(),
            'data'    => [
                'id'                  => $idString,
                'name'                => ['en' => 'Test City'],
                'type'                => 'city',
                'marketingCategoryId' => null,
                'geometry'            => null,
                'site'                => 'pl',
            ],
        ];
    }

    public function test_round_trip_preserves_uuid_id_field(): void
    {
        $id = Uuid::uuid4();
        $restored = LocationUpdated::fromArray($this->makeSerializedArray($id->toString()));

        $this->assertInstanceOf(UuidInterface::class, $restored->id);
        $this->assertSame($id->toString(), $restored->id->toString());
    }

    public function test_round_trip_preserves_location_type_enum(): void
    {
        $id = Uuid::uuid4();
        $restored = LocationUpdated::fromArray($this->makeSerializedArray($id->toString()));

        $this->assertSame(LocationType::CITY, $restored->type);
    }

    public function test_round_trip_with_null_geometry(): void
    {
        $id = Uuid::uuid4();
        $restored = LocationUpdated::fromArray($this->makeSerializedArray($id->toString()));

        $this->assertNull($restored->geometry);
    }

    public function test_resource_uuid_returns_id(): void
    {
        $id = Uuid::uuid4();
        $event = new LocationUpdated(
            id: $id,
            name: ['en' => 'Test City'],
            type: LocationType::CITY,
            marketingCategoryId: null,
            geometry: null,
            site: Site::PL,
        );

        $this->assertSame($event->id, $event->resourceUuid());
    }

    public function test_resource_id_returns_null(): void
    {
        $id = Uuid::uuid4();
        $event = new LocationUpdated(
            id: $id,
            name: ['en' => 'Test City'],
            type: LocationType::CITY,
            marketingCategoryId: null,
            geometry: null,
            site: Site::PL,
        );

        $this->assertNull($event->resourceId());
    }
}
