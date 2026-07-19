<?php

use App\Enums\ReportType;
use App\Filament\Pages\ImportFarIndex;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function farIndexCsv(string $contents): string
{
    $csv = tempnam(sys_get_temp_dir(), 'far').'.csv';
    file_put_contents($csv, $contents);

    return $csv;
}

it('renders the import FAR index page for an authenticated admin', function () {
    // Filament's Authenticate middleware allows all users in the local
    // environment when the User model doesn't implement FilamentUser.
    config(['app.env' => 'local']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/import-far-index')
        ->assertOk();
});

it('imports providers and creates an ImportBatch via runImport()', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('2026-07/acme.pdf', 'pdf');
    Charity::factory()->create(['cc_ref' => '1111111']);

    $csv = farIndexCsv(
        "Provider Ref,Provider Name,Tier,Filename,Related CC Refs\n".
        "PRV-1000,Acme Care,standard,acme.pdf,1111111\n"
    );

    $page = app(ImportFarIndex::class);
    $batch = $page->runImport($csv, '2026 H2', '2026-07');

    expect($batch->status)->toBe('published')
        ->and($batch->label)->toBe('2026 H2')
        ->and($batch->rows)->toBe(1)
        ->and($batch->providers_created)->toBe(1)
        ->and($batch->issues_created)->toBe(1);

    expect(Report::where('type', ReportType::FAR)->count())->toBe(1);
    expect(ImportBatch::count())->toBe(1);
});

it('fails the batch via runImport() when validation errors exist', function () {
    Storage::fake('s3');

    $csv = farIndexCsv(
        "Provider Ref,Provider Name,Tier,Filename,Related CC Refs\n".
        "PRV-1000,Acme Care,standard,missing.pdf,\n"
    );

    $page = app(ImportFarIndex::class);
    $batch = $page->runImport($csv, '2026 H2', '2026-07');

    expect($batch->status)->toBe('failed')
        ->and(Report::count())->toBe(0);
});
