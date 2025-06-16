<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Traits;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Message;
use Emoti\CommonResources\Queue\Publisher\RabbitMQPublisher;

trait DispatchableTrait
{
    public function dispatch(Site $site): void
    {
        $publisher = new RabbitMQPublisher();

        $this->setSite($site);
        $this->setEventId();

        $publisher->publish(
            new Message($this->toArray(), static::class),
            $this->routingKey(),
        );
    }
}