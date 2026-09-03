<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Voucher;

use DateTimeImmutable;
use DateTimeZone;
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Voucher\VoucherUpdated;
use Emoti\CommonResources\Queue\Message;
use PHPUnit\Framework\TestCase;

final class VoucherUpdatedTest extends TestCase
{
    private function makeEvent(
        int $id = 123,
        ?DateTimeImmutable $createdAt = null,
    ): VoucherUpdated {
        $createdAt ??= new DateTimeImmutable('2026-07-01 10:00:00.000000', new DateTimeZone('UTC'));

        return new VoucherUpdated(
            id: $id,
            customerUuid: 'customer-uuid-1',
            customerEmail: 'customer@example.com',
            orderUuid: 'order-uuid-1',
            productId: 456,
            reservationCodeUuid: 'reservation-uuid-1',
            status: 'unused',
            title: 'Voucher title',
            validTo: '2027-07-01',
            createdAt: $createdAt,
            updatedAt: $createdAt,
            purchasePrice: 179.99,
        );
    }

    public function test_routing_name_is_voucher_updated(): void
    {
        $this->assertSame('voucher.updated', VoucherUpdated::routingName());
    }

    public function test_version_is_one(): void
    {
        $this->assertSame(1, VoucherUpdated::version());
    }

    public function test_resource_id_returns_id(): void
    {
        $this->assertSame(123, $this->makeEvent()->resourceId());
    }

    public function test_resource_uuid_is_null(): void
    {
        $this->assertNull($this->makeEvent()->resourceUuid());
    }

    public function test_constructor_exposes_typed_fields_not_a_bag(): void
    {
        $event = $this->makeEvent();

        $this->assertSame('customer@example.com', $event->customerEmail);
        $this->assertSame(456, $event->productId);
        $this->assertSame('unused', $event->status);
        $this->assertSame('', $event->recipientEmail);
        $this->assertSame(179.99, $event->purchasePrice);
    }

    public function test_message_json_round_trip_serializes_datetime_to_wire_shape(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-01 10:00:00.000000', new DateTimeZone('UTC'));
        $event = $this->makeEvent(createdAt: $createdAt);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $json = (new Message($event->toArray(), VoucherUpdated::class))->toJson();
        $decoded = json_decode($json, true);

        $createdAtWire = $decoded['content']['data']['createdAt'];
        $this->assertIsArray($createdAtWire);
        $this->assertArrayHasKey('date', $createdAtWire);
        $this->assertArrayHasKey('timezone_type', $createdAtWire);
        $this->assertArrayHasKey('timezone', $createdAtWire);
        $this->assertSame('UTC', $createdAtWire['timezone']);
        $this->assertStringStartsWith('2026-07-01 10:00:00', $createdAtWire['date']);

        $restored = VoucherUpdated::fromArray(Message::fromJson($json)->content);

        $this->assertSame(123, $restored->id);
        $this->assertSame('customer@example.com', $restored->customerEmail);
        $this->assertInstanceOf(DateTimeImmutable::class, $restored->createdAt);
        $this->assertSame('UTC', $restored->createdAt->getTimezone()->getName());
        $this->assertStringStartsWith('2026-07-01 10:00:00', $restored->createdAt->format('Y-m-d H:i:s.u'));
    }
}
