<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\Cache;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Cache\CloudflareCachePurgeRequested;
use PHPUnit\Framework\TestCase;

final class CloudflareCachePurgeRequestedTest extends TestCase
{
    public function test_round_trip_preserves_tags_array(): void
    {
        $event = new CloudflareCachePurgeRequested(tags: ['tag1', 'tag2']);
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = CloudflareCachePurgeRequested::fromArray($event->toArray());

        $this->assertSame(['tag1', 'tag2'], $restored->tags);
    }

    public function test_resource_id_and_uuid_return_null(): void
    {
        $event = new CloudflareCachePurgeRequested(tags: []);

        $this->assertNull($event->resourceId());
        $this->assertNull($event->resourceUuid());
    }
}
