<?php

namespace Database\Factories;

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ReportType::PIR,
            'charity_id' => Charity::factory(),
            'provider_id' => null,
            'name' => fake()->company().' Report',
            'slug' => fake()->unique()->slug(),
        ];
    }

    public function pir(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::PIR,
            'charity_id' => Charity::factory(),
            'provider_id' => null,
        ]);
    }

    public function far(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::FAR,
            'charity_id' => null,
            'provider_id' => Provider::factory(),
        ]);
    }
}
