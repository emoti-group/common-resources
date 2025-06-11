<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use InvalidArgumentException;

enum Site: string
{
    case PL = 'pl';
    case EE = 'ee';
    case LT = 'lt';
    case LV = 'lv';
    case FI = 'fi';

    public static function fromLongNameUnderscoreCode(string $name): self
    {
        return match ($name) {
            'wyjatkowyprezent_pl' => self::PL,
            'laisvalaikiodovanos_lt' => self::LT,
            'davanuserviss_lv' => self::LV,
            'kingitus_ee' => self::EE,
            'elamyslahjat_fi' => self::FI,
            default => throw new InvalidArgumentException("Unknown project name: $name"),
        };
    }

    public static function fromLongNameDotCode(string $name): self
    {
        return match ($name) {
            'wyjatkowyprezent.pl' => self::PL,
            'laisvalaikiodovanos.lt' => self::LT,
            'davanuserviss.lv' => self::LV,
            'kingitus.ee' => self::EE,
            'elamyslahjat.fi' => self::FI,
            default => throw new InvalidArgumentException("Unknown project name: $name"),
        };
    }

    public static function fromShortNameUnderscoreCode(string $name): self
    {
        return match ($name) {
            'wp_pl' => self::PL,
            'ld_lt' => self::LT,
            'ds_lv' => self::LV,
            'kg_ee' => self::EE,
            'el_fi' => self::FI,
            default => throw new InvalidArgumentException("Unknown project name: $name"),
        };
    }
}
