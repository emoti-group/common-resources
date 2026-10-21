<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

enum HomePageType: string
{
    use ArrayableEnumTrait;

    case Home = 'home';
    case SelfServiceHome = 'self_service_home';

    public function isAvailableOn(Site $site): bool
    {
        return match ($this) {
            self::Home => true,
            self::SelfServiceHome => $site === Site::PL,
        };
    }

    /**
     * @return list<HomePageType>
     */
    public static function availableOn(Site $site): array
    {
        return array_values(array_filter(self::cases(), fn(self $type): bool => $type->isAvailableOn($site)));
    }
}
