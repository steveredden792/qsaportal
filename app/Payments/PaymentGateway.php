<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** Create a hosted checkout session for the order (one line per order item); returns the redirect URL. */
    public function checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string;

    /** Verify and parse an incoming webhook request; null when the signature or payload is invalid. */
    public function webhookEvent(Request $request): ?WebhookEvent;

    /** Refund one order item's amount against the order's payment. Throws on gateway failure. */
    public function refundItem(OrderItem $item): void;
}
