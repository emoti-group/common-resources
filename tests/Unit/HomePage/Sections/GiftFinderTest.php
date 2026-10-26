<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage\Sections;

use Emoti\CommonResources\HomePage\Sections\GiftFinder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * GiftFinder has only the shared fields, so this test also covers the rules of SectionFields.
 */
final class GiftFinderTest extends TestCase
{
    private const WARSAW = '9b2f6a2e-1c4d-4c8e-9a51-0d6f1e2b3c4d';

    public function test_to_array_round_trips(): void
    {
        $this->assertSame(self::payload(), GiftFinder::fromArray(self::payload())->toArray());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_from_array_rejects_an_invalid_payload(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        GiftFinder::fromArray($payload);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        $payload = self::payload();

        return [
            'city ids not a list' => [[...$payload, 'cityIds' => ['warsaw' => self::WARSAW]]],
            'city id not a UUID' => [[...$payload, 'cityIds' => ['Warszawa']]],
            'duplicate city id' => [[...$payload, 'cityIds' => [self::WARSAW, self::WARSAW]]],
            'string category id' => [[...$payload, 'categoryIds' => ['442']]],
            'duplicate category id' => [[...$payload, 'categoryIds' => [442, 442]]],
            'labels not an object' => [[...$payload, 'labels' => 'home:gift_finder.search']],
            'empty label key' => [[...$payload, 'labels' => ['search' => '']]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return [
            'cityIds' => [self::WARSAW],
            'categoryIds' => [170, 442],
            'labels' => ['search' => 'home:gift_finder.search'],
        ];
    }
}
