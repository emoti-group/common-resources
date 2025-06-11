<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Storage;

final class Storage
{
    public static function path(string $filename): string
    {
        $storage = StorageFactory::make();

        return $storage->path($filename);
    }
}