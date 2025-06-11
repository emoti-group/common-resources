<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support;

trait SingletonTrait
{
    private static ?self $instance = null;

    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }
}