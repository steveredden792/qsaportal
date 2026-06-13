<?php

namespace Database\Factories;

use App\Enums\AssetType;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'type' => AssetType::ReportPdf,
            'disk' => 's3',
            'path' => 'far/'.fake()->numerify('#######').'/2026-h1/report.pdf',
            'original_filename' => 'report.pdf',
            'size' => fake()->numberBetween(50_000, 5_000_000),
            'mime' => 'application/pdf',
        ];
    }
}
