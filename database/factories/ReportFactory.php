<?php

namespace Database\Factories;

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\Market;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ReportType::FAR,
            'charity_id' => Charity::factory(),
            'provider_id' => null,
            'market_id' => null,
            'name' => fake()->company().' Report',
            'slug' => fake()->unique()->slug(),
        ];
    }

    public function far(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::FAR,
            'provider_id' => null,
            'market_id' => null,
        ]);
    }

    public function ppr(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::PPR,
            'charity_id' => null,
            'market_id' => null,
        ]);
    }

    public function pmr(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::PMR,
            'charity_id' => null,
            'provider_id' => null,
        ]);
    }
}
