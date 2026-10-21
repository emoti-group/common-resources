<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

enum HomePageSectionType: string
{
    use ArrayableEnumTrait;

    case HeroBanners = 'hero_banners';
    case GiftFinder = 'gift_finder';
    case CategoryTiles = 'category_tiles';
    case ProductCarousel = 'product_carousel';
    case TrustStrip = 'trust_strip';
    case GiftFilter = 'gift_filter';
    case GiftCard = 'gift_card';
    case LinkColumns = 'link_columns';
    case Newsletter = 'newsletter';
    case SeoText = 'seo_text';
    case ImageTiles = 'image_tiles';
}
