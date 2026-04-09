<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

enum PromotionType: string
{
    use ArrayableEnumTrait;

    case FREE_GIFT = 'free_gift';
    case MULTI_DISCOUNT = 'multi_discount';
    case LIMITED_QUANTITY = 'limited_quantity';

    public function priority(): int
    {
        return match ($this) {
            self::MULTI_DISCOUNT => 1,
            self::FREE_GIFT => 2,
            self::LIMITED_QUANTITY => 3,
        };
    }

    public function isBlockedByDiscountCodes(): bool
    {
        return match ($this) {
            self::MULTI_DISCOUNT, self::FREE_GIFT => true,
            self::LIMITED_QUANTITY => false,
        };
    }

    /** @return list<self> */
    public function mutuallyExclusiveTypes(): array
    {
        return match ($this) {
            self::MULTI_DISCOUNT => [self::FREE_GIFT],
            self::FREE_GIFT => [self::MULTI_DISCOUNT],
            self::LIMITED_QUANTITY => [],
        };
    }
}
