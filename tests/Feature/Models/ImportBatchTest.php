<?php

use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an import batch with default status and zero counts', function () {
    $batch = ImportBatch::factory()->create(['label' => '2026 H1']);

    expect($batch->label)->toBe('2026 H1')
        ->and($batch->status)->toBe('pending')
        ->and($batch->rows)->toBe(0)
        ->and($batch->charities_created)->toBe(0);
});
