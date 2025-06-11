<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

use Emoti\CommonResources\Enums\Site;
use Ramsey\Uuid\UuidInterface;

interface EmotiEventInterface
{
    /** Serializable from concrete event **/
    public static function routingName(): string;

    public static function version(): int;

    public function resourceId(): ?int;

    public function resourceUuid(): ?UuidInterface;

    public function data(): array;

    /** Serializable from abstract event **/
    public static function routingKey(): string;

    public function site(): Site;

    public function eventId(): UuidInterface;


    /** Non-serializable **/
    public static function fromArray(array $data): static;

    public function toArray(): array;
}