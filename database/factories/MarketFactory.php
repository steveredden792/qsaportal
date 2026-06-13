<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MarketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'MKT-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->words(3, true),
        ];
    }
}
