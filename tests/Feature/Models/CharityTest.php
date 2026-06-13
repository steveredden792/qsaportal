<?php

use App\Models\Charity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a charity from the factory', function () {
    $charity = Charity::factory()->create(['cc_ref' => '1234567']);
    expect($charity->cc_ref)->toBe('1234567')
        ->and($charity->name)->not->toBeNull();
});

it('enforces a unique cc_ref', function () {
    Charity::factory()->create(['cc_ref' => '1234567']);
    Charity::factory()->create(['cc_ref' => '1234567']);
})->throws(QueryException::class);
