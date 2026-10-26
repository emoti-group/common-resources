<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use InvalidArgumentException;

final class TrustStrip
{
    /**
     * @param list<TrustStripItem> $items The visible items, in the editor order.
     * @param array<string, string> $labels Label name => Tolgee key.
     */
    public function __construct(
        public readonly array $items,
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
                static fn(array $item): TrustStripItem => TrustStripItem::fromArray($item),
                SectionFields::objects($data['items'] ?? null, 'trust strip items', 0),
            ),
            SectionFields::labels($data['labels'] ?? null, 'trust strip'),
        );
    }

    /**
     * @return array{
     *     items: list<array{icon: string, title: string, description: string, link: ?string}>,
     *     labels: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn(TrustStripItem $item): array => $item->toArray(),
                $this->items,
            ),
            'labels' => $this->labels,
        ];
    }
}
