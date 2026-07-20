<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the orders list and detail pages for an authenticated admin', function () {
    // Filament's Authenticate middleware allows all users in the local
    // environment when the User model doesn't implement FilamentUser.
    config(['app.env' => 'local']);

    $order = Order::factory()->fulfilled()->create();
    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->actingAs(User::factory()->create())
        ->get('/admin/orders')
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get("/admin/orders/{$order->id}")
        ->assertOk();
});
