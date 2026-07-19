<?php

namespace Database\Seeders;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Charity;
use App\Models\FarPirReference;
use App\Models\Issue;
use App\Models\Provider;
use App\Models\Report;
use Illuminate\Database\Seeder;

class CatalogueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $charities = Charity::factory(60)->create();

        $charities->each(function (Charity $charity) {
            $report = Report::factory()->pir()->create([
                'charity_id' => $charity->id,
                'name' => $charity->name.' — Public Information Report',
                'slug' => 'pir-'.$charity->cc_ref,
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

        Provider::factory(5)->create()->each(function (Provider $provider) use ($charities) {
            $report = Report::factory()->far()->for($provider)->create([
                'name' => $provider->name.' — Financial Analysis Report',
                'slug' => 'far-'.\Illuminate\Support\Str::slug($provider->code),
                'tier' => fake()->randomElement(config('reports.far_tiers')),
            ]);
            $issue = Issue::factory()->for($report)->create([
                'version_label' => '2026 H1',
                'is_current' => true,
                'q_score' => null,
                'stability' => null,
            ]);
            $charities->random(4)->each(fn ($c) => FarPirReference::firstOrCreate([
                'issue_id' => $issue->id,
                'charity_id' => $c->id,
            ]));
        });
    }
}
