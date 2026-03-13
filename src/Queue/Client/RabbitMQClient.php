<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Client;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Support\Config\Config;
use Emoti\CommonResources\Support\SingletonTrait;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
final class RabbitMQClient
{
    use SingletonTrait;

    public AbstractConnection $connection;
    public AMQPChannel $channel;

    private function __construct()
    {
        $this->connect();
    }

    public function reconnect(): void
    {
        try {
            if ($this->channel->is_open()) {
                $this->channel->close();
            }
        } catch (Throwable) {
        }

        try {
            if ($this->connection->isConnected()) {
                $this->connection->close();
            }
        } catch (Throwable) {
        }

        $this->connect();
    }

    public function declareQueue(string $queueSuffix): string
    {
        $this->ensureConnected();

        $queueName = $this->buildQueueName($queueSuffix);

        $this->channel->queue_declare(
            queue: $queueName,
            durable: true,
            auto_delete: false,
            arguments: new AMQPTable([
                'x-dead-letter-exchange' => '',
                'x-dead-letter-routing-key' => $this->buildDeadLetterQueueName($queueSuffix),
            ]),
        );

        $this->channel->queue_declare(
            queue: $this->buildDeadLetterQueueName($queueSuffix),
            durable: true,
            auto_delete: false,
        );

        return $queueName;
    }

    public function declareExchange(): string
    {
        $this->ensureConnected();

        $exchangeName = $this->buildExchangeName();
        $this->channel->exchange_declare(
            exchange: $exchangeName,
            type: 'topic',
            durable: true,
            auto_delete: false,
        );

        return $exchangeName;
    }

    public function bindQueueToExchange(string $queueName, string $exchangeName, array $routingKeys): void
    {
        $this->ensureConnected();

        foreach ($routingKeys as $routingKey) {
            $this->channel->queue_bind($queueName, $exchangeName, $routingKey);
        }
    }

    public function unbindQueueFromExchange(string $queueName, string $exchangeName, array $routingKeys): void
    {
        $this->ensureConnected();

        foreach ($routingKeys as $routingKey) {
            $this->channel->queue_unbind($queueName, $exchangeName, $routingKey);
        }
    }

    private function connect(): void
    {
        $this->connection = RabbitMqConnectionFactory::create();
        $this->channel = $this->connection->channel();
    }

    private function ensureConnected(): void
    {
        if (!$this->connection->isConnected() || !$this->channel->is_open()) {
            $this->reconnect();
        }
    }

    private function buildQueueName(string $queueSuffix): string
    {
        $env = Config::get('env');
        $projectName = Config::get('project_name');

        return sprintf('%s.%s.%s', $env, $projectName, $queueSuffix);
    }

    private function buildDeadLetterQueueName(string $queueSuffix): string
    {
        return self::buildQueueName($queueSuffix) . '_dlq';
    }

    private function buildExchangeName(): string
    {
        $env = Config::get('env');
        $exchangeSuffix = Config::get('rabbitmq.exchange');

        return sprintf('%s.%s', $env, $exchangeSuffix);
    }
}