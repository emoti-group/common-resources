<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class GiftFilter
{
    /*
     * The price range is fixed and the same on all sites, as on agcore (filterfield.tpl).
     * gifts-api adds it to the public payload as priceRange.
     */
    public const PRICE_MIN = 1;
    public const PRICE_MAX = 1000;
    public const PRICE_START_MIN = 50;
    public const PRICE_START_MAX = 900;
    public const PRICE_STEP = 1;

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
            SectionFields::cityIds($data['cityIds'] ?? null, 'gift filter'),
            SectionFields::categoryIds($data['categoryIds'] ?? null, 'gift filter'),
            SectionFields::labels($data['labels'] ?? null, 'gift filter'),
        );
    }

    /**
     * @return array{
     *     min: int,
     *     max: int,
     *     startMin: int,
     *     startMax: int,
     *     step: int
     * }
     */
    public static function priceRange(): array
    {
        return [
            'min' => self::PRICE_MIN,
            'max' => self::PRICE_MAX,
            'startMin' => self::PRICE_START_MIN,
            'startMax' => self::PRICE_START_MAX,
            'step' => self::PRICE_STEP,
        ];
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
