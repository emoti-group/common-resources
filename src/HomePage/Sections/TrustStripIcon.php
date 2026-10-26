<?php

declare(strict_types=1);

namespace Emoti\CommonResources\HomePage\Sections;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

/**
 * The icons of the home page trust strip. They cover the agcore stamp icon classes (e_stamps.class).
 * ee-ru uses older glyphs with the same meaning. They map to the same icons.
 */
enum TrustStripIcon: string
{
    use ArrayableEnumTrait;

    case Exchange = 'exchange';
    case Shield = 'shield';
    case Pig = 'pig';
    case Time = 'time';
    case Gift = 'gift';
    case Globe = 'globe';

    private const AGCORE_CLASSES = [
        'web_icons_exchange' => self::Exchange,
        'web_icons_shield' => self::Shield,
        'web_icons_pig' => self::Pig,
        'web_icons_time' => self::Time,
        'web_icons_gift' => self::Gift,
        'web_icons_globe' => self::Globe,
        'synchronize-3' => self::Exchange,
        'check-shield' => self::Shield,
        'piggy-bank' => self::Pig,
        'calendar-refresh-1' => self::Time,
    ];

    /**
     * Maps an agcore stamp icon class, for example "web_icons_gift", to its icon.
     * An empty class is the pig, because agcore shows "icon-piggy-bank" for it (stamp.tpl).
     */
    public static function tryFromAgcoreClass(string $class): ?self
    {
        $class = strtolower(trim($class));

        if ($class === '') {
            return self::Pig;
        }

        return self::AGCORE_CLASSES[$class] ?? null;
    }

    /**
     * The sold-gifts counter shows on the item with this icon, as on agcore (StampWidget).
     */
    public function showsSoldGiftsCount(): bool
    {
        return $this === self::Gift;
    }
}
