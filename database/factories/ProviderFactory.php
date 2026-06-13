<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'PRV-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->company(),
        ];
    }
}
