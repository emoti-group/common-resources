<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

enum LocationType: string
{
    case CITY = 'city';
    case ADMINISTRATIVE_REGION = 'administrative_region';
    case CUSTOM_REGION = 'custom_region';
}
