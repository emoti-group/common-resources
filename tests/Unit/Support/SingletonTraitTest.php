<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Emoti\CommonResources\Exceptions\LogicException;
use Emoti\CommonResources\Support\SingletonTrait;
use PHPUnit\Framework\TestCase;

class ConcreteSingleton
{
    use SingletonTrait;
}

final class SingletonTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConcreteSingleton::destroy();
    }

    protected function tearDown(): void
    {
        ConcreteSingleton::destroy();
        parent::tearDown();
    }

    public function test_get_instance_returns_same_object_on_repeated_calls(): void
    {
        $first = ConcreteSingleton::getInstance();
        $second = ConcreteSingleton::getInstance();

        $this->assertSame($first, $second);
    }

    public function test_destroy_allows_fresh_instance_to_be_created(): void
    {
        $old = ConcreteSingleton::getInstance();

        ConcreteSingleton::destroy();

        $new = ConcreteSingleton::getInstance();

        $this->assertNotSame($old, $new);
    }

    public function test_clone_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);

        $instance = ConcreteSingleton::getInstance();
        // __clone is private, so PHP throws Error before invoking it on clone.
        // Use reflection to directly invoke __clone() to test it throws LogicException.
        $ref = new \ReflectionClass($instance);
        $method = $ref->getMethod('__clone');
        $method->setAccessible(true);
        $method->invoke($instance);
    }

    public function test_wakeup_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);

        $instance = ConcreteSingleton::getInstance();
        $instance->__wakeup();
    }
}
