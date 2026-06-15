<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Publisher;

use Emoti\CommonResources\Queue\Message;

/**
 * Test double that records published messages instead of sending them to
 * RabbitMQ. Install it via {@see PublisherRegistry::fake()} in a test, then
 * assert on the captured payloads. Reset with {@see PublisherRegistry::reset()}.
 */
final class FakePublisher implements PublisherInterface
{
    /** @var list<array{routingKey: string, content: array, class: class-string, priority: int}> */
    public array $published = [];

    public function publish(Message $message, string $routingKey): void
    {
        $this->published[] = [
            'routingKey' => $routingKey,
            'content' => $message->content,
            'class' => $message->class,
            'priority' => $message->priority,
        ];
    }

    /**
     * The event payloads (each event's toArray() output) published under the
     * given routing key, in publish order. Lets callers assert without ever
     * touching the Message type.
     *
     * @return list<array>
     */
    public function payloadsFor(string $routingKey): array
    {
        $matching = array_filter(
            $this->published,
            static fn (array $entry): bool => $entry['routingKey'] === $routingKey,
        );

        return array_values(array_map(
            static fn (array $entry): array => $entry['content'],
            $matching,
        ));
    }

    public function countFor(string $routingKey): int
    {
        return count($this->payloadsFor($routingKey));
    }
}
