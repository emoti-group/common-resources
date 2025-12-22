<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

enum FeatureFlag: string
{
    case RedisCache = 'redis-cache';
    case RequirePostData = 'require-post-data';
    case LevelFilters = 'level-filters';
    case NewListingTestV2 = 'new_listing_test_v2';
}
