<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Storage;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Support\Storage')]
interface StorageInterface
{
    public static function path(string $filename): string;
}