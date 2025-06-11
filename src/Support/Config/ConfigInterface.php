<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Config;

interface ConfigInterface
{
    public static function get(string $key, mixed $default = null): mixed;
}