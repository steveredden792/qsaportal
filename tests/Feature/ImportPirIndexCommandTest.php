<?php

use App\Models\Charity;
use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports a PIR index csv via the artisan command', function () {
    $this->artisan('import:pir-index', [
        'path' => base_path('tests/fixtures/pir-index-sample.csv'),
        'label' => '2026 H1',
    ])->assertSuccessful();

    expect(Charity::count())->toBe(2);

    $batch = ImportBatch::first();
    expect($batch->label)->toBe('2026 H1')
        ->and($batch->status)->toBe('completed')
        ->and($batch->charities_created)->toBe(2)
        ->and($batch->issues_created)->toBe(2);
});

it('fails cleanly when the file is missing', function () {
    $this->artisan('import:pir-index', ['path' => '/no/such/file.csv', 'label' => '2026 H1'])
        ->assertFailed();

    expect(ImportBatch::count())->toBe(0);
});
