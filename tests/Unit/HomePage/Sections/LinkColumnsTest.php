<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage\Sections;

use Emoti\CommonResources\HomePage\Sections\LinkColumns;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinkColumnsTest extends TestCase
{
    private const LINK = ['categoryId' => 170, 'title' => null];

    public function test_to_array_round_trips(): void
    {
        $payload = [
            'columns' => [
                ['links' => [['categoryId' => 170, 'title' => 'SPA i Masaże'], ['categoryId' => 12, 'title' => null]]],
                ['links' => [['categoryId' => 442, 'title' => null]]],
            ],
            'labels' => ['title' => 'home:link_columns.title'],
        ];

        $this->assertSame($payload, LinkColumns::fromArray($payload)->toArray());
    }

    public function test_accepts_4_columns_of_5_links(): void
    {
        $columns = array_fill(0, 4, ['links' => array_fill(0, 5, self::LINK)]);

        $this->assertCount(4, LinkColumns::fromArray(['columns' => $columns, 'labels' => []])->columns);
    }

    /**
     * @param list<mixed> $columns
     */
    #[DataProvider('invalidColumns')]
    public function test_from_array_rejects_invalid_columns(array $columns): void
    {
        $this->expectException(InvalidArgumentException::class);

        LinkColumns::fromArray(['columns' => $columns, 'labels' => []]);
    }

    /**
     * @return array<string, array{list<mixed>}>
     */
    public static function invalidColumns(): array
    {
        return [
            '5 columns' => [array_fill(0, 5, ['links' => [self::LINK]])],
            '6 links' => [[['links' => array_fill(0, 6, self::LINK)]]],
            'column without links' => [[['links' => []]]],
            'column as a bare list' => [[[self::LINK]]],
        ];
    }
}
