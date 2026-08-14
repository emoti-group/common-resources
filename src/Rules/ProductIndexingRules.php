<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Rules;

use Emoti\CommonResources\Enums\ProductStatus;
use Emoti\CommonResources\Enums\Site;
use Illuminate\Support\Collection;

class ProductIndexingRules
{
    public static function indexingExclusionReason(?Site $site, int $productId, ProductStatus $productStatus, float $priceAfterDiscount, Collection $titles): ?string
    {
        $excludedStatuses = [ProductStatus::DRAFT, ProductStatus::NO_LONGER_PROVIDED, ProductStatus::DELETED];

        if (in_array($productStatus, $excludedStatuses, true)) {
            return sprintf('product status excludes indexing: %s', $productStatus->name);
        }

        $priceReason = self::priceIndexingExclusionReason($site, $productId, $productStatus, $priceAfterDiscount);
        if ($priceReason !== null) {
            return $priceReason;
        }

        if ($titles->isEmpty()) {
            return 'no product titles';
        }

        if (collect($titles->toArray())->filter(fn($title) => !empty($title['value']))->isEmpty()) {
            return 'all product title values empty';
        }

        return null;
    }

    private static function priceIndexingExclusionReason(?Site $site, int $productId, ProductStatus $productStatus, float $priceAfterDiscount): ?string
    {
        if ($priceAfterDiscount > 0) {
            return null;
        }

        if ($priceAfterDiscount < 0) {
            return sprintf('negative price_after_discount (%s)', $priceAfterDiscount);
        }

        if ($productStatus === ProductStatus::NOT_SHOWN_IN_LISTS) {
            return null;
        }

        if ($site === null) {
            return 'zero priceAfterDiscount requires site context for allowlist eligibility';
        }

        return sprintf(
            'zero priceAfterDiscount but product ID %d is not in zero-price allowlist for site %s',
            $productId,
            $site->value,
        );
    }
}
