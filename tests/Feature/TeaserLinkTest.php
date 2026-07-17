<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function farReportWithTeaser(): array
{
    $report = Report::factory()->pir()->create(['slug' => 'pir-teaser-test']);
    $issue = Issue::factory()->for($report)->create(['is_current' => true]);
    $teaser = Asset::factory()->for($issue)->create(['type' => AssetType::Teaser]);

    return [$report, $teaser];
}

it('shows the sample link to an authenticated user when a teaser exists', function () {
    [$report, $teaser] = farReportWithTeaser();

    $this->actingAs(User::factory()->create())
        ->get('/reports/pir-teaser-test')
        ->assertOk()
        ->assertSee(route('assets.download', $teaser), false);
});

it('does not show the sample link to guests', function () {
    [$report, $teaser] = farReportWithTeaser();

    $this->get('/reports/pir-teaser-test')
        ->assertOk()
        ->assertDontSee(route('assets.download', $teaser), false);
});
