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
         * agcore's per-order, per-axis (existence) monotonically increasing counter.
         * 0 = unknown/unsequenced (backward-compatible default); consumers should
         * treat 0 as "always apply", i.e. no staleness information available.
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
