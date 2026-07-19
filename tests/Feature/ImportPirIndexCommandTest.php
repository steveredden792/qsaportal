<?php

use App\Models\Charity;
use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('imports a PIR index csv via the artisan command', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('2026-07/acme-1234567.pdf', 'pdf');
    Storage::disk('s3')->put('2026-07/beacon-7654321.pdf', 'pdf');

    $this->artisan('import:pir-index', [
        'path' => base_path('tests/fixtures/pir-index-sample.csv'),
        'label' => '2026 H1',
        'folder' => '2026-07',
    ])->assertSuccessful();

    expect(Charity::count())->toBe(2);

    $batch = ImportBatch::first();
    expect($batch->label)->toBe('2026 H1')
        ->and($batch->status)->toBe('published')
        ->and($batch->charities_created)->toBe(2)
        ->and($batch->issues_created)->toBe(2);
});

it('reports validation errors and imports nothing when files are missing from S3', function () {
    Storage::fake('s3');

    $this->artisan('import:pir-index', [
        'path' => base_path('tests/fixtures/pir-index-sample.csv'),
        'label' => '2026 H1',
        'folder' => '2026-07',
    ])->assertFailed();

    expect(Charity::count())->toBe(0)
        ->and(ImportBatch::firstOrFail()->status)->toBe('failed');
});

it('fails cleanly when the file is missing', function () {
    $this->artisan('import:pir-index', ['path' => '/no/such/file.csv', 'label' => '2026 H1', 'folder' => '2026-07'])
        ->assertFailed();

    expect(ImportBatch::count())->toBe(0);
});
