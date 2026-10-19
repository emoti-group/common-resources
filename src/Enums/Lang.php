<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

enum Lang: string
{
    use ArrayableEnumTrait;

    case EE = 'ee';
    case FI = 'fi';
    case LT = 'lt';
    case LV = 'lv';
    case PL = 'pl';
    case RU = 'ru';

    // Internal code is ee; external services get the ISO 639-1 code.
    public function isoCode(): string
    {
        return match ($this) {
            self::EE => 'et',
            default => $this->value,
        };
    }

    /**
     * @return non-empty-list<Lang>
     */
    public static function fromSite(Site $site): array
    {
        return match ($site) {
            Site::PL => [
                self::PL,
            ],
            Site::EE => [
                self::EE,
                self::RU,
            ],
            Site::LT => [
                self::LT,
            ],
            Site::LV => [
                self::LV,
                self::RU,
            ],
            Site::FI => [
                self::FI,
            ],
        };
    }
}
