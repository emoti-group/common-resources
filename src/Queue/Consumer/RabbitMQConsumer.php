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
use Emoti\CommonResources\Services\Monitoring\QueueJobMetrics;
use Emoti\CommonResources\Services\Monitoring\SentryMetricsReporter;
use Emoti\CommonResources\Support\Config\Config;
use Illuminate\Support\Facades\App;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class RabbitMQConsumer implements ConsumerInterface
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_SECONDS = 5;

    /**
     * Low-traffic queues may never reach the SDK's size-based flush threshold, so buffered
     * metrics are also sent whenever the last flush is older than this: after each message
     * and from the consume loop while idle. The flush is a blocking HTTP call bounded by the
     * SDK's http timeouts.
     */
    private const METRICS_FLUSH_INTERVAL_SECONDS = 30;

    private readonly RabbitMQClient $client;
    private readonly QueueJobMetrics $metrics;

    public function __construct(?QueueJobMetrics $metrics = null)
    {
        $this->client = RabbitMQClient::getInstance();
        // Laravel injects the container singleton; no-framework apps get a standalone instance.
        $this->metrics = $metrics ?? new QueueJobMetrics(new SentryMetricsReporter());

        // Handle consumer shutdown (also the `exit` after ExternalQueueRestartRequested)
        register_shutdown_function(function () {
            $this->metrics->flush();

            try {
                $this->client->channel->close();
            } catch (Throwable) {
            }

            try {
                $this->client->connection->close();
            } catch (Throwable) {
            }
        });
    }

    /**
     * @param Closure(Throwable): void $captureException
     * @throws Throwable
     */
    public function consume(Closure $captureException, string $queueName): void
    {
        try {
            [, $declaredQueueName] = (new RabbitMQSetupper($this->client))->setup($queueName);
            $this->startQueueConsumer($declaredQueueName, $captureException, $queueName);
            $this->consumeLoop();
        } catch (Throwable $e) {
            $captureException($e);
            $this->client->channel->close();
            $this->client->connection->close();
            exit;
        }
    }

    /**
     * Same loop as AMQPChannel::consume(), but waking up at least every
     * METRICS_FLUSH_INTERVAL_SECONDS so buffered metrics of the last message leave the
     * process even when no further message arrives.
     *
     * @throws Throwable
     */
    private function consumeLoop(): void
    {
        $channel = $this->client->channel;
        $connection = $this->client->connection;

        $timeout = min($connection->getReadTimeout(), self::METRICS_FLUSH_INTERVAL_SECONDS);
        $heartbeat = $connection->getHeartbeat();
        if ($heartbeat > 2) {
            $timeout = min($timeout, (int) floor($heartbeat / 2));
        }
        $timeout = max($timeout, 1);

        while ($channel->is_consuming()) {
            try {
                $channel->wait(null, false, $timeout);
            } catch (AMQPTimeoutException) {
                // idle: nothing arrived within the timeout
            }

            $this->metrics->flushIfOlderThan(self::METRICS_FLUSH_INTERVAL_SECONDS);
        }
    }

    /**
     * @param Closure(Throwable): void $captureException
     */
    private function startQueueConsumer(string $queueName, Closure $captureException, string $bindingsGroup): void
    {
        $callback = function (AMQPMessage $AMQPMessage) use ($captureException, $bindingsGroup) {
            $outcome = new MessageMetrics();

            try {
                $event = $this->processTheMessage($AMQPMessage, $bindingsGroup, $outcome);
                $AMQPMessage->ack();

                // Recorded after ack(): a failing ack lands in the catch below and must not
                // leave both a success and a failure for the same message.
                if ($outcome->skipped) {
                    $this->metrics->recordSkipped($outcome->job, $bindingsGroup, QueueJobMetrics::RUNTIME_EXTERNAL);
                } else {
                    $this->metrics->recordSuccess(
                        $outcome->job,
                        $bindingsGroup,
                        QueueJobMetrics::RUNTIME_EXTERNAL,
                        $outcome->attempt,
                        $outcome->lastAttemptMs(),
                    );
                }
            } catch (Throwable $e) {
                // Throwable, not Exception: a TypeError in a listener must nack the message
                // like any other failure instead of escaping and killing the consumer.
                // nack() has requeue=false, so this is the final outcome (dead-letter queue);
                // an undecodable body is counted under `unknown`.
                $AMQPMessage->nack();
                $captureException($e);

                $this->metrics->recordFailure(
                    $outcome->job,
                    $bindingsGroup,
                    QueueJobMetrics::RUNTIME_EXTERNAL,
                    max(1, $outcome->attempt),
                    $outcome->lastAttemptMs(),
                    $e,
                );

                return;
            } finally {
                $this->metrics->flushIfOlderThan(self::METRICS_FLUSH_INTERVAL_SECONDS);
            }

            if ($event instanceof ExternalQueueRestartRequested) {
                exit;
            }
        };

        $this->client->channel->basic_qos(prefetch_size: 0, prefetch_count: 10, a_global: false);

        $this->client->channel->basic_consume(
            queue: $queueName,
            consumer_tag: $this->getConsumerTag(),
            callback: $callback,
        );
    }

    /**
     * @throws Throwable
     */
    private function processTheMessage(AMQPMessage $AMQPMessage, string $bindingsGroup, MessageMetrics $outcome): EmotiEventInterface
    {
        $message = Message::fromJson($AMQPMessage->getBody());

        /** @var EmotiEventInterface $event */
        $event = $message->class::fromArray($message->content);

        // `$event::class` is the canonical class name; the payload string is never used as
        // a metric attribute (case variants / arbitrary strings would each be a new series).
        $outcome->job = $event::class;

        $listener = Config::get('bindings.' . $bindingsGroup)[$event::class] ?? null;

        if (! $listener) {
            $outcome->skipped = true;

            return $event;
        }

        /** @var EmotiListenerInterface $listenerInstance */
        $listenerInstance = App::getFacadeRoot() ? App::make($listener) : new $listener();
        $outcome->job = $listenerInstance::class;

        $this->handleWithRetry($listenerInstance, $event, $bindingsGroup, $outcome);

        return $event;
    }

    /**
     * Runs the listener up to MAX_RETRIES + 1 times. Every retried attempt is recorded here
     * with its own duration; the final attempt is recorded by the caller after ack/nack.
     *
     * @throws Throwable
     */
    private function handleWithRetry(
        EmotiListenerInterface $listener,
        EmotiEventInterface $event,
        string $bindingsGroup,
        MessageMetrics $outcome,
    ): void {
        $maxAttempts = self::MAX_RETRIES + 1;

        for ($attempt = 1; ; $attempt++) {
            $outcome->attempt = $attempt;
            $startedAt = hrtime(true);

            try {
                $listener->handle($event);
                $outcome->lastAttemptNanoseconds = hrtime(true) - $startedAt;

                return;
            } catch (Throwable $e) {
                $outcome->lastAttemptNanoseconds = hrtime(true) - $startedAt;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                $this->metrics->recordRetry(
                    $listener::class,
                    $bindingsGroup,
                    QueueJobMetrics::RUNTIME_EXTERNAL,
                    $attempt,
                    $outcome->lastAttemptMs(),
                    $e,
                );
                sleep(self::RETRY_DELAY_SECONDS);
            }
        }
    }

    private function getConsumerTag(): string
    {
        return sprintf('%s.%s', Config::get('env'), Config::get('project_name'));
    }
}
