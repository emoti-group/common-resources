<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Consumer;

use Closure;
use Emoti\CommonResources\Queue\Client\RabbitMQClient;
use Emoti\CommonResources\Queue\Client\RabbitMQSetupper;
use Emoti\CommonResources\Queue\EmotiListenerInterface;
use Emoti\CommonResources\Queue\Events\EmotiEventInterface;
use Emoti\CommonResources\Queue\Events\System\ExternalQueueRestartRequested;
use Emoti\CommonResources\Queue\Message;
use Emoti\CommonResources\Support\Config\Config;
use Exception;
use Illuminate\Support\Facades\App;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;


final class RabbitMQConsumer implements ConsumerInterface
{
    private readonly RabbitMQClient $client;

    public function __construct()
    {
        $this->client = RabbitMQClient::getInstance();

        // Handle consumer shutdown
        register_shutdown_function(function () {
            $this->client->channel->close();
            $this->client->connection->close();
        });
    }

    /**
     * @param Closure(Exception): void $captureException
     * @throws Exception
     */
    public function consume(Closure $captureException): void
    {
        try {
            [$exchangeName, $queueName] = (new RabbitMQSetupper($this->client))->setup();
            $this->startQueueConsumer($queueName, $captureException);
            $this->client->channel->consume();
        } catch (Throwable $e) {
            $captureException($e);
            $this->client->channel->close();
            $this->client->connection->close();
            exit;
        }
    }

    /**
     * @param Closure(Exception): void $captureException
     */
    private function startQueueConsumer(string $queueName, Closure $captureException): void
    {
        $callback = function (AMQPMessage $AMQPMessage) use ($captureException) {
            try {
                $event = $this->processTheMessage($AMQPMessage);
                $AMQPMessage->ack();

                if ($event instanceof ExternalQueueRestartRequested) {
                    exit;
                }
            } catch (Exception $e) {
                $AMQPMessage->nack();
                $captureException($e);
            }
        };

        $this->client->channel->basic_qos(prefetch_size: 0, prefetch_count: 10, a_global: false);

        $this->client->channel->basic_consume(
            queue: $queueName,
            consumer_tag: $this->getConsumerTag(),
            callback: $callback,
        );
    }

    private function processTheMessage(AMQPMessage $AMQPMessage): EmotiEventInterface
    {
        $message = Message::fromJson($AMQPMessage->getBody());

        /** @var EmotiEventInterface $event */
        $event = $message->class::fromArray($message->content);

        $listener = Config::get('bindings')[$event::class] ?? null;

        if ($listener) {
            /** @var EmotiListenerInterface $listenerInstance */
            $listenerInstance = App::getFacadeRoot() ? App::make($listener) : new $listener();
            $this->handleWithRetry($listenerInstance, $event);
        }

        return $event;
    }

    /**
     * @throws Exception
     */
    private function handleWithRetry(EmotiListenerInterface $listener, EmotiEventInterface $event): void
    {
        $maxRetries = 3;
        $retryDelay = 5;
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $listener->handle($event);
                return;
            } catch (Exception $e) {
                $lastException = $e;

                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                }
            }
        }

        throw $lastException;
    }

    private function getConsumerTag(): string
    {
        return sprintf('%s.%s', Config::get('env'), Config::get('project_name'));
    }
}