<?php

use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Report;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakePaymentGateway();
    app()->instance(PaymentGateway::class, $this->gateway);
});

function pendingOrder(User $user, int $items = 2): Order
{
    $order = Order::factory()->create(['user_id' => $user->id, 'total_pence' => 2500 * $items]);

    for ($i = 0; $i < $items; $i++) {
        $report = Report::factory()->pir()->create();
        $issue = Issue::factory()->for($report)->create(['is_current' => true]);
        OrderItem::factory()->create(['order_id' => $order->id, 'issue_id' => $issue->id]);
        BasketItem::create(['user_id' => $user->id, 'report_id' => $report->id]);
    }

    return $order->fresh('items');
}

it('fulfils a pending order: entitlements per item, basket cleared, status fulfilled', function () {
    $user = User::factory()->create();
    $order = pendingOrder($user);

    $this->gateway->nextWebhookEvent = new WebhookEvent('checkout.session.completed', $order->id, 'pi_123');

    $this->postJson(route('webhooks.stripe'))->assertOk();

    $order->refresh();
    expect($order->status)->toBe('fulfilled')
        ->and($order->stripe_payment_intent_id)->toBe('pi_123')
        ->and(Entitlement::where('user_id', $user->id)->count())->toBe(2)
        ->and(BasketItem::where('user_id', $user->id)->count())->toBe(0);

    $entitlement = Entitlement::first();
    expect($entitlement->expires_at->isAfter(now()->addMonths(11)))->toBeTrue()
        ->and($entitlement->order_item_id)->not->toBeNull();
});

it('ignores duplicate deliveries without creating duplicate entitlements', function () {
    $user = User::factory()->create();
    $order = pendingOrder($user);

    $this->gateway->nextWebhookEvent = new WebhookEvent('checkout.session.completed', $order->id, 'pi_123');
    $this->postJson(route('webhooks.stripe'))->assertOk();
    $this->postJson(route('webhooks.stripe'))->assertOk();

    expect(Entitlement::count())->toBe(2)
        ->and($order->fresh()->status)->toBe('fulfilled');
});

it('rejects an invalid payload with 400 and writes nothing', function () {
    $this->gateway->nextWebhookEvent = null;

    $this->postJson(route('webhooks.stripe'))->assertStatus(400);

    expect(Entitlement::count())->toBe(0);
});

it('no-ops on an unknown order id with 200', function () {
    $this->gateway->nextWebhookEvent = new WebhookEvent('checkout.session.completed', 999999, 'pi_x');

    $this->postJson(route('webhooks.stripe'))->assertOk();

    expect(Entitlement::count())->toBe(0);
});

it('ignores unrelated event types', function () {
    $user = User::factory()->create();
    $order = pendingOrder($user);

    $this->gateway->nextWebhookEvent = new WebhookEvent('invoice.paid', $order->id, 'pi_123');

    $this->postJson(route('webhooks.stripe'))->assertOk();

    expect($order->fresh()->status)->toBe('pending')
        ->and(Entitlement::count())->toBe(0);
});

it('shows live status on the success page without granting anything', function () {
    $user = User::factory()->create();
    $order = pendingOrder($user);

    $this->actingAs($user)
        ->get(route('checkout.success', $order))
        ->assertOk()
        ->assertSee('Payment processing');

    expect(Entitlement::count())->toBe(0);

    $this->gateway->nextWebhookEvent = new WebhookEvent('checkout.session.completed', $order->id, 'pi_123');
    $this->postJson(route('webhooks.stripe'));

    $this->actingAs($user)
        ->get(route('checkout.success', $order))
        ->assertSee('Payment confirmed');
});

it('blocks other users from the success page', function () {
    $order = pendingOrder(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('checkout.success', $order))
        ->assertForbidden();
});
