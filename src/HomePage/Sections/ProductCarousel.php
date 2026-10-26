<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class ProductCarousel
{
    public const MIN_PRODUCT_COUNT = 1;
    public const MAX_PRODUCT_COUNT = 30;

    /**
     * @param array<string, string> $labels Label name => Tolgee key.
     */
    public function __construct(
        public readonly int $categoryId,
        public readonly int $productCount,
        public readonly string $gaListName,
        public readonly bool $highlighted,
        public readonly array $labels,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $categoryId = $data['categoryId'] ?? null;
        $productCount = $data['productCount'] ?? null;
        $gaListName = $data['gaListName'] ?? null;
        $highlighted = $data['highlighted'] ?? null;

        if (!is_int($categoryId) || $categoryId < 1) {
            throw new InvalidArgumentException('The carousel category id must be a positive integer.');
        }

        if (!is_int($productCount) || $productCount < self::MIN_PRODUCT_COUNT || $productCount > self::MAX_PRODUCT_COUNT) {
            throw new InvalidArgumentException('The carousel product count must be an integer from ' . self::MIN_PRODUCT_COUNT . ' to ' . self::MAX_PRODUCT_COUNT . '.');
        }

        if (!is_string($gaListName) || trim($gaListName) === '') {
            throw new InvalidArgumentException('The carousel GA list name must be a non-empty string.');
        }

        if (!is_bool($highlighted)) {
            throw new InvalidArgumentException('The carousel highlighted flag must be a boolean.');
        }

        return new self(
            $categoryId,
            $productCount,
            $gaListName,
            $highlighted,
            SectionFields::labels($data['labels'] ?? null, 'carousel'),
        );
    }

    /**
     * @return array{
     *     categoryId: int,
     *     productCount: int,
     *     gaListName: string,
     *     highlighted: bool,
     *     labels: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'categoryId' => $this->categoryId,
            'productCount' => $this->productCount,
            'gaListName' => $this->gaListName,
            'highlighted' => $this->highlighted,
            'labels' => $this->labels,
        ];
    }
}
