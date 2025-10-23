<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

enum CloudflareCacheTag: string
{
    case SITE = 'site';
    case PRODUCT_ID = 'product_id';
    case GENERAL_CATEGORY_ID = 'general_category_id';
    case MARKETING_CATEGORY_ID = 'marketing_category_id';
    case LOCATION_ID = 'location_id';
    case SUPPLIER_ID = 'supplier_id';
}
