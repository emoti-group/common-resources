<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\System;

use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

final class ExternalQueueRestartRequested extends AbstractEmotiEvent implements EmotiEventInterface
{
    public function __construct(
        public string $reason = '',
    ) {}

    public static function routingName(): string
    {
        return 'system.restart';
    }

    public static function version(): int
    {
        return 1;
    }

    public function resourceId(): ?int
    {
        return null;
    }

    public function resourceUuid(): ?UuidInterface
    {
        return null;
    }
}
