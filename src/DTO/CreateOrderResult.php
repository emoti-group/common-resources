<?php

declare(strict_types=1);

namespace Emoti\CommonResources\DTO;

/**
 * Shared result of a createOrder operation, used by agcore and gifts-api.
 */
final readonly class CreateOrderResult
{
    /**
     * @param list<string> $reservationCodeUuids
     */
    public function __construct(
        public int $id,
        public int $number,
        public ?string $customerType,
        public ?string $userUuid,
        public array $reservationCodeUuids = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $reservationCodeUuids = [];

        foreach ($data['reservation_code_uuids'] ?? [] as $uuid) {
            if (is_string($uuid) && $uuid !== '') {
                $reservationCodeUuids[] = $uuid;
            }
        }

        return new self(
            id: (int) $data['id'],
            number: (int) $data['number'],
            customerType: $data['customer_type'] ?? null,
            userUuid: $data['user_uuid'] ?? null,
            reservationCodeUuids: $reservationCodeUuids,
        );
    }

    /**
     * @return array{id: int, number: int, customer_type: ?string, user_uuid: ?string, reservation_code_uuids: list<string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_type' => $this->customerType,
            'user_uuid' => $this->userUuid,
            'reservation_code_uuids' => $this->reservationCodeUuids,
        ];
    }
}
