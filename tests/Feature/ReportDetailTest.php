<?php

use App\Models\Charity;
use App\Models\Issue;
use App\Models\Report;

it('shows a PIR detail page with charity data and price', function () {
    $charity = Charity::factory()->create(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);
    $report = Report::factory()->pir()->create(['charity_id' => $charity->id, 'slug' => 'pir-1111111']);
    Issue::factory()->for($report)->create(['is_current' => true, 'version_label' => '2026 H1']);

    $this->get('/reports/pir-1111111')
        ->assertOk()->assertSee('Oxfam')->assertSee('1111111')->assertSee('2026 H1')->assertSee('£25.00');
});

it('returns 404 for an unknown report slug', function () {
    $this->get('/reports/does-not-exist')->assertNotFound();
});
