<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('imports a FAR index CSV end to end', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('2026-07/acme.pdf', 'pdf');
    Charity::factory()->create(['cc_ref' => '1111111']);

    $csv = tempnam(sys_get_temp_dir(), 'far').'.csv';
    file_put_contents($csv,
        "Provider Ref,Provider Name,Tier,Filename,Related CC Refs\n".
        "PRV-1000,Acme Care,standard,acme.pdf,1111111\n"
    );

    $this->artisan('import:far-index', ['path' => $csv, 'label' => '2026 H2', 'folder' => '2026-07'])
        ->assertSuccessful();

    expect(Report::where('type', ReportType::FAR)->count())->toBe(1)
        ->and(ImportBatch::where('type', 'far_index')->firstOrFail()->status)->toBe('published');
});

it('reports validation errors and imports nothing', function () {
    Storage::fake('s3');

    $csv = tempnam(sys_get_temp_dir(), 'far').'.csv';
    file_put_contents($csv,
        "Provider Ref,Provider Name,Tier,Filename,Related CC Refs\n".
        "PRV-1000,Acme Care,standard,missing.pdf,\n"
    );

    $this->artisan('import:far-index', ['path' => $csv, 'label' => '2026 H2', 'folder' => '2026-07'])
        ->assertFailed();

    expect(Report::count())->toBe(0)
        ->and(ImportBatch::firstOrFail()->status)->toBe('failed');
});
