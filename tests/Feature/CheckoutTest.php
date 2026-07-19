<?php

use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->gateway = new FakePaymentGateway();
    app()->instance(PaymentGateway::class, $this->gateway);
});

function basketReport(User $user): Report
{
    $report = Report::factory()->pir()->create();
    Issue::factory()->for($report)->create(['is_current' => true]);
    BasketItem::create(['user_id' => $user->id, 'report_id' => $report->id]);

    return $report->fresh();
}

it('creates a pending order frozen to current issues and redirects to the gateway', function () {
    $a = basketReport($this->user);
    $b = basketReport($this->user);

    $response = $this->post(route('checkout.store'));

    $order = Order::sole();
    $response->assertRedirect('https://checkout.stripe.test/session/cs_fake_'.$order->id);

    expect($order->status)->toBe('pending')
        ->and($order->total_pence)->toBe(5000)
        ->and($order->items()->pluck('issue_id')->sort()->values()->all())
        ->toBe(collect([$a->currentIssue->id, $b->currentIssue->id])->sort()->values()->all())
        ->and($this->gateway->checkoutSessions)->toHaveCount(1);

    // Basket is NOT cleared at checkout — only on fulfilment.
    expect(BasketItem::where('user_id', $this->user->id)->count())->toBe(2);
});

it('rejects an empty basket', function () {
    $this->post(route('checkout.store'))
        ->assertRedirect(route('basket.show'));

    expect(Order::count())->toBe(0);
});

it('bounces back naming stale lines without creating anything', function () {
    $owned = basketReport($this->user);
    Entitlement::factory()->create([
        'user_id' => $this->user->id,
        'issue_id' => $owned->currentIssue->id,
    ]);
    basketReport($this->user);

    $response = $this->post(route('checkout.store'));

    $response->assertRedirect(route('basket.show'))
        ->assertSessionHas('error', fn (string $msg) => str_contains($msg, $owned->name));

    expect(Order::count())->toBe(0)
        ->and($this->gateway->checkoutSessions)->toBe([]);
});

it('treats a report without a current issue as stale', function () {
    $report = basketReport($this->user);
    $report->currentIssue->update(['is_current' => false]);

    $this->post(route('checkout.store'))->assertRedirect(route('basket.show'));

    expect(Order::count())->toBe(0);
});

it('requires authentication', function () {
    auth()->logout();

    $this->post(route('checkout.store'))->assertRedirect(route('login'));
});
