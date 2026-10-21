<?php

declare(strict_types=1);

namespace Emoti\CommonResources\DTO;

use DateTimeImmutable;
use DateTimeInterface;
use Emoti\CommonResources\Enums\HomePageType;
use Emoti\CommonResources\Enums\Lang;
use Exception;
use InvalidArgumentException;

/**
 * @phpstan-type Section array{key: string, type: string, data: array<string, mixed>}
 */
final class HomePageSnapshot
{
    /**
     * @param array<string, mixed> $settings Page meta: title, description, canonical, noindex.
     * @param list<Section> $sections
     */
    public function __construct(
        public readonly HomePageType $type,
        public readonly Lang $locale,
        public readonly int $version,
        public readonly int $schemaVersion,
        public readonly array $settings,
        public readonly array $sections,
        public readonly DateTimeImmutable $publishedAt,
        public readonly ?string $publishedBy,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $type = HomePageType::tryFrom(self::string($data, 'type'));
        $locale = Lang::tryFrom(self::string($data, 'locale'));

        if ($type === null) {
            throw new InvalidArgumentException('Unknown home page type.');
        }

        if ($locale === null) {
            throw new InvalidArgumentException('Unknown home page locale.');
        }

        $settings = $data['settings'] ?? null;

        if (!is_array($settings)) {
            throw new InvalidArgumentException('The home page settings must be an object.');
        }

        $publishedBy = $data['publishedBy'] ?? null;

        if ($publishedBy !== null && !is_string($publishedBy)) {
            throw new InvalidArgumentException('The home page publisher must be a string.');
        }

        try {
            $publishedAt = new DateTimeImmutable(self::string($data, 'publishedAt'));
        } catch (Exception $exception) {
            throw new InvalidArgumentException('The home page publish date is not valid.', previous: $exception);
        }

        return new self(
            type: $type,
            locale: $locale,
            version: self::int($data, 'version'),
            schemaVersion: self::int($data, 'schemaVersion'),
            settings: $settings,
            sections: self::sections($data['sections'] ?? null),
            publishedAt: $publishedAt,
            publishedBy: $publishedBy,
        );
    }

    /**
     * @return array{
     *     type: string,
     *     locale: string,
     *     version: int,
     *     schemaVersion: int,
     *     settings: array<string, mixed>,
     *     sections: list<Section>,
     *     publishedAt: string,
     *     publishedBy: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'locale' => $this->locale->value,
            'version' => $this->version,
            'schemaVersion' => $this->schemaVersion,
            'settings' => $this->settings,
            'sections' => $this->sections,
            'publishedAt' => $this->publishedAt->format(DateTimeInterface::ATOM),
            'publishedBy' => $this->publishedBy,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException("The home page {$key} must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException("The home page {$key} must be a positive integer.");
        }

        return $value;
    }

    /**
     * @return list<Section>
     */
    private static function sections(mixed $sections): array
    {
        if (!is_array($sections) || !array_is_list($sections)) {
            throw new InvalidArgumentException('The home page sections must be a list.');
        }

        return array_map(static function (mixed $section): array {
            if (
                !is_array($section)
                || !is_string($section['key'] ?? null)
                || !is_string($section['type'] ?? null)
                || !is_array($section['data'] ?? null)
            ) {
                throw new InvalidArgumentException('Every home page section needs a key, a type and data.');
            }

            return [
                'key' => $section['key'],
                'type' => $section['type'],
                'data' => $section['data'],
            ];
        }, $sections);
    }
}
