<?php

use App\Models\BasketItem;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enforces one basket line per user and report', function () {
    $user = User::factory()->create();
    $report = Report::factory()->pir()->create();

    BasketItem::create(['user_id' => $user->id, 'report_id' => $report->id]);

    expect(fn () => BasketItem::create(['user_id' => $user->id, 'report_id' => $report->id]))
        ->toThrow(QueryException::class);
});

it('creates an order with pending status and gbp currency by default', function () {
    $order = Order::factory()->create();

    expect($order->status)->toBe('pending')
        ->and($order->currency)->toBe('gbp')
        ->and($order->user)->toBeInstanceOf(User::class);
});

it('links order items to their order, issue and entitlement', function () {
    $item = OrderItem::factory()->create();
    $entitlement = Entitlement::factory()->create(['order_item_id' => $item->id]);

    expect($item->order)->toBeInstanceOf(Order::class)
        ->and($item->issue->id)->toBe($item->issue_id)
        ->and($item->entitlement->id)->toBe($entitlement->id);
});

it('scopes active entitlements excluding revoked and expired ones', function () {
    $user = User::factory()->create();
    $active = Entitlement::factory()->create(['user_id' => $user->id]);
    Entitlement::factory()->expired()->create(['user_id' => $user->id]);
    Entitlement::factory()->revoked()->create(['user_id' => $user->id]);

    expect($user->entitlements()->active()->pluck('id')->all())->toBe([$active->id]);
});

it('reports entitlement status with a 30-day expiring boundary', function () {
    expect(Entitlement::factory()->create()->status())->toBe('active')
        ->and(Entitlement::factory()->create(['expires_at' => now()->addDays(31)])->status())->toBe('active')
        ->and(Entitlement::factory()->create(['expires_at' => now()->addDays(30)])->status())->toBe('expiring')
        ->and(Entitlement::factory()->expiring()->create()->status())->toBe('expiring')
        ->and(Entitlement::factory()->expired()->create()->status())->toBe('expired')
        ->and(Entitlement::factory()->revoked()->create()->status())->toBe('expired');
});
