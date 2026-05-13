<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Product;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Product\ProductUpdated;
use PHPUnit\Framework\TestCase;

final class ProductUpdatedTest extends TestCase
{
    private function makeProductUpdated(): ProductUpdated
    {
        return new ProductUpdated(
            id: 123,
            parentId: null,
            status: 'active',
            cmsName: 'test-product',
            type: 'experience',
            generalCategoryId: 1,
            generalCategoryNames: [],
            generalCategoryCmsName: 'category',
            parentGeneralCategoryId: null,
            parentGeneralCategoryNames: null,
            parentGeneralCategoryCmsName: null,
            marketingCategoryIds: [],
            supplierId: 456,
            supplier: 'Test Supplier',
            supplier_path: 'test-supplier',
            upsellDimensionType: null,
            upsellDimensionValue: null,
            titles: [],
            descriptions: [],
            tags: [],
            rating: null,
            pictures: [],
            costprice: 10.0,
            priceBeforeDiscount: 20.0,
            priceAfterDiscount: 15.0,
            discountPercent: 25,
            participantsFrom: 1,
            participantsTo: 10,
            url: 'https://example.com/product',
            categoriesIdsWhereHighlighted: [],
            categoriesIdsWhereRecommended: [],
            isPackage: false,
            giftcardParentId: null,
            numberOfExperiences: null,
            activityLevel: null,
            romanticismLevel: null,
            adrenalineLevel: null,
            ageLevel: null,
            locations: [],
            fittingLocations: [],
            locationRadius: 0.0,
            isOnline: false,
            isDelivery: false,
            qs: 0.5,
        );
    }

    public function test_round_trip_preserves_required_fields(): void
    {
        $event = $this->makeProductUpdated();
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = ProductUpdated::fromArray($event->toArray());

        $this->assertSame(123, $restored->id);
        $this->assertSame('active', $restored->status);
        $this->assertSame(456, $restored->supplierId);
    }

    public function test_round_trip_preserves_optional_fields_with_defaults(): void
    {
        $event = $this->makeProductUpdated();
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = ProductUpdated::fromArray($event->toArray());

        $this->assertSame([], $restored->packageChildrenIds);
        $this->assertFalse($restored->isGlobal);
        $this->assertSame(0.0, $restored->lowestPrice30Days);
    }

    public function test_resource_id_returns_id(): void
    {
        $event = $this->makeProductUpdated();

        $this->assertSame(123, $event->resourceId());
    }

    public function test_resource_uuid_returns_null(): void
    {
        $event = $this->makeProductUpdated();

        $this->assertNull($event->resourceUuid());
    }
}
