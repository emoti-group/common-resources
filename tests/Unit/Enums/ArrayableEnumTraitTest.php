<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use Emoti\CommonResources\Enums\Site;
use PHPUnit\Framework\TestCase;

final class ArrayableEnumTraitTest extends TestCase
{
    public function test_values_returns_all_raw_values(): void
    {
        $values = Site::values();

        $this->assertEqualsCanonicalizing(['pl', 'ee', 'lt', 'lv', 'fi'], $values);
    }

    public function test_names_returns_all_case_names(): void
    {
        $names = Site::names();

        $this->assertEqualsCanonicalizing(['PL', 'EE', 'LT', 'LV', 'FI'], $names);
    }

    public function test_array_returns_value_to_name_map(): void
    {
        $array = Site::array();

        $this->assertSame([
            'pl' => 'PL',
            'ee' => 'EE',
            'lt' => 'LT',
            'lv' => 'LV',
            'fi' => 'FI',
        ], $array);
    }
}
