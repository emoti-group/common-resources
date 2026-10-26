<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage;

use DateTimeImmutable;
use Emoti\CommonResources\HomePage\HomePageSnapshot;
use Emoti\CommonResources\HomePage\HomePageType;
use Emoti\CommonResources\Enums\Lang;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HomePageSnapshotTest extends TestCase
{
    public function test_from_array_reads_every_field(): void
    {
        $snapshot = HomePageSnapshot::fromArray(self::payload());

        $this->assertSame(HomePageType::Home, $snapshot->type);
        $this->assertSame(Lang::EE, $snapshot->locale);
        $this->assertSame(3, $snapshot->version);
        $this->assertSame(1, $snapshot->schemaVersion);
        $this->assertSame(['title' => 'Kingitused', 'noindex' => false], $snapshot->settings);
        $this->assertSame([['key' => 'hero', 'type' => 'hero_banners', 'data' => ['slides' => []]]], $snapshot->sections);
        $this->assertEquals(new DateTimeImmutable('2026-10-21T10:15:00+00:00'), $snapshot->publishedAt);
        $this->assertSame('Jane Editor', $snapshot->publishedBy);
    }

    public function test_to_array_round_trips(): void
    {
        $this->assertSame(self::payload(), HomePageSnapshot::fromArray(self::payload())->toArray());
    }

    public function test_publisher_and_settings_can_be_empty(): void
    {
        $snapshot = HomePageSnapshot::fromArray([...self::payload(), 'settings' => [], 'sections' => [], 'publishedBy' => null]);

        $this->assertSame([], $snapshot->settings);
        $this->assertSame([], $snapshot->sections);
        $this->assertNull($snapshot->publishedBy);
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('invalidPayloads')]
    public function test_from_array_rejects_an_invalid_payload(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);

        HomePageSnapshot::fromArray([...self::payload(), ...$changes]);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'unknown type' => [['type' => 'landing']],
            'unknown locale' => [['locale' => 'en']],
            'zero version' => [['version' => 0]],
            'string version' => [['version' => '3']],
            'missing schema version' => [['schemaVersion' => null]],
            'settings not an array' => [['settings' => 'x']],
            'sections not a list' => [['sections' => ['hero' => ['key' => 'hero', 'type' => 'x', 'data' => []]]]],
            'section without data' => [['sections' => [['key' => 'hero', 'type' => 'x']]]],
            'invalid date' => [['publishedAt' => 'yesterday-ish']],
            'publisher not a string' => [['publishedBy' => 5]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return [
            'type' => 'home',
            'locale' => 'ee',
            'version' => 3,
            'schemaVersion' => 1,
            'settings' => ['title' => 'Kingitused', 'noindex' => false],
            'sections' => [['key' => 'hero', 'type' => 'hero_banners', 'data' => ['slides' => []]]],
            'publishedAt' => '2026-10-21T10:15:00+00:00',
            'publishedBy' => 'Jane Editor',
        ];
    }
}
