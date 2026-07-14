<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site as CommonSite;
use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

final class OrderRestored extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public int $id,
        public CommonSite $site,
        public bool $isB2b = false,
        public ?string $orderUuid = null,
        /**
         * Payment status at restore time; consumers re-apply payment-axis
         * effects only if true.
         */
        public bool $isPaid = false,
        /**
         * agcore's per-order, per-axis (existence) monotonically increasing counter.
         * 0 = unknown/unsequenced (backward-compatible default); consumers should
         * treat 0 as "always apply", i.e. no staleness information available.
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
