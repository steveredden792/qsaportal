<?php

use App\Models\Charity;
use App\Models\Issue;
use App\Models\Market;
use App\Models\Provider;
use App\Models\Report;

it('shows a FAR detail page with charity data and price', function () {
    $charity = Charity::factory()->create(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);
    $report = Report::factory()->far()->for($charity)->create(['slug' => 'far-1111111']);
    Issue::factory()->for($report)->create(['is_current' => true, 'version_label' => '2026 H1']);

    $this->get('/reports/far-1111111')
        ->assertOk()->assertSee('Oxfam')->assertSee('1111111')->assertSee('2026 H1')->assertSee('£25.00');
});

it('shows a PPR detail page with three tier prices', function () {
    $provider = Provider::factory()->create(['name' => 'Acme Care', 'code' => 'PRV-1000']);
    $report = Report::factory()->ppr()->for($provider)->create(['slug' => 'ppr-prv-1000']);
    Issue::factory()->for($report)->create(['is_current' => true]);

    $this->get('/reports/ppr-prv-1000')
        ->assertOk()->assertSee('Acme Care')
        ->assertSee('£50.00')->assertSee('£75.00')->assertSee('£100.00');
});

it('shows a PMR detail page with two tier prices', function () {
    $market = Market::factory()->create(['name' => 'Homelessness', 'code' => 'MKT-2000']);
    $report = Report::factory()->pmr()->for($market)->create(['slug' => 'pmr-mkt-2000']);
    Issue::factory()->for($report)->create(['is_current' => true]);

    $this->get('/reports/pmr-mkt-2000')
        ->assertOk()->assertSee('Homelessness')
        ->assertSee('£50.00')->assertSee('£100.00')->assertDontSee('£75.00');
});

it('returns 404 for an unknown report slug', function () {
    $this->get('/reports/does-not-exist')->assertNotFound();
});
