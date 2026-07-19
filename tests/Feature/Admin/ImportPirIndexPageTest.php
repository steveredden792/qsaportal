<?php

use App\Filament\Pages\ImportPirIndex;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('renders the import PIR index page for an authenticated admin', function () {
    // Filament's Authenticate middleware allows all users in the local
    // environment when the User model doesn't implement FilamentUser.
    config(['app.env' => 'local']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/import-pir-index')
        ->assertOk();
});

it('imports charities and creates an ImportBatch via runImport()', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('2026-07/acme-1234567.pdf', 'pdf');
    Storage::disk('s3')->put('2026-07/beacon-7654321.pdf', 'pdf');

    $page = app(ImportPirIndex::class);

    $batch = $page->runImport(
        base_path('tests/fixtures/pir-index-sample.csv'),
        '2026 H1',
        '2026-07',
    );

    expect($batch->status)->toBe('published')
        ->and($batch->label)->toBe('2026 H1')
        ->and($batch->rows)->toBe(2)
        ->and($batch->charities_created)->toBe(2)
        ->and($batch->issues_created)->toBe(2);

    expect(Charity::count())->toBe(2);
    expect(ImportBatch::count())->toBe(1);
});

it('fails the batch via runImport() when files are missing from S3', function () {
    Storage::fake('s3');

    $page = app(ImportPirIndex::class);

    $batch = $page->runImport(
        base_path('tests/fixtures/pir-index-sample.csv'),
        '2026 H1',
        '2026-07',
    );

    expect($batch->status)->toBe('failed')
        ->and($batch->errors)->toHaveCount(2)
        ->and(Charity::count())->toBe(0);
});
