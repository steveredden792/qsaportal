<?php

use App\Models\Market;
use App\Models\Provider;
use App\Models\Report;

it('lists PPR provider reports with a from-price', function () {
    $provider = Provider::factory()->create(['name' => 'Acme Care', 'code' => 'PRV-1000']);
    Report::factory()->ppr()->for($provider)->create(['slug' => 'ppr-prv-1000', 'name' => 'Acme Care PPR']);

    $this->get('/catalogue/ppr')
        ->assertOk()
        ->assertSee('Acme Care')
        ->assertSee('£50.00')
        ->assertSee('/reports/ppr-prv-1000');
});

it('lists PMR market reports with a from-price', function () {
    $market = Market::factory()->create(['name' => 'Homelessness', 'code' => 'MKT-2000']);
    Report::factory()->pmr()->for($market)->create(['slug' => 'pmr-mkt-2000', 'name' => 'Homelessness PMR']);

    $this->get('/catalogue/pmr')
        ->assertOk()
        ->assertSee('Homelessness')
        ->assertSee('£50.00')
        ->assertSee('/reports/pmr-mkt-2000');
});

it('filters PPR by provider name', function () {
    $acme = Provider::factory()->create(['name' => 'Acme Care', 'code' => 'PRV-1000']);
    Report::factory()->ppr()->for($acme)->create(['slug' => 'ppr-prv-1000']);
    $beacon = Provider::factory()->create(['name' => 'Beacon Health', 'code' => 'PRV-2000']);
    Report::factory()->ppr()->for($beacon)->create(['slug' => 'ppr-prv-2000']);

    $this->get('/catalogue/ppr?search=Acme')
        ->assertOk()
        ->assertSee('Acme Care')
        ->assertDontSee('Beacon Health');
});

it('filters PMR by market name', function () {
    $home = Market::factory()->create(['name' => 'Homelessness', 'code' => 'MKT-2000']);
    Report::factory()->pmr()->for($home)->create(['slug' => 'pmr-mkt-2000']);
    $youth = Market::factory()->create(['name' => 'Youth Services', 'code' => 'MKT-3000']);
    Report::factory()->pmr()->for($youth)->create(['slug' => 'pmr-mkt-3000']);

    $this->get('/catalogue/pmr?search=Homeless')
        ->assertOk()
        ->assertSee('Homelessness')
        ->assertDontSee('Youth Services');
});
