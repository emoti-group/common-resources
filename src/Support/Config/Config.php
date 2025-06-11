<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Config;

final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $config = ConfigFactory::make();

        return $config->get($key, $default);
    }
}