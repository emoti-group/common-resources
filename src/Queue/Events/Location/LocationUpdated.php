<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Location;

use Emoti\CommonResources\DTO\GeoJsonGeometryDTO;
use Emoti\CommonResources\Enums\Lang;
use Emoti\CommonResources\Enums\LocationType;
use Emoti\CommonResources\Enums\Site as CommonSite;
use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

final class LocationUpdated extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public UuidInterface $id,
        public array $name,
        public LocationType $type,
        public ?int $marketingCategoryId,
        public ?GeoJsonGeometryDTO $geometry,
        public CommonSite $site,
    ) {}

    public static function routingName(): string
    {
        return 'location.updated';
    }

    public static function version(): int
    {
        return 1;
    }

    public function resourceId(): ?int
    {
        return null;
    }

    public function resourceUuid(): ?UuidInterface
    {
        return $this->id;
    }
}
