<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Config;

use Emoti\CommonResources\Support\Config\VanillaPHPConfig;
use PHPUnit\Framework\TestCase;

final class VanillaPHPConfigTest extends TestCase
{
    public function test_get_reads_nested_key_with_dot_notation(): void
    {
        $value = VanillaPHPConfig::get('rabbitmq.exchange');

        $this->assertSame('gifts', $value);
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $value = VanillaPHPConfig::get('nonexistent.key.xyz', 'fallback');

        $this->assertSame('fallback', $value);
    }

    public function test_get_returns_top_level_value(): void
    {
        $value = VanillaPHPConfig::get('env');

        $this->assertNotNull($value);
    }
}
