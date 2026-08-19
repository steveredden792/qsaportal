<?php

use App\Enums\AssetType;
use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\Issue;
use App\Models\Provider;
use App\Models\Report;
use App\Services\FarIndexImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function farRow(array $overrides = []): array
{
    return array_merge([
        'provider_ref' => 'PRV-1000',
        'name' => 'Acme Care',
        'tier' => 'standard',
        'filename' => 'acme.pdf',
        'related_cc_refs' => [],
    ], $overrides);
}

it('publishes a valid FAR batch with provider, tiered report, issue, asset and references', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('far/2026-07/acme.pdf', 'pdf');
    $charity = Charity::factory()->create(['cc_ref' => '1111111']);

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'far_index', 'folder' => '2026-07']);

    $result = app(FarIndexImporter::class)->import($batch, [
        farRow(['tier' => 'premium', 'related_cc_refs' => ['1111111']]),
    ]);

    expect($result->status)->toBe('published')
        ->and($result->providers_created)->toBe(1)
        ->and($result->issues_created)->toBe(1);

    $provider = Provider::where('code', 'PRV-1000')->firstOrFail();
    $report = Report::where('type', ReportType::FAR)->where('provider_id', $provider->id)->firstOrFail();
    expect($report->tier)->toBe('premium')
        ->and($report->slug)->toBe('far-prv-1000');

    $issue = $report->currentIssue;
    expect($issue->version_label)->toBe('2026 H2')
        ->and($issue->assets()->where('type', AssetType::ReportPdf)->first()->path)->toBe('far/2026-07/acme.pdf')
        ->and($issue->referencedCharities->pluck('cc_ref')->all())->toBe(['1111111']);
});

it('flips is_current when reimporting a provider under a new label', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('far/2026-07/acme.pdf', 'pdf');
    Storage::disk('s3')->put('far/2027-01/acme2.pdf', 'pdf');

    $importer = app(FarIndexImporter::class);
    $importer->import(
        ImportBatch::create(['label' => '2026 H2', 'type' => 'far_index', 'folder' => '2026-07']),
        [farRow()],
    );
    $importer->import(
        ImportBatch::create(['label' => '2027 H1', 'type' => 'far_index', 'folder' => '2027-01']),
        [farRow(['filename' => 'acme2.pdf', 'name' => 'Acme Care Ltd'])],
    );

    $report = Report::where('type', ReportType::FAR)->firstOrFail();
    expect($report->issues)->toHaveCount(2)
        ->and($report->currentIssue->version_label)->toBe('2027 H1')
        ->and(Provider::where('code', 'PRV-1000')->firstOrFail()->name)->toBe('Acme Care Ltd');
});

it('fails without writing on unknown tier, unknown cc ref, missing file, or duplicate provider', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('far/2026-07/acme.pdf', 'pdf');

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'far_index', 'folder' => '2026-07']);

    $result = app(FarIndexImporter::class)->import($batch, [
        farRow(['tier' => 'platinum']),                                   // unknown tier
        farRow(['provider_ref' => 'PRV-2000', 'related_cc_refs' => ['9999999']]), // unknown charity
        farRow(['provider_ref' => 'PRV-3000', 'filename' => 'nope.pdf']), // missing file
        farRow(['provider_ref' => 'PRV-2000', 'filename' => 'acme.pdf']), // duplicate ref
    ]);

    expect($result->status)->toBe('failed')
        ->and($result->errors)->toHaveCount(4)
        ->and(Provider::count())->toBe(0)
        ->and(Report::count())->toBe(0)
        ->and(Issue::count())->toBe(0);
});

it('publishes without checking s3 when file validation is disabled', function () {
    config(['reports.validate_import_files' => false]);
    Storage::fake('s3'); // deliberately empty

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'far_index', 'folder' => '2026-07']);

    $result = app(FarIndexImporter::class)->import($batch, [farRow()]);

    expect($result->status)->toBe('published');
});
