<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\FarPirReference;
use App\Models\Issue;
use App\Models\Report;
use Database\Seeders\CatalogueDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds charity catalogue data with current issues and teasers', function () {
    $this->seed(CatalogueDemoSeeder::class);

    expect(Charity::count())->toBe(60)
        ->and(Report::where('type', ReportType::PIR)->count())->toBe(60)
        ->and(Report::where('type', ReportType::FAR)->count())->toBe(5)
        ->and(Issue::where('is_current', true)->count())->toBe(65)
        ->and(FarPirReference::count())->toBe(20);

    $report = Report::where('type', ReportType::PIR)->first();
    expect($report->slug)->toStartWith('pir-')
        ->and($report->currentIssue)->not->toBeNull();
});
