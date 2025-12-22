<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support;

use Emoti\CommonResources\Exceptions\LogicException;

trait SingletonTrait
{
    private static ?self $instance = null;

    protected function __construct()
    {}

    private function __clone(): void
    {
        throw new LogicException('Cannot unserialize singleton');
    }

    final public function __wakeup()
    {
        throw new LogicException('Cannot unserialize singleton');
    }

    final public static function destroy(): void
    {
        if (self::$instance !== null) {
            self::$instance = null;
        }
    }

    final public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }
}