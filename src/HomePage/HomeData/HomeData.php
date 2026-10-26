<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\HomeData;

use InvalidArgumentException;

final class HomeData
{
    /**
     * @param list<MarketingCategory> $marketingCategories Only the requested categories that agcore has.
     */
    public function __construct(
        public readonly array $marketingCategories,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $categories = $data['marketingCategories'] ?? null;

        if (!is_array($categories) || !array_is_list($categories)) {
            throw new InvalidArgumentException('The home data marketing categories must be a list.');
        }

        return new self(array_map(static function (mixed $category): MarketingCategory {
            if (!is_array($category)) {
                throw new InvalidArgumentException('Every home data marketing category must be an object.');
            }

            return MarketingCategory::fromArray($category);
        }, $categories));
    }

    public function marketingCategory(int $id): ?MarketingCategory
    {
        foreach ($this->marketingCategories as $category) {
            if ($category->id === $id) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     marketingCategories: list<array{id: int, title: string, url: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'marketingCategories' => array_map(
                static fn(MarketingCategory $category): array => $category->toArray(),
                $this->marketingCategories,
            ),
        ];
    }
}
