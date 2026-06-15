<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\Issue;
use App\Models\Provider;
use App\Models\ProviderCharityLink;
use App\Models\Report;
use Database\Seeders\CatalogueDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds FAR, PPR and PMR catalogue data with current issues and links', function () {
    $this->seed(CatalogueDemoSeeder::class);

    expect(Charity::count())->toBe(60)
        ->and(Provider::count())->toBe(8)
        ->and(Report::where('type', ReportType::FAR)->count())->toBe(60)
        ->and(Report::where('type', ReportType::PPR)->count())->toBe(8)
        ->and(Report::where('type', ReportType::PMR)->count())->toBe(5)
        ->and(Issue::where('is_current', true)->count())->toBe(73)
        ->and(ProviderCharityLink::count())->toBe(40);

    $far = Report::where('type', ReportType::FAR)->first();
    expect($far->slug)->toStartWith('far-')
        ->and($far->currentIssue)->not->toBeNull();
});
