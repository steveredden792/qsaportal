<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'issue_id' => Issue::factory(),
            'amount_pence' => 2500,
        ];
    }
}
