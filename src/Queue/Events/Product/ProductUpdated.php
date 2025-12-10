<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Product;

use Emoti\CommonResources\Enums\Lang;
use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * @property list<array{lang: string, value: string}> $generalCategoryNames
 * @property null|list<array{lang: string, value: string}> $parentGeneralCategoryNames
 * @property list<array{lang: string, value: string}> $titles
 * @property list<array{lang: string, value: string}> $descriptions
 * @property list<string> $tags
 * @property null|array{average: float, reviewsCount: int, key: string} $rating
 * @property list<string> $pictures
 * @property null|list<array{lat: float, long: float, city: string}> $locations
 * @property null|list<array{id: string, name: array<Lang, string>, type: string}> $fittingLocations
 * @property list<int> $packageChildrenIds Ids of products that belong to this product. Empty when isPackage property is false.
 * @property list<string> $cacheTagsToInvalidate Cache tags of OLD entities that were attached to the product, but they are not anymore. Example: locations that were removed from the product.
 * @property array<Lang, string> $urlsPerLang
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
        public string $generalCategoryCmsName,
        public ?int $parentGeneralCategoryId,
        public ?array $parentGeneralCategoryNames,
        public ?string $parentGeneralCategoryCmsName,
        public array $marketingCategoryIds,
        public int $supplierId,
        public string $supplier,
        public string $supplier_path,
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
        public array $fittingLocations,
        public float $locationRadius,
        public bool $isOnline,
        public bool $isDelivery,
        public float $qs,
        public array $packageChildrenIds = [],
        public bool $isGlobal = false, // remove default value after migration period
        public array $cacheTagsToInvalidate = [],
        public array $urlsPerLang = [],
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