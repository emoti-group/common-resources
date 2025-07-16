<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use InvalidArgumentException;

enum Lang: string
{
    case ET = 'et';
    case FI = 'fi';
    case LT = 'lt';
    case LV = 'lv';
    case PL = 'pl';
    case RU = 'ru';

    /**
     * Returns an array of languages based on the provided site.
     *
     * @param Site $site The site for which to get the languages.
     * @return array<int, Lang> An array of Lang enums corresponding to the site.
     */
    public static function fromSite(Site $site): array
    {
        return match ($site) {
            Site::PL => [
                self::PL
            ],
            Site::EE => [
                self::ET,
                self::RU,
            ],
            Site::LT => [
                self::LT
            ],
            Site::LV => [
                self::LV,
                self::RU,
            ],
            Site::FI => [
                self::FI
            ],
        };
    }
}
