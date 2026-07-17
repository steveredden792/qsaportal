<?php

use App\Filament\Pages\ImportPirIndex;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $page = app(ImportPirIndex::class);

    $batch = $page->runImport(
        base_path('tests/fixtures/pir-index-sample.csv'),
        '2026 H1',
    );

    expect($batch->status)->toBe('completed')
        ->and($batch->label)->toBe('2026 H1')
        ->and($batch->rows)->toBe(2)
        ->and($batch->charities_created)->toBe(2)
        ->and($batch->issues_created)->toBe(2);

    expect(Charity::count())->toBe(2);
    expect(ImportBatch::count())->toBe(1);
});
