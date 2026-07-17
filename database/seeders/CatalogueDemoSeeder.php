<?php

namespace Database\Seeders;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Charity;
use App\Models\Issue;
use App\Models\Report;
use Illuminate\Database\Seeder;

class CatalogueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $charities = Charity::factory(60)->create();

        $charities->each(function (Charity $charity) {
            $report = Report::factory()->far()->for($charity)->create([
                'name' => $charity->name.' — Financial Analysis Report',
                'slug' => 'far-'.$charity->cc_ref,
            ]);
            Issue::factory()->for($report)->create([
                'version_label' => '2026 H1',
                'is_current' => true,
                'q_score' => $charity->latest_q_score,
                'stability' => $charity->latest_stability,
            ]);
        });

        Issue::where('is_current', true)->each(function (Issue $issue) {
            Asset::factory()->for($issue)->create([
                'type' => AssetType::Teaser,
                'disk' => 's3',
                'path' => 'teasers/sample-teaser.pdf',
                'original_filename' => 'sample-teaser.pdf',
                'mime' => 'application/pdf',
            ]);
        });
    }
}
