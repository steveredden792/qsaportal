<?php

use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Services\RefundOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakePaymentGateway();
    app()->instance(PaymentGateway::class, $this->gateway);
});

function fulfilledOrderWithItems(int $count = 2): Order
{
    $order = Order::factory()->fulfilled()->create(['total_pence' => 2500 * $count]);

    OrderItem::factory($count)->create(['order_id' => $order->id])
        ->each(fn (OrderItem $item) => Entitlement::factory()->create([
            'user_id' => $order->user_id,
            'issue_id' => $item->issue_id,
            'order_item_id' => $item->id,
        ]));

    return $order->fresh('items');
}

it('refunds one item, revoking only its entitlement', function () {
    $order = fulfilledOrderWithItems(2);
    [$first, $second] = $order->items;

    app(RefundOrderItem::class)->handle($first);

    expect($first->fresh()->refunded_at)->not->toBeNull()
        ->and($first->entitlement->fresh()->revoked_at)->not->toBeNull()
        ->and($second->fresh()->refunded_at)->toBeNull()
        ->and($second->entitlement->fresh()->revoked_at)->toBeNull()
        ->and($order->fresh()->status)->toBe('fulfilled')
        ->and($this->gateway->refundedItems)->toHaveCount(1);
});

it('flips the order to refunded when the last item is refunded', function () {
    $order = fulfilledOrderWithItems(2);

    $order->items->each(fn (OrderItem $item) => app(RefundOrderItem::class)->handle($item));

    expect($order->fresh()->status)->toBe('refunded');
});

it('leaves everything intact when the gateway throws', function () {
    $order = fulfilledOrderWithItems(1);
    $this->gateway->failRefunds = true;
    $item = $order->items->first();

    expect(fn () => app(RefundOrderItem::class)->handle($item))->toThrow(RuntimeException::class);

    expect($item->fresh()->refunded_at)->toBeNull()
        ->and($item->entitlement->fresh()->revoked_at)->toBeNull()
        ->and($order->fresh()->status)->toBe('fulfilled');
});

it('rejects already-refunded items and non-fulfilled orders', function () {
    $order = fulfilledOrderWithItems(1);
    $item = $order->items->first();
    app(RefundOrderItem::class)->handle($item);

    expect(fn () => app(RefundOrderItem::class)->handle($item->fresh()))
        ->toThrow(InvalidArgumentException::class);

    $pendingItem = OrderItem::factory()->create();
    expect(fn () => app(RefundOrderItem::class)->handle($pendingItem))
        ->toThrow(InvalidArgumentException::class);
});
