<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    public function checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string
    {
        $session = Cashier::stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $order->user->createOrGetStripeCustomer()->id,
            'line_items' => $order->items->map(fn (OrderItem $item) => [
                'price_data' => [
                    'currency' => $order->currency,
                    'unit_amount' => $item->amount_pence,
                    'product_data' => ['name' => $item->issue->report->name],
                ],
                'quantity' => 1,
            ])->values()->all(),
            'metadata' => ['order_id' => $order->id],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        $order->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }

    public function webhookEvent(Request $request): ?WebhookEvent
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('cashier.webhook.secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return null;
        }

        $object = $event->data->object;

        return new WebhookEvent(
            type: $event->type,
            orderId: isset($object->metadata->order_id) ? (int) $object->metadata->order_id : null,
            paymentIntentId: $object->payment_intent ?? null,
        );
    }

    public function refundItem(OrderItem $item): void
    {
        Cashier::stripe()->refunds->create([
            'payment_intent' => $item->order->stripe_payment_intent_id,
            'amount' => $item->amount_pence,
        ]);
    }
}
