<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Enums\Site;
use Ramsey\Uuid\UuidInterface;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
interface EmotiEventInterface
{
    /** Concrete event class **/
    public static function routingName(): string;

    public static function version(): int;

    public function resourceId(): ?int;

    public function resourceUuid(): ?UuidInterface;

    /** ExtraPropertiesTrait **/
    public static function routingKey(): string;

    public function site(): Site;

    public function eventId(): UuidInterface;

    /** ArrayableTrait **/
    public function data(): array;

    public static function fromArray(array $data): static;

    public function toArray(): array;
}