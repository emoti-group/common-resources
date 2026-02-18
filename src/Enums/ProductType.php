<?php

namespace Emoti\CommonResources\Enums;

enum ProductType: string
{
    case ACTIVITY = 'activity';
    case ACTIVITY_LIST = 'activity_list';
    case ACTIVITY_LIST_MPV = 'activity_list_mpv';
    case MULTIGIFT = 'multigift';
    case MULTIFUNCTIONAL_GIFT = 'multifunctional_gift';
    case GIFTCARD_CONTAINER = 'giftcard_container';
    case GIFTCARD = 'giftcard';
    case GIFTCARD_CONTAINER_MPV = 'giftcard_container_mpv';
    case GIFTCARD_MPV = 'giftcard_mpv';
    case HOTEL_EXPRESS = 'hotel_express';
    case ACCESSORIES = 'accessories';
}