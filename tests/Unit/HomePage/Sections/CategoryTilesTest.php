<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage\Sections;

use Emoti\CommonResources\HomePage\Sections\CategoryTiles;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CategoryTilesTest extends TestCase
{
    public function test_to_array_round_trips(): void
    {
        $this->assertSame(self::payload(), CategoryTiles::fromArray(self::payload())->toArray());
    }

    public function test_a_missing_title_override_is_null(): void
    {
        $payload = self::payload();
        unset($payload['groups'][0]['categories'][0]['title']);

        $this->assertNull(CategoryTiles::fromArray($payload)->groups[0]->categories[0]->title);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_from_array_rejects_an_invalid_payload(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        CategoryTiles::fromArray($payload);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        $payload = self::payload();
        $group = $payload['groups'][0];
        $desktop = $group['image']['desktop'];

        return [
            'mobile image' => [[...$payload, 'groups' => [[...$group, 'image' => ['desktop' => $desktop, 'mobile' => $desktop]]]]],
            'image without a width' => [[...$payload, 'groups' => [[...$group, 'image' => ['desktop' => ['url' => $desktop['url'], 'height' => 400], 'mobile' => null]]]]],
            'group without categories' => [[...$payload, 'groups' => [[...$group, 'categories' => []]]]],
            'empty title override' => [[...$payload, 'groups' => [[...$group, 'categories' => [['categoryId' => 170, 'title' => '']]]]]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return [
            'groups' => [
                [
                    'image' => [
                        'desktop' => ['url' => 'https://cdn.example.com/spa-600x400.jpg', 'width' => 600, 'height' => 400],
                        'mobile' => null,
                    ],
                    'title' => 'SPA',
                    'categories' => [
                        ['categoryId' => 170, 'title' => 'Masaże'],
                        ['categoryId' => 171, 'title' => null],
                    ],
                ],
            ],
            'labels' => ['title' => 'home:category_tiles.title'],
        ];
    }
}
