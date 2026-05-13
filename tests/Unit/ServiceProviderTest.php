<?php

declare(strict_types=1);

namespace Tests\Unit;

use Emoti\CommonResources\CommonResourcesServiceProvider;
use Emoti\CommonResources\Queue\Consumer\ConsumerInterface;
use Emoti\CommonResources\Queue\Consumer\RabbitMQConsumer;
use Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_consumer_interface_is_bound_in_container(): void
    {
        $this->assertTrue($this->app->bound(ConsumerInterface::class));
    }

    public function test_consumer_interface_is_mapped_to_rabbitmq_consumer(): void
    {
        $provider = new CommonResourcesServiceProvider($this->app);

        $this->assertSame(RabbitMQConsumer::class, $provider->bindings[ConsumerInterface::class]);
    }

    public function test_config_is_merged_from_package_defaults(): void
    {
        $this->assertSame('gifts', config('common-resources.rabbitmq.exchange'));
    }

    public function test_external_queue_work_command_is_registered(): void
    {
        // The command exists but requires a {queue} argument.
        // Calling it without arguments throws a RuntimeException ("Not enough arguments"),
        // which means the command IS registered (an unregistered command would throw
        // a different "Command not found" exception).
        try {
            $this->artisan('common-resources:queue-external:work');
            $this->fail('Expected an exception for missing argument');
        } catch (\Symfony\Component\Console\Exception\RuntimeException $e) {
            $this->assertStringContainsString('Not enough arguments', $e->getMessage());
        }
    }
}
