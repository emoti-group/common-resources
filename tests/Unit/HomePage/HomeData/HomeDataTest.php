<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage\HomeData;

use Emoti\CommonResources\HomePage\HomeData\HomeData;
use Emoti\CommonResources\HomePage\HomeData\MarketingCategory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HomeDataTest extends TestCase
{
    public function test_from_array_reads_every_category(): void
    {
        $homeData = HomeData::fromArray(self::payload());

        $this->assertEquals(
            [
                new MarketingCategory(442, 'TOP 30', '/prezenty-top-30/'),
                new MarketingCategory(398, 'Nowości', '/prezenty-nowosci/'),
            ],
            $homeData->marketingCategories,
        );
    }

    public function test_to_array_round_trips(): void
    {
        $this->assertSame(self::payload(), HomeData::fromArray(self::payload())->toArray());
    }

    public function test_marketing_category_finds_a_category_by_id(): void
    {
        $homeData = HomeData::fromArray(self::payload());

        $this->assertSame('Nowości', $homeData->marketingCategory(398)?->title);
        $this->assertNull($homeData->marketingCategory(1));
    }

    public function test_categories_can_be_empty(): void
    {
        $this->assertSame([], HomeData::fromArray(['marketingCategories' => []])->marketingCategories);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_from_array_rejects_an_invalid_payload(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        HomeData::fromArray($payload);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        $category = ['id' => 442, 'title' => 'TOP 30', 'url' => '/prezenty-top-30/'];

        return [
            'missing categories' => [[]],
            'categories not a list' => [['marketingCategories' => ['442' => $category]]],
            'category not an object' => [['marketingCategories' => [442]]],
            'string id' => [['marketingCategories' => [[...$category, 'id' => '442']]]],
            'zero id' => [['marketingCategories' => [[...$category, 'id' => 0]]]],
            'missing title' => [['marketingCategories' => [[...$category, 'title' => null]]]],
            'absolute url' => [['marketingCategories' => [[...$category, 'url' => 'https://www.emoti.test/top/']]]],
            'missing url' => [['marketingCategories' => [[...$category, 'url' => null]]]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return [
            'marketingCategories' => [
                ['id' => 442, 'title' => 'TOP 30', 'url' => '/prezenty-top-30/'],
                ['id' => 398, 'title' => 'Nowości', 'url' => '/prezenty-nowosci/'],
            ],
        ];
    }
}
