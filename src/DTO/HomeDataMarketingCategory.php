<?php

declare(strict_types=1);

namespace Emoti\CommonResources\DTO;

use InvalidArgumentException;

final class HomeDataMarketingCategory
{
    /**
     * @param string $url The path of the category listing, with the language prefix (e.g. /ru/podarki/).
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $url,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;
        $title = $data['title'] ?? null;
        $url = $data['url'] ?? null;

        if (!is_int($id) || $id < 1) {
            throw new InvalidArgumentException('The marketing category id must be a positive integer.');
        }

        if (!is_string($title)) {
            throw new InvalidArgumentException('The marketing category title must be a string.');
        }

        if (!is_string($url) || !str_starts_with($url, '/')) {
            throw new InvalidArgumentException('The marketing category url must be a path that starts with a slash.');
        }

        return new self($id, $title, $url);
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     url: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
        ];
    }
}
