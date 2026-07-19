<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntitlementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'issue_id' => Issue::factory(),
            'order_item_id' => OrderItem::factory(),
            'expires_at' => now()->addMonths(12),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expiring(): static
    {
        return $this->state(fn () => ['expires_at' => now()->addDays(10)]);
    }
}
