<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Services\FarIndexImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a charity, FAR report and current issue from a new row', function () {
    $batch = ImportBatch::factory()->create(['label' => '2026 H1']);

    (new FarIndexImporter)->import($batch, [
        ['cc_ref' => '1234567', 'name' => 'Acme Trust', 'q_score' => 55.5, 'stability' => 60.0],
    ]);

    $charity = Charity::where('cc_ref', '1234567')->first();
    expect($charity)->not->toBeNull()
        ->and($charity->name)->toBe('Acme Trust');

    $report = $charity->report;
    expect($report->type)->toBe(ReportType::PIR);

    $issue = $report->currentIssue;
    expect($issue->version_label)->toBe('2026 H1')
        ->and((float) $issue->q_score)->toBe(55.5);

    $batch->refresh();
    expect($batch->status)->toBe('completed')
        ->and($batch->rows)->toBe(1)
        ->and($batch->charities_created)->toBe(1)
        ->and($batch->issues_created)->toBe(1);
});

it('updates an existing charity and flips the current issue on a new batch', function () {
    (new FarIndexImporter)->import(
        ImportBatch::factory()->create(['label' => '2025 H2']),
        [['cc_ref' => '1234567', 'name' => 'Old Name', 'q_score' => 40.0, 'stability' => 50.0]],
    );

    $b2 = ImportBatch::factory()->create(['label' => '2026 H1']);
    (new FarIndexImporter)->import($b2, [
        ['cc_ref' => '1234567', 'name' => 'New Name', 'q_score' => 70.0, 'stability' => 80.0],
    ]);

    $charity = Charity::where('cc_ref', '1234567')->first();
    expect(Charity::count())->toBe(1)
        ->and($charity->name)->toBe('New Name')
        ->and((float) $charity->latest_q_score)->toBe(70.0);

    $report = $charity->report;
    expect($report->issues()->count())->toBe(2)
        ->and($report->currentIssue->version_label)->toBe('2026 H1');

    expect($b2->fresh()->charities_updated)->toBe(1);
});

it('skips rows with a blank cc_ref', function () {
    $batch = ImportBatch::factory()->create(['label' => '2026 H1']);

    (new FarIndexImporter)->import($batch, [
        ['cc_ref' => '', 'name' => 'No Ref', 'q_score' => 1.0, 'stability' => 2.0],
    ]);

    expect(Charity::count())->toBe(0)
        ->and($batch->fresh()->rows)->toBe(0);
});
