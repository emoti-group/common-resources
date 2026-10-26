<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class CategoryTiles
{
    /**
     * @param list<CategoryTileGroup> $groups
     * @param array<string, string> $labels Label name => Tolgee key.
     */
    public function __construct(
        public readonly array $groups,
        public readonly array $labels,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(
                static fn(array $group): CategoryTileGroup => CategoryTileGroup::fromArray($group),
                SectionFields::objects($data['groups'] ?? null, 'category tile groups', 0),
            ),
            SectionFields::labels($data['labels'] ?? null, 'category tiles'),
        );
    }

    /**
     * @return array{
     *     groups: list<array{
     *         image: array{desktop: array{url: string, width: int, height: int}, mobile: null},
     *         title: string,
     *         categories: list<array{categoryId: int, title: ?string}>
     *     }>,
     *     labels: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'groups' => array_map(
                static fn(CategoryTileGroup $group): array => $group->toArray(),
                $this->groups,
            ),
            'labels' => $this->labels,
        ];
    }
}
