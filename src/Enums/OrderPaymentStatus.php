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
    case UNSETTLED = 'unsettled';
    case NONE = 'none';

    /**
     * Whether this state forbids a new payment for the order.
     *
     * SUCCESS is a charge. UNSETTLED is a charge the provider has not confirmed: the customer
     * committed, so the order can still become paid. The name describes the state rather than any
     * one provider's word for it — a gateway may collapse several of its own statuses onto it.
     *
     * A payment that only reached the provider's page is not reported at all. Most gateways mark
     * such a payment as being in progress when they hand out the redirect url, before the customer
     * pays, so it collapses to NONE — otherwise one abandoned redirect would stop the customer from
     * changing the payment method.
     */
    public function blocksRetry(): bool
    {
        return match ($this) {
            self::SUCCESS, self::UNSETTLED => true,
            self::NONE => false,
        };
    }
}
