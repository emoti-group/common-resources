<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Client;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Queue\Events\System\ExternalQueueRestartRequested;
use Emoti\CommonResources\Support\Config\Config;
use Emoti\CommonResources\Support\Storage\Storage;
use Exception;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
final class RabbitMQSetupper
{
    public function __construct(private readonly RabbitMQClient $client) {}

    /**
     * @return array{string, string}
     * @throws Exception
     */
    public function setup(string $queueName): array
    {
        $routingKeys = $this->getRoutingKeys($queueName);
        [$exchangeName, $declaredQueueName] = $this->declareExchangeAndQueue($queueName);

        $bindingsFile = Storage::path('rabbitmq_bindings_' . $queueName . '.json');
        $previousRoutingKeys = $this->getPreviousBindings($bindingsFile);

        $this->updateQueueBindings($declaredQueueName, $exchangeName, $routingKeys, $previousRoutingKeys);
        $this->updateBindingsFile($bindingsFile, $routingKeys);

        return [$exchangeName, $declaredQueueName];
    }

    private function getRoutingKeys(string $queueName): array
    {
        $routingKeys = collect(Config::get('bindings.' . $queueName, []))
            ->keys()
            ->map(
            /** @param class-string $eventClass */
                fn(string $eventClass) => $eventClass::routingKey(),
            )
            ->prepend(ExternalQueueRestartRequested::routingKey())
            ->toArray();

        return array_unique($routingKeys);
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


}
