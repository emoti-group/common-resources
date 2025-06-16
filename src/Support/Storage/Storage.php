<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Storage;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources')]
final class Storage
{
    public static function path(string $filename): string
    {
        $storage = StorageFactory::make();

        return $storage->path($filename);
    }
}