<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Order;

use DateTimeImmutable;
use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Unsequenced read-model snapshot for gifts-api DynamoDB, last-write-wins.
 * NOT a lifecycle-axis event (OrderPaid / OrderDeleted / OrderRestored are sequenced).
 * A CMS delete is the same event with a CANCELED overlay on status / statusKey / statusHistory.
 *
 * Dates are DateTimeImmutable in memory. json_encode turns them into
 * `{date, timezone_type, timezone}` so gifts-api parsers keep working.
 *
 * @property array<string, mixed> $accountingNoteSeller
 * @property list<array<string, mixed>> $accessories
 * @property array<string, mixed> $companyData
 * @property array<string, mixed> $delivery
 * @property array<string, mixed> $discount
 * @property list<array<string, mixed>> $exchanges
 * @property list<array<string, mixed>> $products
 * @property list<array<string, mixed>> $promotions
 * @property array{price: float|string, priceBeforeDiscount: float|string, title?: array<string, string>, type?: string, isZero?: bool, isBankTransfer?: bool} $paymentMethod
 * @property list<array{status: string, date: DateTimeImmutable|array{date: string, timezone_type: int, timezone: string}, orderProductId?: int}> $status
 * @property list<array{status: string, date: DateTimeImmutable|array{date: string, timezone_type: int, timezone: string}, orderProductId?: int}> $statusHistory
 * @property null|array{points_used: int, discount_amount: float} $loyalty
 */
final class OrderUpdated extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public string $uuid,
        public int $externalOrderId,
        public array $accountingNoteSeller,
        public array $accessories,
        public array $companyData,
        public DateTimeImmutable $createdAt,
        public string $userUuid,
        public array $delivery,
        public float $deliveryAmount,
        public float $deliveryAmountBeforeDiscount,
        public array $discount,
        public array $exchanges,
        public string $number,
        public bool $invoiceNeeded,
        public array $products,
        public array $promotions,
        public bool $paid,
        public array $paymentMethod,
        public ?DateTimeImmutable $paymentDate,
        public int|float $serviceFee,
        public array $status,
        public string $statusKey,
        public array $statusHistory,
        public float $total,
        public float $totalAccessoriesAmount,
        public float $totalAccessoriesAmountBeforeDiscount,
        public float $totalProductsAmount,
        public float $totalProductsAmountBeforeDiscount,
        public float $totalProductsAmountBeforeRebate,
        public DateTimeImmutable $updatedAt,
        public ?int $eligibleAmountCents = null,
        public ?array $loyalty = null,
    ) {}

    public static function routingName(): string
    {
        return 'order.updated';
    }

    public static function version(): int
    {
        return 1;
    }

    public function resourceId(): int
    {
        return $this->externalOrderId;
    }

    public function resourceUuid(): UuidInterface
    {
        return Uuid::fromString($this->uuid);
    }
}
