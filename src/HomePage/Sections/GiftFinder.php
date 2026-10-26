<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class GiftFinder
{
    /**
     * @param list<string> $cityIds gifts-api location UUIDs of type city, in the editor order.
     * @param list<int> $categoryIds agcore category ids, in the editor order.
     * @param array<string, string> $labels Label name => Tolgee key.
     */
    public function __construct(
        public readonly array $cityIds,
        public readonly array $categoryIds,
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
            SectionFields::cityIds($data['cityIds'] ?? null, 'gift finder'),
            SectionFields::categoryIds($data['categoryIds'] ?? null, 'gift finder'),
            SectionFields::labels($data['labels'] ?? null, 'gift finder'),
        );
    }

    /**
     * @return array{
     *     cityIds: list<string>,
     *     categoryIds: list<int>,
     *     labels: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'cityIds' => $this->cityIds,
            'categoryIds' => $this->categoryIds,
            'labels' => $this->labels,
        ];
    }
}
