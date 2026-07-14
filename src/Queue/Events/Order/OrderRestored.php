<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Order;

use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

final class OrderRestored extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public int $id,
        public bool $isB2b = false,
        public ?string $orderUuid = null,
        /**
         * Payment status at restore time — an unsequenced snapshot (guarded only
         * by the existence-axis sequence; carries no payment sequence). Treat it
         * as intent, applied idempotently and reconciled against the real
         * OrderPaid/OrderCancelled events; a newer payment-axis event wins.
         * See "Event sequencing" in docs/message-broker.md.
         */
        public bool $isPaid = false,
        /**
         * Per-order, per-axis (existence) sequence: positive; 0 = unsequenced.
         * See "Event sequencing" in docs/message-broker.md.
         */
        public int $sequence = 0,
    ) {}

    public static function routingName(): string
    {
        return 'order.restored';
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
