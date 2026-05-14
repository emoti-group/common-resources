<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Events;

use Emoti\CommonResources\Queue\Events\System\ExternalQueueRestartRequested;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class DispatchableTraitTest extends TestCase
{
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