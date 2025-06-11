<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Storage;

final class StorageFactory
{
    public static function make(): StorageInterface
    {
        return self::isLaravel()
            ? new LaravelStorage()
            : new VanillaPHPStorage();
    }

    private static function isLaravel(): bool
    {
        return function_exists('storage_path');
    }
}