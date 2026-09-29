<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Voucher;

use DateTimeImmutable;
use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Unsequenced read-model snapshot for gifts-api DynamoDB, last-write-wins.
 * NOT a lifecycle-axis event.
 *
 * Dates are DateTimeImmutable in memory. json_encode turns them into
 * `{date, timezone_type, timezone}` so gifts-api parsers keep working.
 *
 * @property null|array{
 *     reservation_code_uuid: string,
 *     customer_uuid: string,
 *     customer_email: string,
 *     order_uuid: string,
 *     product_id: int,
 *     recipient_email?: string,
 *     site: string,
 *     status: string,
 *     title: string,
 *     valid_to: string,
 *     updated_at: DateTimeImmutable|array{date: string, timezone_type: int, timezone: string}
 * } $originalVoucher
 */
final class VoucherUpdated extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public int $id,
        public string $customerUuid,
        public string $customerEmail,
        public string $orderUuid,
        public int $productId,
        public string $reservationCodeUuid,
        public string $status,
        public string $title,
        public string $validTo,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public float $purchasePrice,
        public string $recipientEmail = '',
        public bool $recipientAddedManually = false,
        public ?DateTimeImmutable $recipientAddedAt = null,
        public ?DateTimeImmutable $returnedAt = null,
        public ?DateTimeImmutable $reservedAt = null,
        public ?DateTimeImmutable $usedAt = null,
        public ?array $originalVoucher = null,
        public ?string $reviewState = null,
        public ?string $reviewToken = null,
        public bool $codePending = false,
    ) {}

    public static function routingName(): string
    {
        return 'voucher.updated';
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
