<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

enum LocationType: string
{
    use ArrayableEnumTrait;
    
    case CITY = 'city';
    case ADMINISTRATIVE_REGION = 'administrative_region';
    case CUSTOM_REGION = 'custom_region';
}
