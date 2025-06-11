<?php

namespace Emoti\CommonResources\Queue\Publisher;

use Emoti\CommonResources\Queue\Message;

interface PublisherInterface
{
    public function publish(Message $message, string $routingKey): void;
}