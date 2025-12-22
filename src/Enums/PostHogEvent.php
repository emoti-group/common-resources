<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

enum PostHogEvent: string
{
    case EXCEPTION = '$exception';
    case PAGEVIEW = '$pageview';
}
