<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Cache;

use Emoti\CommonResources\Queue\Events\AbstractEmotiEvent;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * @var list<string> $tags
 */
final class CloudflareCachePurgeRequested extends AbstractEmotiEvent implements EmotiEventInterface
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public array $tags,
    ) {}

    public static function routingName(): string
    {
        return 'cloudflare.cache_purge_requested';
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
