<?php

namespace Database\Factories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory()->pir(),
            'version_label' => fake()->unique()->numerify('20## H#'),
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'is_current' => true,
            'q_score' => fake()->randomFloat(2, 20, 70),
            'stability' => fake()->randomFloat(2, 15, 85),
        ];
    }
}
