<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events;

use Emoti\CommonResources\Enums\Site;
use Emoti\CommonResources\Queue\Events\Order\OrderCancelled;
use Emoti\CommonResources\Queue\Events\System\ExternalQueueRestartRequested;
use Emoti\CommonResources\Queue\Publisher\PublisherRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class DispatchableTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        PublisherRegistry::reset();
        parent::tearDown();
    }

    public function test_dispatch_routes_through_registry_to_installed_publisher(): void
    {
        $fake = PublisherRegistry::fake();

        (new OrderCancelled(id: 555, site: Site::PL, isB2b: true))->dispatch(Site::PL);

        $payloads = $fake->payloadsFor('order.cancelled.v1');
        $this->assertCount(1, $payloads);
        $this->assertSame(555, $payloads[0]['data']['id']);
        $this->assertTrue($payloads[0]['data']['isB2b']);
    }

    public function test_reset_detaches_previously_installed_fake(): void
    {
        $first = PublisherRegistry::fake();
        PublisherRegistry::reset();
        $second = PublisherRegistry::fake();

        (new OrderCancelled(id: 1, site: Site::PL, isB2b: false))->dispatch(Site::PL);

        $this->assertCount(0, $first->published);
        $this->assertCount(1, $second->published);
    }

    public function test_dispatch_has_priority_parameter_with_default_five(): void
    {
        $method = new ReflectionMethod(ExternalQueueRestartRequested::class, 'dispatch');
        $params = $method->getParameters();

        $priorityParam = null;
        foreach ($params as $param) {
            if ($param->getName() === 'priority') {
                $priorityParam = $param;
                break;
            }
        }

        $this->assertNotNull($priorityParam, 'dispatch() must have a $priority parameter');
        $this->assertTrue($priorityParam->isOptional());
        $this->assertSame(5, $priorityParam->getDefaultValue());
    }

    public function test_dispatch_priority_parameter_is_typed_int(): void
    {
        $method = new ReflectionMethod(ExternalQueueRestartRequested::class, 'dispatch');

        foreach ($method->getParameters() as $param) {
            if ($param->getName() === 'priority') {
                $type = $param->getType();
                $this->assertNotNull($type);
                $this->assertSame('int', $type->getName());
                return;
            }
        }

        $this->fail('dispatch() must have a $priority parameter');
    }
}