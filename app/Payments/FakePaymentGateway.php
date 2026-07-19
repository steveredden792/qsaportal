<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use RuntimeException;

class FakePaymentGateway implements PaymentGateway
{
    /** @var array<int, Order> */
    public array $checkoutSessions = [];

    /** @var array<int, OrderItem> */
    public array $refundedItems = [];

    public bool $failRefunds = false;

    public ?WebhookEvent $nextWebhookEvent = null;

    public function checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string
    {
        $this->checkoutSessions[] = $order;
        $order->update(['stripe_checkout_session_id' => 'cs_fake_'.$order->id]);

        return 'https://checkout.stripe.test/session/cs_fake_'.$order->id;
    }

    public function webhookEvent(Request $request): ?WebhookEvent
    {
        return $this->nextWebhookEvent;
    }

    public function refundItem(OrderItem $item): void
    {
        if ($this->failRefunds) {
            throw new RuntimeException('Fake gateway: refund failed');
        }

        $this->refundedItems[] = $item;
    }
}
