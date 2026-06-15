<?php

namespace Database\Seeders;

use App\Models\Charity;
use App\Models\Issue;
use App\Models\Market;
use App\Models\Provider;
use App\Models\ProviderCharityLink;
use App\Models\Report;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        Provider::factory(8)->create()->each(function (Provider $provider) use ($charities) {
            $report = Report::factory()->ppr()->for($provider)->create([
                'name' => $provider->name.' — Provider Portfolio Report',
                'slug' => 'ppr-'.Str::lower($provider->code),
            ]);
            Issue::factory()->for($report)->create([
                'version_label' => '2026 H1',
                'is_current' => true,
                'q_score' => null,
                'stability' => null,
            ]);
            $charities->random(5)->each(fn (Charity $c) => ProviderCharityLink::firstOrCreate([
                'provider_id' => $provider->id,
                'charity_id' => $c->id,
            ]));
        });

        Market::factory(5)->create()->each(function (Market $market) {
            $report = Report::factory()->pmr()->for($market)->create([
                'name' => $market->name.' — Provider Market Report',
                'slug' => 'pmr-'.Str::lower($market->code),
            ]);
            Issue::factory()->for($report)->create([
                'version_label' => '2026 H1',
                'is_current' => true,
                'q_score' => null,
                'stability' => null,
            ]);
        });
    }
}
