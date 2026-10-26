<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class LinkColumns
{
    public const MAX_COLUMNS = 4;
    public const MAX_LINKS_PER_COLUMN = 5;

    /**
     * @param list<list<CategoryLink>> $columns Each column is an ordered list of links.
     * @param array<string, string> $labels Label name => Tolgee key.
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $labels,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $columns = SectionFields::objects($data['columns'] ?? null, 'link columns', 0, self::MAX_COLUMNS);

        return new self(
            array_map(static fn(array $column): array => array_map(
                static fn(array $link): CategoryLink => CategoryLink::fromArray($link),
                SectionFields::objects($column['links'] ?? null, 'column links', 1, self::MAX_LINKS_PER_COLUMN),
            ), $columns),
            SectionFields::labels($data['labels'] ?? null, 'link columns'),
        );
    }

    /**
     * @return array{
     *     columns: list<array{links: list<array{categoryId: int, title: ?string}>}>,
     *     labels: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'columns' => array_map(static fn(array $links): array => [
                'links' => array_map(static fn(CategoryLink $link): array => $link->toArray(), $links),
            ], $this->columns),
            'labels' => $this->labels,
        ];
    }
}
