<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Config;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Support\Config')]
final class LaravelConfig implements ConfigInterface
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return config('common-resources.' . $key, $default);
    }
}