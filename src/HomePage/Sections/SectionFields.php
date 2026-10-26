<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Field readers that the home page sections share.
 */
#[NamespaceVisibility(namespace: 'Emoti\CommonResources\HomePage\Sections')]
final class SectionFields
{
    /**
     * @return array<string, string> Label name => Tolgee key.
     *
     * @throws InvalidArgumentException
     */
    public static function labels(mixed $labels, string $section): array
    {
        if (!is_array($labels)) {
            throw new InvalidArgumentException("The {$section} labels must be an object.");
        }

        $keys = [];

        foreach ($labels as $name => $key) {
            if (!is_string($name) || !is_string($key) || $key === '') {
                throw new InvalidArgumentException("Every {$section} label must map a name to a non-empty Tolgee key.");
            }

            $keys[$name] = $key;
        }

        return $keys;
    }

    /**
     * @return list<string> gifts-api location UUIDs, in the editor order.
     *
     * @throws InvalidArgumentException
     */
    public static function cityIds(mixed $cityIds, string $section): array
    {
        if (!is_array($cityIds) || !array_is_list($cityIds)) {
            throw new InvalidArgumentException("The {$section} city ids must be a list.");
        }

        $ids = [];

        foreach ($cityIds as $cityId) {
            if (!is_string($cityId) || !Uuid::isValid($cityId)) {
                throw new InvalidArgumentException("Every {$section} city id must be a location UUID.");
            }

            if (in_array($cityId, $ids, true)) {
                throw new InvalidArgumentException("The {$section} city ids must be unique.");
            }

            $ids[] = $cityId;
        }

        return $ids;
    }

    /**
     * @return list<int> agcore category ids, in the editor order.
     *
     * @throws InvalidArgumentException
     */
    public static function categoryIds(mixed $categoryIds, string $section): array
    {
        if (!is_array($categoryIds) || !array_is_list($categoryIds)) {
            throw new InvalidArgumentException("The {$section} category ids must be a list.");
        }

        $ids = [];

        foreach ($categoryIds as $categoryId) {
            if (!is_int($categoryId) || $categoryId < 1) {
                throw new InvalidArgumentException("Every {$section} category id must be a positive integer.");
            }

            if (in_array($categoryId, $ids, true)) {
                throw new InvalidArgumentException("The {$section} category ids must be unique.");
            }

            $ids[] = $categoryId;
        }

        return $ids;
    }

    /**
     * @return list<array<mixed>>
     *
     * @throws InvalidArgumentException
     */
    public static function objects(mixed $objects, string $name, int $min, ?int $max = null): array
    {
        if (!is_array($objects) || !array_is_list($objects)) {
            throw new InvalidArgumentException("The {$name} must be a list.");
        }

        if (count($objects) < $min) {
            throw new InvalidArgumentException("The {$name} must have at least {$min} items.");
        }

        if ($max !== null && count($objects) > $max) {
            throw new InvalidArgumentException("The {$name} must have at most {$max} items.");
        }

        return array_map(static function (mixed $object) use ($name): array {
            if (!is_array($object)) {
                throw new InvalidArgumentException("Every item of the {$name} must be an object.");
            }

            return $object;
        }, $objects);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function text(mixed $text, string $name): string
    {
        if (!is_string($text) || trim($text) === '') {
            throw new InvalidArgumentException("The {$name} must be a non-empty string.");
        }

        return $text;
    }
}
