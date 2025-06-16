<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Storage;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Support\Storage')]
final class LaravelStorage implements StorageInterface
{
    public static function path(string $filename): string
    {
        return storage_path("app/private/{$filename}");
    }
}