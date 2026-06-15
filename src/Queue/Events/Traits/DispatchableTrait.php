<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Events\Traits;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Message;
use Emoti\CommonResources\Queue\Publisher\PublisherRegistry;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
trait DispatchableTrait
{
    public function dispatch(Site $site, int $priority = 5): void
    {
        $this->setSite($site);
        $this->setEventId();
        $this->setSendAt();

        // Resolves to RabbitMQPublisher in production; tests can install a
        // FakePublisher via PublisherRegistry::fake() to capture the payload.
        PublisherRegistry::resolve()->publish(
            new Message($this->toArray(), static::class, $priority),
            $this->routingKey(),
        );
    }
}