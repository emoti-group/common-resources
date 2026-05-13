<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events\System;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\System\ExternalQueueRestartRequested;
use PHPUnit\Framework\TestCase;

final class ExternalQueueRestartRequestedTest extends TestCase
{
    public function test_round_trip_with_default_reason(): void
    {
        $event = new ExternalQueueRestartRequested();
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = ExternalQueueRestartRequested::fromArray($event->toArray());

        $this->assertSame('', $restored->reason);
    }

    public function test_round_trip_with_custom_reason(): void
    {
        $event = new ExternalQueueRestartRequested(reason: 'test reason');
        $event->setSite(Site::PL);
        $event->setEventId();
        $event->setSendAt();

        $restored = ExternalQueueRestartRequested::fromArray($event->toArray());

        $this->assertSame('test reason', $restored->reason);
    }
}
