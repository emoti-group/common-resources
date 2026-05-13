<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use Emoti\CommonResources\Enums\CloudflareCacheTag;
use Emoti\CommonResources\Enums\Site;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CloudflareCacheTagTest extends TestCase
{
    public function test_build_site_tag_returns_site_prefixed_value(): void
    {
        $this->assertSame('site:pl', CloudflareCacheTag::SITE->build(Site::PL));
    }

    public function test_build_tagged_cache_key_with_value(): void
    {
        $this->assertSame('pl_pro:42', CloudflareCacheTag::PRODUCT_ID->build(Site::PL, 42));
    }

    public function test_build_without_value_throws_for_non_site_types(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CloudflareCacheTag::PRODUCT_ID->build(Site::PL, null);
    }
}
