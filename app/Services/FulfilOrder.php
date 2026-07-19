<?php

namespace App\Services;

use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class FulfilOrder
{
    /**
     * Idempotently fulfil a paid order: one entitlement per item (12-month
     * window), clear the purchased reports from the buyer's basket, mark the
     * order fulfilled. Anything but a pending order is a no-op.
     */
    public function handle(int $orderId, ?string $paymentIntentId): void
    {
        DB::transaction(function () use ($orderId, $paymentIntentId) {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if ($order === null || $order->status !== 'pending') {
                return;
            }

            $order->update(['status' => 'paid', 'stripe_payment_intent_id' => $paymentIntentId]);

            $order->load('items.issue');

            foreach ($order->items as $item) {
                Entitlement::create([
                    'user_id' => $order->user_id,
                    'issue_id' => $item->issue_id,
                    'order_item_id' => $item->id,
                    'expires_at' => now()->addMonths(12),
                ]);
            }

            BasketItem::where('user_id', $order->user_id)
                ->whereIn('report_id', $order->items->pluck('issue.report_id'))
                ->delete();

            $order->update(['status' => 'fulfilled']);
        });
    }
}
