<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage\Sections;

use Emoti\CommonResources\HomePage\Sections\ProductCarousel;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductCarouselTest extends TestCase
{
    public function test_to_array_round_trips(): void
    {
        $this->assertSame(self::payload(), ProductCarousel::fromArray(self::payload())->toArray());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_from_array_rejects_an_invalid_payload(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProductCarousel::fromArray($payload);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        $payload = self::payload();

        return [
            'zero products' => [[...$payload, 'productCount' => 0]],
            '31 products' => [[...$payload, 'productCount' => 31]],
            'blank GA list name' => [[...$payload, 'gaListName' => ' ']],
            'missing highlighted' => [[...$payload, 'highlighted' => null]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return [
            'categoryId' => 442,
            'productCount' => 10,
            'gaListName' => 'Home page: TOP 30',
            'highlighted' => true,
            'labels' => ['seeMore' => 'common:show_more'],
        ];
    }
}
