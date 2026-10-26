<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class TrustStripItem
{
    /**
     * @param string $title Plain text. A line break is "\n".
     * @param ?string $link Null: the item has no link.
     */
    public function __construct(
        public readonly TrustStripIcon $icon,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $link,
    ) {}

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $icon = is_string($data['icon'] ?? null) ? TrustStripIcon::tryFrom($data['icon']) : null;
        $title = SectionFields::text($data['title'] ?? null, 'trust strip item title');
        $description = $data['description'] ?? null;
        $link = $data['link'] ?? null;

        if ($icon === null) {
            throw new InvalidArgumentException('The trust strip item icon is not in the icon set.');
        }

        if (strip_tags($title) !== $title) {
            throw new InvalidArgumentException('The trust strip item title must be plain text. Use "\n" for a line break.');
        }

        if (!is_string($description)) {
            throw new InvalidArgumentException('The trust strip item description must be a string.');
        }

        return new self(
            $icon,
            $title,
            $description,
            $link === null ? null : SectionFields::text($link, 'trust strip item link'),
        );
    }

    /**
     * @return array{
     *     icon: string,
     *     title: string,
     *     description: string,
     *     link: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'icon' => $this->icon->value,
            'title' => $this->title,
            'description' => $this->description,
            'link' => $this->link,
        ];
    }
}
