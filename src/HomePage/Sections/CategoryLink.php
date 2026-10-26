<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class CategoryLink
{
    /**
     * @param ?string $title The title override. Null: show the agcore category title.
     */
    public function __construct(
        public readonly int $categoryId,
        public readonly ?string $title,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $categoryId = $data['categoryId'] ?? null;
        $title = $data['title'] ?? null;

        if (!is_int($categoryId) || $categoryId < 1) {
            throw new InvalidArgumentException('The category link category id must be a positive integer.');
        }

        return new self(
            $categoryId,
            $title === null ? null : SectionFields::text($title, 'category link title'),
        );
    }

    /**
     * @return array{
     *     categoryId: int,
     *     title: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'categoryId' => $this->categoryId,
            'title' => $this->title,
        ];
    }
}
