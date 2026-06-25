<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Order;

use Emoti\CommonResources\Enums\Site as CommonSite;
use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

final class OrderPaid extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public int $id,
        public CommonSite $site,
        public bool $isB2b = false,
        public int $eligibleAmountCents = 0,
        public ?string $orderUuid = null,
    ) {}

    public static function routingName(): string
    {
        return 'order.paid';
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
