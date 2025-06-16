<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Config;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Support\Config')]
final class ConfigFactory
{
    public static function make(): ConfigInterface
    {
        return self::isLaravel()
            ? new LaravelConfig()
            : new VanillaPHPConfig();
    }

    private static function isLaravel(): bool
    {
        return function_exists('config');
    }
}