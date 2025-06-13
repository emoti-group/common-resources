<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Product;

use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

final class ProductRemovedFromUpsellGroup extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        private readonly int $productId,
        private readonly int $upsellGroupId,
    ) {}

    public static function routingName(): string
    {
        return 'product.added_to_upsell_group';
    }

    public static function version(): int
    {
        return 1;
    }

    public function resourceId(): ?int
    {
        return $this->productId;
    }

    public function resourceUuid(): ?UuidInterface
    {
        return null;
    }
}