<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Traits;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Enums\Site;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
trait ExtraPropertiesTrait
{
    protected Site $site;
    protected UuidInterface $eventId;

    public static function routingKey(): string
    {
        return static::routingName() . '.v' . static::version();
    }

    public function site(): Site
    {
        return $this->site;
    }

    public function setSite(Site $site): void
    {
        $this->site = $site;
    }

    public function eventId(): UuidInterface
    {
        return $this->eventId;
    }

    public function setEventId(): void
    {
        $this->eventId = Uuid::uuid7();
    }
}