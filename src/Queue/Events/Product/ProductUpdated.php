<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Product;

use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * @var list<array{lang: string, value: string}> $generalCategoryNames
 * @var null|list<array{lang: string, value: string}> $parentGeneralCategoryNames
 * @var list<array{lang: string, value: string}> $titles
 * @var list<array{lang: string, value: string}> $descriptions
 * @var list<string> $tags
 * @var null|array{average: float, reviewsCount: int, key: string} $rating
 * @var list<string> $pictures
 * @var null|list<array{lat: float, long: float}> $locations
 */
final class ProductUpdated extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public int $id,
        public string $status,
        public string $cmsName,
        public string $type,
        public int $generalCategoryId,
        public array $generalCategoryNames,
        public ?int $parentGeneralCategoryId,
        public ?array $parentGeneralCategoryNames,
        public ?string $upsellDimensionType,
        public ?string $upsellDimensionValue,
        public array $titles,
        public array $descriptions,
        public array $tags,
        public ?array $rating,
        public array $pictures,
        public float $priceBeforeDiscount,
        public float $priceAfterDiscount,
        public int $discountPercent,
        public int $participantsFrom,
        public int $participantsTo,
        public string $url,
        public array $categoriesIdsWhereHighlighted,
        public array $categoriesIdsWhereRecommended,
        public bool $isPackage,
        public ?int $giftcardParentId,
        public ?int $numberOfExperiences,
        public ?string $activityLevel,
        public ?string $romanticismLevel,
        public ?string $adrenalineLevel,
        public ?string $ageLevel,
        public array $locations,
        public float $locationRadius,
        public bool $isOnline,
        public bool $isDelivery,
        public float $qs,
    ) {}

    public static function routingName(): string
    {
        return 'product.updated';
    }

    public static function version(): int
    {
        return 1;
    }

    public function resourceId(): ?int
    {
        return $this->id;
    }

    public function resourceUuid(): ?UuidInterface
    {
        return null;
    }
}