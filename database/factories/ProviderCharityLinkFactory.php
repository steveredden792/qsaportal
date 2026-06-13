<?php

namespace Database\Factories;

use App\Models\Charity;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderCharityLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'charity_id' => Charity::factory(),
        ];
    }
}
