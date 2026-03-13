<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Publisher;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Queue\Client\RabbitMQClient;
use Emoti\CommonResources\Queue\Message;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Message\AMQPMessage;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
final class RabbitMQPublisher implements PublisherInterface
{
    private readonly RabbitMQClient $client;

    public function __construct()
    {
        $this->client = RabbitMQClient::getInstance();
    }

    public function publish(Message $message, string $routingKey): void
    {
        try {
            $this->doPublish($message, $routingKey);
        } catch (AMQPConnectionClosedException | AMQPHeartbeatMissedException) {
            $this->client->reconnect();
            $this->doPublish($message, $routingKey);
        }
    }

    private function doPublish(Message $message, string $routingKey): void
    {
        $exchangeName = $this->client->declareExchange();

        $this->client->channel->basic_publish(
            msg: new AMQPMessage($message->toJson()),
            exchange: $exchangeName,
            routing_key: $routingKey,
        );
    }
}