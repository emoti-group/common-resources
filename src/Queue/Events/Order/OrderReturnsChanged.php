<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site as CommonSite;
use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * GIFTSPB-1631: the RETURN axis — a third, independent order lifecycle axis
 * beside payment (order.paid / order.cancelled) and existence (order.deleted /
 * order.restored). State-carrying, not delta-carrying: every field describes the
 * order AFTER this change, so a consumer reconciles to a target and a duplicate
 * delivery is a no-op by construction.
 *
 * Two independent bases travel and they are NOT interchangeable. The discount
 * pair drives the spend restore (per-line loyalty attribution); the eligible pair
 * drives the earn revoke. Both numerator and denominator of each pair are
 * computed over the same rows in the same call, so neither side can drift
 * against a frozen figure.
 *
 * There is deliberately no full-return flag. Both sides are exact integer sums
 * over the same set, and returned lines are counted by distinct id, so
 * `returned == total` is exact and `returned > total` unreachable. A flag was
 * tried and removed: a line removed by deleteProduct() is archived and can never
 * appear in o_returns, so a flag counting live lines reported a full return while
 * the amounts reported a partial one.
 *
 * Every OPTIONAL field carries a default, because ArrayableTrait::fromArray()
 * leaves a missing non-nullable typed property UNINITIALIZED, which throws on
 * first read. `id` and `site` are required and always sent; `site` additionally
 * travels at envelope level and is restored from there.
 */
final class OrderReturnsChanged extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public int $id,
        public CommonSite $site,
        public bool $isB2b = false,
        /** Σ per-line loyalty attribution of the returned lines, live ∪ archived. */
        public int $returnedLoyaltyDiscountCents = 0,
        /** Σ per-line loyalty attribution of every line, live ∪ archived. */
        public int $orderLoyaltyDiscountCents = 0,
        /** Σ (line price − attributed discount) over returned non-accessory lines. */
        public int $returnedEligibleAmountCents = 0,
        /** The same sum over every non-accessory line. */
        public int $orderEligibleAmountCents = 0,
        public ?string $orderUuid = null,
        /**
         * Per-order RETURN-axis sequence, always positive: agcore refuses to
         * publish when it cannot stamp one, so consumers never see 0.
         */
        public int $sequence = 0,
    ) {}

    public static function routingName(): string
    {
        return 'order.returns_changed';
    }

    public static function version(): int
    {
        return 1;
    }

    public function resourceId(): int
    {
        return $this->id;
    }

    public function resourceUuid(): ?UuidInterface
    {
        return null;
    }
}
