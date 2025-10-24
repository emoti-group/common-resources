<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use InvalidArgumentException;

enum CloudflareCacheTag: string
{
    case SITE = 'site';
    case PRODUCT_ID = 'pro';
    case GENERAL_CATEGORY_ID = 'gcat';
    case MARKETING_CATEGORY_ID = 'mcat';
    case LOCATION_ID = 'loc';
    case SUPPLIER_ID = 'sup';

    public function build(Site $site, string|int|null $value = null): string
    {
        if ($this === self::SITE) {
            return sprintf('%s:%s', $this->value, $site->value);
        }

        if ($value === null) {
            throw new InvalidArgumentException('Value cannot be null for this tag type.');
        }

        return sprintf('%s_%s:%s', $site->value, $this->value, $value);
    }
}
