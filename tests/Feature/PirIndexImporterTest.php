<?php

use App\Enums\AssetType;
use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\Issue;
use App\Models\Report;
use App\Services\PirIndexImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates a charity, PIR report and current issue from a new row', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('pir/2026-07/acme.pdf', 'pdf');

    $batch = ImportBatch::factory()->create(['label' => '2026 H1', 'folder' => '2026-07']);

    (new PirIndexImporter)->import($batch, [
        ['cc_ref' => '1234567', 'name' => 'Acme Trust', 'q_score' => 55.5, 'stability' => 60.0, 'filename' => 'acme.pdf'],
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
    expect($batch->status)->toBe('published')
        ->and($batch->rows)->toBe(1)
        ->and($batch->charities_created)->toBe(1)
        ->and($batch->issues_created)->toBe(1);
});

it('updates an existing charity and flips the current issue on a new batch', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('pir/2025-12/acme.pdf', 'pdf');
    Storage::disk('s3')->put('pir/2026-07/acme2.pdf', 'pdf');

    (new PirIndexImporter)->import(
        ImportBatch::factory()->create(['label' => '2025 H2', 'folder' => '2025-12']),
        [['cc_ref' => '1234567', 'name' => 'Old Name', 'q_score' => 40.0, 'stability' => 50.0, 'filename' => 'acme.pdf']],
    );

    $b2 = ImportBatch::factory()->create(['label' => '2026 H1', 'folder' => '2026-07']);
    (new PirIndexImporter)->import($b2, [
        ['cc_ref' => '1234567', 'name' => 'New Name', 'q_score' => 70.0, 'stability' => 80.0, 'filename' => 'acme2.pdf'],
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

it('fails the batch without writing when a file is missing from S3', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('pir/2026-07/oxfam.pdf', 'pdf');

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'pir_index', 'folder' => '2026-07']);

    $result = app(PirIndexImporter::class)->import($batch, [
        ['cc_ref' => '1111111', 'name' => 'Oxfam', 'q_score' => 60.0, 'stability' => 50.0, 'filename' => 'oxfam.pdf'],
        ['cc_ref' => '2222222', 'name' => 'Shelter', 'q_score' => 55.0, 'stability' => 45.0, 'filename' => 'missing.pdf'],
    ]);

    expect($result->status)->toBe('failed')
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]['row'])->toBe(2)
        ->and($result->errors[0]['error'])->toContain('missing.pdf')
        ->and(Charity::count())->toBe(0)
        ->and(Report::count())->toBe(0);
});

it('fails the batch on duplicate cc_refs and missing fields', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('pir/2026-07/a.pdf', 'pdf');

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'pir_index', 'folder' => '2026-07']);

    $result = app(PirIndexImporter::class)->import($batch, [
        ['cc_ref' => '1111111', 'name' => 'Oxfam', 'q_score' => null, 'stability' => null, 'filename' => 'a.pdf'],
        ['cc_ref' => '1111111', 'name' => 'Oxfam Again', 'q_score' => null, 'stability' => null, 'filename' => 'a.pdf'],
        ['cc_ref' => '', 'name' => 'No Ref', 'q_score' => null, 'stability' => null, 'filename' => 'a.pdf'],
        ['cc_ref' => '3333333', 'name' => 'No File', 'q_score' => null, 'stability' => null, 'filename' => ''],
    ]);

    expect($result->status)->toBe('failed')->and($result->errors)->toHaveCount(3);
});

it('publishes a valid batch with report pdf assets on the new issues', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('pir/2026-07/oxfam.pdf', 'pdf');

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'pir_index', 'folder' => '2026-07']);

    $result = app(PirIndexImporter::class)->import($batch, [
        ['cc_ref' => '1111111', 'name' => 'Oxfam', 'q_score' => 60.0, 'stability' => 50.0, 'filename' => 'oxfam.pdf'],
    ]);

    expect($result->status)->toBe('published');

    $issue = Issue::where('is_current', true)->firstOrFail();
    $asset = $issue->assets()->where('type', AssetType::ReportPdf)->firstOrFail();
    expect($asset->disk)->toBe('s3')
        ->and($asset->path)->toBe('pir/2026-07/oxfam.pdf')
        ->and($asset->original_filename)->toBe('oxfam.pdf');
});

it('publishes without checking s3 when file validation is disabled', function () {
    config(['reports.validate_import_files' => false]);
    Storage::fake('s3'); // deliberately empty — no files uploaded

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'pir_index', 'folder' => '2026-07']);

    $result = app(PirIndexImporter::class)->import($batch, [
        ['cc_ref' => '1111111', 'name' => 'Oxfam', 'q_score' => 60.0, 'stability' => 50.0, 'filename' => 'oxfam.pdf'],
    ]);

    expect($result->status)->toBe('published');

    $asset = Issue::where('is_current', true)->firstOrFail()
        ->assets()->where('type', AssetType::ReportPdf)->firstOrFail();
    expect($asset->path)->toBe('pir/2026-07/oxfam.pdf');
});

it('still fails blank filenames when file validation is disabled', function () {
    config(['reports.validate_import_files' => false]);
    Storage::fake('s3');

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'pir_index', 'folder' => '2026-07']);

    $result = app(PirIndexImporter::class)->import($batch, [
        ['cc_ref' => '1111111', 'name' => 'Oxfam', 'q_score' => null, 'stability' => null, 'filename' => ''],
    ]);

    expect($result->status)->toBe('failed')
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]['error'])->toBe('Missing filename');
});

it('persists q_grade and stability_grade on the charity and issue', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('pir/2026-07/acme.pdf', 'pdf');

    $batch = ImportBatch::factory()->create(['label' => '2026 H1', 'folder' => '2026-07']);

    (new PirIndexImporter)->import($batch, [
        ['cc_ref' => '1234567', 'name' => 'Acme Trust', 'q_score' => 55.5, 'stability' => 60.0, 'q_grade' => 'bbb', 'stability_grade' => 7.5, 'filename' => 'acme.pdf'],
    ]);

    $charity = Charity::where('cc_ref', '1234567')->first();
    expect($charity->latest_q_grade)->toBe('bbb')
        ->and((float) $charity->latest_stability_grade)->toBe(7.5);

    $issue = $charity->report->currentIssue;
    expect($issue->q_grade)->toBe('bbb')
        ->and((float) $issue->stability_grade)->toBe(7.5);
});
