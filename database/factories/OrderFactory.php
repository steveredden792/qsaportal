<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_pence' => 2500,
        ];
    }

    public function fulfilled(): static
    {
        return $this->state(fn () => [
            'status' => 'fulfilled',
            'stripe_payment_intent_id' => 'pi_fake_'.fake()->unique()->numberBetween(1000, 999999),
        ]);
    }
}
