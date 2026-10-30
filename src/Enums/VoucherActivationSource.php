<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

/**
 * Where a customer activated a voucher with an SMS code. gifts-api sends it, agcore stores it.
 */
enum VoucherActivationSource: string
{
    use ArrayableEnumTrait;

    case BASKET = 'basket';
    case ENTER_CODE_PAGE = 'enter_code_page';
    case OFFLINE_SALES = 'offline_sales';
}
