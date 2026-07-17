<?php

namespace Database\Factories;

use App\Models\Charity;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

class FarPirReferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'charity_id' => Charity::factory(),
        ];
    }
}
