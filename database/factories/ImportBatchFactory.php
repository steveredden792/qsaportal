<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => '2026 H1',
            'type' => 'pir_index',
            'status' => 'pending',
        ];
    }
}
