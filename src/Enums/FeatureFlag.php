<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

enum FeatureFlag: string
{
    case RedisCache = 'redis-cache';
    case LevelFilters = 'level-filters';
    case NewListingTestV2 = 'new_listing_test_v2';
    case SelfService = 'self-service';
}
