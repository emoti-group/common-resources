<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage\Sections;

use Emoti\CommonResources\HomePage\Sections\GiftFilter;
use PHPUnit\Framework\TestCase;

final class GiftFilterTest extends TestCase
{
    public function test_to_array_round_trips(): void
    {
        $payload = [
            'cityIds' => [],
            'categoryIds' => [170],
            'labels' => ['showResults' => 'home:gift_filter.show_results'],
        ];

        $this->assertSame($payload, GiftFilter::fromArray($payload)->toArray());
    }
}
