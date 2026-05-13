<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use Emoti\CommonResources\Enums\PromotionType;
use PHPUnit\Framework\TestCase;

final class PromotionTypeTest extends TestCase
{
    public function test_priority_returns_correct_order(): void
    {
        $this->assertSame(1, PromotionType::MULTI_DISCOUNT->priority());
        $this->assertSame(2, PromotionType::FREE_GIFT->priority());
        $this->assertSame(3, PromotionType::LIMITED_QUANTITY->priority());
    }

    public function test_is_blocked_by_discount_codes(): void
    {
        $this->assertTrue(PromotionType::MULTI_DISCOUNT->isBlockedByDiscountCodes());
        $this->assertTrue(PromotionType::FREE_GIFT->isBlockedByDiscountCodes());
        $this->assertFalse(PromotionType::LIMITED_QUANTITY->isBlockedByDiscountCodes());
    }

    public function test_mutually_exclusive_types(): void
    {
        $this->assertSame([PromotionType::FREE_GIFT], PromotionType::MULTI_DISCOUNT->mutuallyExclusiveTypes());
        $this->assertSame([PromotionType::MULTI_DISCOUNT], PromotionType::FREE_GIFT->mutuallyExclusiveTypes());
        $this->assertSame([], PromotionType::LIMITED_QUANTITY->mutuallyExclusiveTypes());
    }
}
