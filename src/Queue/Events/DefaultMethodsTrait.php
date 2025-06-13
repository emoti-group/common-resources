<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events;

use Emoti\CommonResources\Enums\Site;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

trait DefaultMethodsTrait
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
}