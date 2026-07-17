<?php

use App\Models\Charity;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests away from the PIR catalogue', function () {
    $this->get(route('catalogue.pir'))->assertRedirect(route('login'));
});

it('redirects guests away from a PIR detail page', function () {
    $charity = Charity::factory()->create(['cc_ref' => '1111111']);
    $report = Report::factory()->pir()->for($charity)->create(['slug' => 'pir-1111111']);
    Issue::factory()->for($report)->create(['is_current' => true]);

    $this->get('/reports/pir-1111111')->assertRedirect(route('login'));
});

it('redirects unverified users to email verification', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('catalogue.pir'))
        ->assertRedirect(route('verification.notice'));
});

it('shows the PIR catalogue to a verified free account', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('catalogue.pir'))
        ->assertOk();
});
