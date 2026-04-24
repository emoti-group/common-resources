<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

enum ProductStatus: string
{
    case ACTIVE = 'active';
    case ACTIVE_FOR_MG = 'active_for_mg';
    case NOT_SHOWN = 'not_shown';
    case NOT_SHOWN_IN_LISTS = 'not_shown_in_lists';
    case DRAFT = 'draft';
    case NO_LONGER_PROVIDED = 'no_longer_provided';
    case DELETED = 'deleted';
}
