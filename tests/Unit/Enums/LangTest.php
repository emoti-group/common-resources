<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use Emoti\CommonResources\Enums\Lang;
use Emoti\CommonResources\Enums\Site;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LangTest extends TestCase
{
    /**
     * @param list<Lang> $expectedLangs
     */
    #[DataProvider('siteToLangsProvider')]
    public function test_from_site_returns_correct_langs_for_each_site(Site $site, array $expectedLangs): void
    {
        $this->assertSame($expectedLangs, Lang::fromSite($site));
    }

    public static function siteToLangsProvider(): array
    {
        return [
            'PL' => [Site::PL, [Lang::PL]],
            'EE' => [Site::EE, [Lang::EE, Lang::RU]],
            'LT' => [Site::LT, [Lang::LT]],
            'LV' => [Site::LV, [Lang::LV, Lang::RU]],
            'FI' => [Site::FI, [Lang::FI]],
        ];
    }

    public function test_values_returns_all_raw_values(): void
    {
        $values = Lang::values();
        sort($values);

        $this->assertSame(['ee', 'fi', 'lt', 'lv', 'pl', 'ru'], $values);
    }

    public function test_iso_code_is_et_for_estonian(): void
    {
        $this->assertSame('et', Lang::EE->isoCode());
    }

    #[DataProvider('passThroughLangsProvider')]
    public function test_iso_code_equals_value_for_other_langs(Lang $lang): void
    {
        $this->assertSame($lang->value, $lang->isoCode());
    }

    /** @return list<array{Lang}> */
    public static function passThroughLangsProvider(): array
    {
        $cases = array_filter(Lang::cases(), static fn (Lang $lang): bool => $lang !== Lang::EE);

        return array_map(static fn (Lang $lang): array => [$lang], array_values($cases));
    }
}
