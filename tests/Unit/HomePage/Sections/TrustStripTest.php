<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage\Sections;

use Emoti\CommonResources\HomePage\Sections\TrustStrip;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrustStripTest extends TestCase
{
    private const ITEM = ['icon' => 'exchange', 'title' => '101 dni na zwrot', 'description' => '', 'link' => '/gwarantujemy/'];

    public function test_to_array_round_trips(): void
    {
        $payload = [
            'items' => [
                self::ITEM,
                ['icon' => 'pig', 'title' => "Nemokamas pristatymas\nnuo 45 €", 'description' => '', 'link' => '/garantijos'],
                ['icon' => 'globe', 'title' => 'Kansainvälinen konserni', 'description' => '', 'link' => null],
            ],
            'labels' => [],
        ];

        $this->assertSame($payload, TrustStrip::fromArray($payload)->toArray());
    }

    /**
     * @param array<string, mixed> $item
     */
    #[DataProvider('invalidItems')]
    public function test_from_array_rejects_an_invalid_item(array $item): void
    {
        $this->expectException(InvalidArgumentException::class);

        TrustStrip::fromArray(['items' => [$item], 'labels' => []]);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidItems(): array
    {
        return [
            'agcore class as the icon' => [[...self::ITEM, 'icon' => 'web_icons_exchange']],
            'HTML line break in the title' => [[...self::ITEM, 'title' => 'Nemokamas pristatymas<br>nuo 45 €']],
            'empty link instead of null' => [[...self::ITEM, 'link' => '']],
        ];
    }
}
