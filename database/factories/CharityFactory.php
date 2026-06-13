<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CharityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cc_ref' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'name' => fake()->company().' Trust',
            'latest_q_score' => fake()->randomFloat(2, 20, 70),
            'latest_stability' => fake()->randomFloat(2, 15, 85),
        ];
    }
}
