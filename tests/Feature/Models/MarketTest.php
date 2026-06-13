<?php

use App\Models\Market;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a market from the factory', function () {
    $market = Market::factory()->create(['code' => 'MKT-001']);
    expect($market->code)->toBe('MKT-001');
});

it('enforces a unique code', function () {
    Market::factory()->create(['code' => 'MKT-001']);
    Market::factory()->create(['code' => 'MKT-001']);
})->throws(QueryException::class);
