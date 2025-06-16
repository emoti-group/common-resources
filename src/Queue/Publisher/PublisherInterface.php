<?php

namespace Emoti\CommonResources\Queue\Publisher;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Queue\Message;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
interface PublisherInterface
{
    public function publish(Message $message, string $routingKey): void;
}