<?php

declare(strict_types=1);

namespace Tests\Unit\HomePage\Sections;

use Emoti\CommonResources\HomePage\Sections\TrustStripIcon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrustStripIconTest extends TestCase
{
    #[DataProvider('agcoreClasses')]
    public function test_maps_every_agcore_stamp_class(string $class, TrustStripIcon $icon): void
    {
        $this->assertSame($icon, TrustStripIcon::tryFromAgcoreClass($class));
    }

    /**
     * @return array<string, array{string, TrustStripIcon}>
     */
    public static function agcoreClasses(): array
    {
        return [
            'exchange' => ['web_icons_exchange', TrustStripIcon::Exchange],
            'shield' => ['web_icons_shield', TrustStripIcon::Shield],
            'pig' => ['web_icons_pig', TrustStripIcon::Pig],
            'time' => ['web_icons_time', TrustStripIcon::Time],
            'gift' => ['web_icons_gift', TrustStripIcon::Gift],
            'globe' => ['web_icons_globe', TrustStripIcon::Globe],
            'upper case, as agcore lowers it' => [' WEB_ICONS_Gift ', TrustStripIcon::Gift],
            'ee-ru exchange' => ['synchronize-3', TrustStripIcon::Exchange],
            'ee-ru shield' => ['check-shield', TrustStripIcon::Shield],
            'ee-ru pig' => ['piggy-bank', TrustStripIcon::Pig],
            'ee-ru time' => ['calendar-refresh-1', TrustStripIcon::Time],
            'empty, the agcore piggy-bank fallback' => ['', TrustStripIcon::Pig],
        ];
    }

    public function test_an_unknown_class_maps_to_null(): void
    {
        $this->assertNull(TrustStripIcon::tryFromAgcoreClass('web_icons_like'));
        $this->assertNull(TrustStripIcon::tryFromAgcoreClass('gift'));
    }
}
