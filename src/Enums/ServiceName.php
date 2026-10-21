<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

enum ServiceName: string
{
    use ArrayableEnumTrait;

    case GiftsApi = 'gifts-api';
    case GiftsCms = 'gifts-cms';
}
