<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site as CommonSite;
use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * GIFTSPB-1631: tells the loyalty service that an order's RETURNED AMOUNT CHANGED.
 *
 * WHAT IT IS FOR. A customer earns cashback points on an order, and may have paid part
 * of that order with points. When goods come back, both sides have to be undone in
 * proportion: the points EARNED on the returned goods are taken back, and the points
 * SPENT on them are given back. Returns are recorded in agcore; points live in
 * gifts-api, which until now had no way to hear that a return happened at all — so a
 * customer could return the goods and keep the cashback. This event is that missing
 * message. Nothing here computes or applies anything; it is the wire format.
 *
 * WHY IT CARRIES STATE, NOT A DELTA. Returns get corrected, duplicated and withdrawn.
 * "Take back 35 points" delivered twice takes back 70. So every field describes the
 * order AFTER the change — a cumulative total — and the consumer reconciles TOWARD it.
 * The same message delivered twice changes nothing, which is what makes this safe on a
 * queue at all.
 *
 * WHY FOUR FIGURES AND NOT ONE. The two undos are computed on different bases, so each
 * gets its own numerator/denominator pair — returned, and the whole order — and the
 * consumer needs no memory of earlier messages to use them.
 *
 * WHY A `sequence`. Only so that a late message carrying a SMALLER total cannot drag a
 * consumer's already-correct state backwards.
 *
 * ---
 *
 * The rest is design rationale, for whoever changes this class.
 *
 * It is the RETURN axis — a third, independent order lifecycle axis beside payment
 * (order.paid / order.cancelled) and existence (order.deleted / order.restored). Unlike
 * those two it has NO inverse event, which is why its `sequence` exists for the
 * cumulative reason above rather than to survive an undo.
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
