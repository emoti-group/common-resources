<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use Emoti\CommonResources\DTO\HomePageProductCarousel;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HomePageProductCarouselTest extends TestCase
{
    public function test_from_array_reads_every_field(): void
    {
        $this->assertEquals(
            new HomePageProductCarousel(442, 10, 'Home page: TOP 30', true, ['seeMore' => 'common:show_more']),
            HomePageProductCarousel::fromArray(self::payload()),
        );
    }

    public function test_to_array_round_trips(): void
    {
        $this->assertSame(self::payload(), HomePageProductCarousel::fromArray(self::payload())->toArray());
    }

    public function test_labels_can_be_empty(): void
    {
        $this->assertSame([], HomePageProductCarousel::fromArray([...self::payload(), 'labels' => []])->labels);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_from_array_rejects_an_invalid_payload(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        HomePageProductCarousel::fromArray($payload);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        $payload = self::payload();

        return [
            'missing category id' => [[...$payload, 'categoryId' => null]],
            'string category id' => [[...$payload, 'categoryId' => '442']],
            'zero category id' => [[...$payload, 'categoryId' => 0]],
            'zero products' => [[...$payload, 'productCount' => 0]],
            'too many products' => [[...$payload, 'productCount' => 31]],
            'empty GA list name' => [[...$payload, 'gaListName' => ' ']],
            'missing highlighted' => [[...$payload, 'highlighted' => null]],
            'labels not an object' => [[...$payload, 'labels' => 'common:show_more']],
            'empty label key' => [[...$payload, 'labels' => ['seeMore' => '']]],
            'label without a name' => [[...$payload, 'labels' => ['common:show_more']]],
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
