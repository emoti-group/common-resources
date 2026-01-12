<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

enum PostHogEvent: string
{
    case EXCEPTION = '$exception';
    case PAGEVIEW = '$pageview';
    case CLIENT_LOGIN = 'client_login';
    case CMS_LOGIN = 'cms_login';
    case OFFLINESALES_LOGIN = 'offlinesales_login';
    case LOGOUT = 'logout';
    case CLIENT_LOGOUT = 'client_logout';
}
