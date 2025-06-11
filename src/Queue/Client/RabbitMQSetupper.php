<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Client;

use Emoti\CommonResources\Support\Config\Config;
use Emoti\CommonResources\Support\Storage\Storage;
use Exception;

final class RabbitMQSetupper
{
    public function __construct(private readonly RabbitMQClient $client) {}

    /**
     * @return array{string, string}
     * @throws Exception
     */
    public function setup(): array
    {
        $queueSuffix = Config::get('rabbitmq.external_queue');
        $routingKeys = $this->getRoutingKeys();
        [$exchangeName, $queueName] = $this->declareExchangeAndQueue($queueSuffix);

        $bindingsFile = Storage::path('rabbitmq_bindings.json');
        $previousRoutingKeys = $this->getPreviousBindings($bindingsFile);

        $this->updateQueueBindings($queueName, $exchangeName, $routingKeys, $previousRoutingKeys);
        $this->updateBindingsFile($bindingsFile, $routingKeys);

        return [$exchangeName, $queueName];
    }

    private function getRoutingKeys(): array
    {
        return collect(Config::get('bindings'))
            ->keys()
            ->map(
            /** @param class-string $eventClass */
                fn(string $eventClass) => $eventClass::routingKey(),
            )
            ->toArray();
    }

    private function declareExchangeAndQueue(string $queueSuffix): array
    {
        $exchangeName = $this->client->declareExchange();
        $queueName = $this->client->declareQueue($queueSuffix);

        return [$exchangeName, $queueName];
    }

    private function getPreviousBindings(string $bindingsFile): array
    {
        return file_exists($bindingsFile)
            ? json_decode(file_get_contents($bindingsFile), true)
            : [];
    }

    private function updateQueueBindings(
        string $queueName,
        string $exchangeName,
        array $routingKeys,
        array $previousRoutingKeys,
    ): void {
        $this->client->bindQueueToExchange($queueName, $exchangeName, $routingKeys);

        $removedRoutingKeys = array_diff($previousRoutingKeys, $routingKeys);

        if (!empty($removedRoutingKeys)) {
            $this->client->unbindQueueFromExchange($queueName, $exchangeName, $removedRoutingKeys);
        }
    }

    private function updateBindingsFile(string $bindingsFile, array $routingKeys): void
    {
        file_put_contents($bindingsFile, json_encode($routingKeys));
    }

    private function closeConnections(): void
    {
        $this->client->channel->close();
        $this->client->connection->close();
    }
}
