<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Issue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to an issue and casts its type', function () {
    $issue = Issue::factory()->create();
    $asset = Asset::factory()->for($issue)->create(['type' => AssetType::ReportPdf]);

    expect($asset->issue->is($issue))->toBeTrue()
        ->and($asset->type)->toBe(AssetType::ReportPdf)
        ->and($asset->disk)->toBe('s3');
});
