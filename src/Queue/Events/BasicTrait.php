<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

use Emoti\CommonResources\Enums\Site;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

trait BasicTrait
{
    protected Site $site;

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
        return Uuid::uuid7();
    }

    public function toArray(): array
    {
        return [
            'site' => $this->site()->value,
            'routing_key' => $this->routingKey(),
            'event_id' => $this->eventId()->toString(),
            'resource_id' => $this->resourceId(),
            'resource_uuid' => $this->resourceUuid()?->toString(),
            'data' => $this->data(),
            'version' => $this->version(),
        ];
    }
}