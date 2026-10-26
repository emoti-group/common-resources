<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class CategoryTileGroup
{
    /**
     * @param array{url: string, width: int, height: int} $image The desktop image. The front also uses it on mobile.
     * @param string $title The front also uses the title as the alt text of the image.
     * @param list<CategoryLink> $categories
     */
    public function __construct(
        public readonly array $image,
        public readonly string $title,
        public readonly array $categories,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::image($data['image'] ?? null),
            SectionFields::text($data['title'] ?? null, 'category tile group title'),
            array_map(
                static fn(array $category): CategoryLink => CategoryLink::fromArray($category),
                SectionFields::objects($data['categories'] ?? null, 'category tile group categories', 1),
            ),
        );
    }

    /**
     * @return array{
     *     image: array{
     *         desktop: array{url: string, width: int, height: int},
     *         mobile: null
     *     },
     *     title: string,
     *     categories: list<array{categoryId: int, title: ?string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'image' => [
                'desktop' => $this->image,
                'mobile' => null,
            ],
            'title' => $this->title,
            'categories' => array_map(
                static fn(CategoryLink $category): array => $category->toArray(),
                $this->categories,
            ),
        ];
    }

    /**
     * @return array{url: string, width: int, height: int}
     */
    private static function image(mixed $image): array
    {
        if (!is_array($image) || ($image['mobile'] ?? null) !== null) {
            throw new InvalidArgumentException('The category tile group image must be an object with a desktop image only.');
        }

        $desktop = $image['desktop'] ?? null;

        if (
            !is_array($desktop)
            || !is_string($desktop['url'] ?? null)
            || $desktop['url'] === ''
            || !is_int($desktop['width'] ?? null)
            || $desktop['width'] < 1
            || !is_int($desktop['height'] ?? null)
            || $desktop['height'] < 1
        ) {
            throw new InvalidArgumentException('The category tile group desktop image needs a url, a width and a height.');
        }

        return [
            'url' => $desktop['url'],
            'width' => $desktop['width'],
            'height' => $desktop['height'],
        ];
    }
}
