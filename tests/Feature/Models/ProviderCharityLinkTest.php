<?php

use App\Models\Charity;
use App\Models\Provider;
use App\Models\ProviderCharityLink;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links a provider to a charity both ways', function () {
    $provider = Provider::factory()->create();
    $charity = Charity::factory()->create();
    ProviderCharityLink::factory()->create([
        'provider_id' => $provider->id,
        'charity_id' => $charity->id,
    ]);

    expect($provider->charities->pluck('id'))->toContain($charity->id)
        ->and($charity->providers->pluck('id'))->toContain($provider->id);
});

it('forbids duplicate provider/charity pairs', function () {
    $provider = Provider::factory()->create();
    $charity = Charity::factory()->create();
    $payload = ['provider_id' => $provider->id, 'charity_id' => $charity->id];
    ProviderCharityLink::factory()->create($payload);
    ProviderCharityLink::factory()->create($payload);
})->throws(QueryException::class);
