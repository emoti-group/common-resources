<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

/**
 * The authoritative payment state of an order, as the payment gateway service reports it on
 * `GET /api/external/{siteUuid}/order/{orderId}/payment-status`.
 *
 * This is a wire contract between the payment gateway service and its callers, not a database
 * column: it collapses the gateway's per-payment statuses into the one answer a caller needs
 * before it offers a payment retry. NONE is a deliberate answer — "no payment holds this order" —
 * and never means "unknown".
 */
enum OrderPaymentStatus: string
{
    case SUCCESS = 'success';
    case PROCESSING = 'processing';
    case NONE = 'none';
}
