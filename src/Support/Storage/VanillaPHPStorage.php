<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Storage;

use RuntimeException;

final class VanillaPHPStorage implements StorageInterface
{

    public static function path(string $filename): string
    {
        $directory = self::getStorageDirectory();
        self::ensureDirectoryExists($directory);

        return "{$directory}/{$filename}";
    }

    private static function getStorageDirectory(): string
    {
        return self::getProjectRoot() . '/storage/app/private';
    }

    private static function getProjectRoot(): string
    {
        foreach (get_included_files() as $file) {
            if (basename($file) === 'autoload.php' && str_contains($file, '/vendor/')) {
                return dirname($file, 2);
            }
        }

        throw new RuntimeException('Unable to detect project root');
    }

    private static function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}