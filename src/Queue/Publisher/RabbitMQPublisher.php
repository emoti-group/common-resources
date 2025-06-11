<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Publisher;

use Emoti\CommonResources\Queue\Client\RabbitMQClient;
use Emoti\CommonResources\Queue\Message;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMQPublisher implements PublisherInterface
{
    private readonly RabbitMQClient $client;

    public function __construct()
    {
        $this->client = RabbitMQClient::getInstance();
    }

    public function publish(Message $message, string $routingKey): void
    {
        $exchangeName = $this->client->declareExchange();

        $this->client->channel->basic_publish(
            msg: new AMQPMessage($message->toJson()),
            exchange: $exchangeName,
            routing_key: $routingKey,
        );
    }
}