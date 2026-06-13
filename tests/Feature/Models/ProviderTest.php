<?php

use App\Models\Provider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a provider from the factory', function () {
    $provider = Provider::factory()->create(['code' => 'PRV-001']);
    expect($provider->code)->toBe('PRV-001');
});

it('enforces a unique code', function () {
    Provider::factory()->create(['code' => 'PRV-001']);
    Provider::factory()->create(['code' => 'PRV-001']);
})->throws(QueryException::class);
