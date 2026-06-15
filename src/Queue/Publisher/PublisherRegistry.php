<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Publisher;

/**
 * Resolves the publisher used by {@see \Emoti\CommonResources\Queue\Events\Traits\DispatchableTrait}.
 *
 * In production this returns a real {@see RabbitMQPublisher}. Tests can install
 * a {@see FakePublisher} (or any {@see PublisherInterface}) to capture dispatched
 * events instead of sending them to the broker. Always {@see self::reset()} in
 * test teardown so the override does not leak across tests.
 */
final class PublisherRegistry
{
    private static ?PublisherInterface $override = null;

    public static function resolve(): PublisherInterface
    {
        return self::$override ?? new RabbitMQPublisher();
    }

    public static function set(PublisherInterface $publisher): void
    {
        self::$override = $publisher;
    }

    /**
     * Install and return a fresh recording publisher.
     */
    public static function fake(): FakePublisher
    {
        $fake = new FakePublisher();
        self::$override = $fake;

        return $fake;
    }

    public static function reset(): void
    {
        self::$override = null;
    }
}
