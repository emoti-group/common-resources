<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Config;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources')]
final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $config = ConfigFactory::make();

        return $config->get($key, $default);
    }
}