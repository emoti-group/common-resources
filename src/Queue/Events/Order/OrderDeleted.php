<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Order;

use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

final class OrderDeleted extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public int $id,
        public bool $isB2b = false,
        public ?string $orderUuid = null,
        /**
         * Per-order, per-axis (existence) sequence: positive; 0 = unsequenced.
         * See "Event sequencing" in docs/message-broker.md.
         */
        public int $sequence = 0,
    ) {}

    public static function routingName(): string
    {
        return 'order.deleted';
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
