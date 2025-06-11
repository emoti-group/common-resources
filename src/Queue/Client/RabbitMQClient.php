<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Client;

use Emoti\CommonResources\Support\Config\Config;
use Emoti\CommonResources\Support\SingletonTrait;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMQClient
{
    use SingletonTrait;

    public AbstractConnection $connection;
    public AMQPChannel $channel;

    /**
     * @throws Exception
     */
    private function __construct()
    {
        try {
            $this->connection = RabbitMqConnectionFactory::create();
            $this->channel = $this->connection->channel();
        } catch (Exception $e) {
            throw new Exception('Failed to connect to RabbitMQ:' . $e->getMessage());
        }
    }

    public function declareQueue(string $queueSuffix): string
    {
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
        foreach ($routingKeys as $routingKey) {
            $this->channel->queue_bind($queueName, $exchangeName, $routingKey);
        }
    }

    public function unbindQueueFromExchange(string $queueName, string $exchangeName, array $routingKeys): void
    {
        foreach ($routingKeys as $routingKey) {
            $this->channel->queue_unbind($queueName, $exchangeName, $routingKey);
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