<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Order;

use DateTimeImmutable;
use DateTimeZone;
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderUpdated;
use Emoti\CommonResources\Queue\Message;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class OrderUpdatedTest extends TestCase
{
    private const VALID_UUID = '11111111-2222-3333-4444-555555555555';

    private function makeEvent(
        int $externalOrderId = 42,
        string $uuid = self::VALID_UUID,
        ?DateTimeImmutable $createdAt = null,
    ): OrderUpdated {
        $createdAt ??= new DateTimeImmutable('2026-07-01 10:00:00.000000', new DateTimeZone('UTC'));

        return new OrderUpdated(
            uuid: $uuid,
            externalOrderId: $externalOrderId,
            accountingNoteSeller: [],
            accessories: [],
            companyData: [],
            createdAt: $createdAt,
            userUuid: 'user-uuid',
            delivery: ['price' => 0.0, 'priceBeforeDiscount' => 0.0],
            deliveryAmount: 0.0,
            deliveryAmountBeforeDiscount: 0.0,
            discount: [],
            exchanges: [],
            number: '202601010001',
            invoiceNeeded: false,
            products: [],
            promotions: [],
            paid: true,
            paymentMethod: ['price' => 0.0, 'priceBeforeDiscount' => 0.0],
            paymentDate: null,
            serviceFee: 0,
            status: [['status' => 'paid', 'date' => $createdAt]],
            statusKey: 'paid',
            statusHistory: [['status' => 'paid', 'date' => $createdAt]],
            total: 149.99,
            totalAccessoriesAmount: 0.0,
            totalAccessoriesAmountBeforeDiscount: 0.0,
            totalProductsAmount: 149.99,
            totalProductsAmountBeforeDiscount: 149.99,
            totalProductsAmountBeforeRebate: 149.99,
            updatedAt: $createdAt,
        );
    }

    public function test_routing_name_is_order_updated(): void
    {
        $this->assertSame('order.updated', OrderUpdated::routingName());
    }

    public function test_version_is_one(): void
    {
        $this->assertSame(1, OrderUpdated::version());
    }

    public function test_resource_id_returns_external_order_id(): void
    {
        $this->assertSame(42, $this->makeEvent()->resourceId());
    }

    public function test_resource_uuid_parses_valid_uuid(): void
    {
        $event = $this->makeEvent();

        $this->assertNotNull($event->resourceUuid());
        $this->assertTrue(Uuid::fromString(self::VALID_UUID)->equals($event->resourceUuid()));
    }

    public function test_resource_uuid_is_null_for_invalid_uuid(): void
    {
        $this->assertNull($this->makeEvent(uuid: 'not-a-uuid')->resourceUuid());
    }

    public function test_constructor_exposes_typed_fields_not_a_bag(): void
    {
        $event = $this->makeEvent();

        $this->assertSame(self::VALID_UUID, $event->uuid);
        $this->assertSame(42, $event->externalOrderId);
        $this->assertTrue($event->paid);
        $this->assertSame('paid', $event->statusKey);
        $this->assertSame(149.99, $event->total);
    }

    public function test_message_json_round_trip_serializes_datetime_to_wire_shape(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-01 10:00:00.000000', new DateTimeZone('UTC'));
        $event = $this->makeEvent(externalOrderId: 99, createdAt: $createdAt);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $json = (new Message($event->toArray(), OrderUpdated::class))->toJson();
        $decoded = json_decode($json, true);

        $createdAtWire = $decoded['content']['data']['createdAt'];
        $this->assertIsArray($createdAtWire);
        $this->assertArrayHasKey('date', $createdAtWire);
        $this->assertArrayHasKey('timezone_type', $createdAtWire);
        $this->assertArrayHasKey('timezone', $createdAtWire);
        $this->assertSame('UTC', $createdAtWire['timezone']);
        $this->assertStringStartsWith('2026-07-01 10:00:00', $createdAtWire['date']);

        $restored = OrderUpdated::fromArray(Message::fromJson($json)->content);

        $this->assertSame(99, $restored->externalOrderId);
        $this->assertSame(self::VALID_UUID, $restored->uuid);
        $this->assertInstanceOf(DateTimeImmutable::class, $restored->createdAt);
        $this->assertSame('UTC', $restored->createdAt->getTimezone()->getName());
        $this->assertStringStartsWith('2026-07-01 10:00:00', $restored->createdAt->format('Y-m-d H:i:s.u'));
        $this->assertTrue($restored->paid);
    }
}
