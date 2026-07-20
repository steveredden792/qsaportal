<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RefundOrderItem
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /**
     * Refund one item of a fulfilled order and revoke its entitlement.
     * The gateway call happens first — if it throws, nothing is revoked.
     * Refunding the last unrefunded item flips the order to `refunded`.
     */
    public function handle(OrderItem $item): void
    {
        if ($item->refunded_at !== null || $item->order->status !== 'fulfilled') {
            throw new InvalidArgumentException('Only unrefunded items of fulfilled orders can be refunded.');
        }

        $this->gateway->refundItem($item);

        DB::transaction(function () use ($item) {
            $item->update(['refunded_at' => now()]);
            $item->entitlement?->update(['revoked_at' => now()]);

            $order = $item->order->fresh('items');
            if ($order->items->every(fn (OrderItem $i) => $i->refunded_at !== null)) {
                $order->update(['status' => 'refunded']);
            }
        });
    }
}
