<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Support\Config;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use RuntimeException;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Support\Config')]
final class VanillaPHPConfig implements ConfigInterface
{
    public static function get(string $key, mixed $default = null): mixed
    {
        static $config = null;

        if ($config === null) {
            $configPath = self::getProjectRoot() . '/config/common-resources.php';

            if (!is_file($configPath)) {
                return $default;
            }

            $config = require $configPath;
        }

        return self::getNestedValue($config, $key, $default);
    }

    private static function getNestedValue(array $config, string $key, mixed $default): mixed
    {
        $segments = explode('.', $key);
        $value = $config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
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
}