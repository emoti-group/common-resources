<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use Emoti\CommonResources\Enums\Site;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteTest extends TestCase
{
    #[DataProvider('longNameUnderscoreCodeProvider')]
    public function test_from_long_name_underscore_code_maps_all_cases(string $code, Site $expected): void
    {
        $this->assertSame($expected, Site::fromLongNameUnderscoreCode($code));
    }

    public static function longNameUnderscoreCodeProvider(): array
    {
        return [
            ['wyjatkowyprezent_pl', Site::PL],
            ['laisvalaikiodovanos_lt', Site::LT],
            ['davanuserviss_lv', Site::LV],
            ['kingitus_ee', Site::EE],
            ['elamyslahjat_fi', Site::FI],
        ];
    }

    #[DataProvider('longNameDotCodeProvider')]
    public function test_from_long_name_dot_code_maps_all_cases(string $code, Site $expected): void
    {
        $this->assertSame($expected, Site::fromLongNameDotCode($code));
    }

    public static function longNameDotCodeProvider(): array
    {
        return [
            ['wyjatkowyprezent.pl', Site::PL],
            ['laisvalaikiodovanos.lt', Site::LT],
            ['davanuserviss.lv', Site::LV],
            ['kingitus.ee', Site::EE],
            ['elamyslahjat.fi', Site::FI],
        ];
    }

    #[DataProvider('shortNameUnderscoreCodeProvider')]
    public function test_from_short_name_underscore_code_maps_all_cases(string $code, Site $expected): void
    {
        $this->assertSame($expected, Site::fromShortNameUnderscoreCode($code));
    }

    public static function shortNameUnderscoreCodeProvider(): array
    {
        return [
            ['wp_pl', Site::PL],
            ['ld_lt', Site::LT],
            ['ds_lv', Site::LV],
            ['kg_ee', Site::EE],
            ['el_fi', Site::FI],
        ];
    }

    public function test_unknown_code_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Site::fromLongNameUnderscoreCode('unknown_code');
    }

    public function test_unknown_dot_code_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Site::fromLongNameDotCode('unknown.code');
    }

    public function test_unknown_short_code_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Site::fromShortNameUnderscoreCode('unknown_code');
    }
}
