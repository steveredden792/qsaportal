<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\Issue;
use App\Models\Report;
use Database\Seeders\CatalogueDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds charity catalogue data with current issues and teasers', function () {
    $this->seed(CatalogueDemoSeeder::class);

    expect(Charity::count())->toBe(60)
        ->and(Report::where('type', ReportType::PIR)->count())->toBe(60)
        ->and(Issue::where('is_current', true)->count())->toBe(60);

    $report = Report::where('type', ReportType::PIR)->first();
    expect($report->slug)->toStartWith('pir-')
        ->and($report->currentIssue)->not->toBeNull();
});
