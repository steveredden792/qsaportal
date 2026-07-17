<?php

use App\Models\Charity;
use App\Models\Report;
use App\Livewire\PirCatalogue;
use Livewire\Livewire;

function pirCharity(array $attrs): Charity
{
    $charity = Charity::factory()->create($attrs);
    Report::factory()->pir()->create(['charity_id' => $charity->id, 'slug' => 'pir-'.$charity->cc_ref]);

    return $charity;
}

it('lists PIR charities', function () {
    pirCharity(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);

    Livewire::test(PirCatalogue::class)->assertSee('Oxfam');
});

it('filters by keyword on name', function () {
    pirCharity(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);
    pirCharity(['name' => 'Barnardos', 'cc_ref' => '2222222', 'latest_q_score' => 40, 'latest_stability' => 30]);

    Livewire::test(PirCatalogue::class)
        ->set('search', 'Oxf')
        ->assertSee('Oxfam')
        ->assertDontSee('Barnardos');
});

it('filters by Q score range', function () {
    pirCharity(['name' => 'HighQ', 'cc_ref' => '3333333', 'latest_q_score' => 65, 'latest_stability' => 50]);
    pirCharity(['name' => 'LowQ', 'cc_ref' => '4444444', 'latest_q_score' => 25, 'latest_stability' => 50]);

    Livewire::test(PirCatalogue::class)
        ->set('qMin', 50)
        ->assertSee('HighQ')
        ->assertDontSee('LowQ');
});

it('filters by stability range', function () {
    pirCharity(['name' => 'Stable', 'cc_ref' => '5555555', 'latest_q_score' => 50, 'latest_stability' => 80]);
    pirCharity(['name' => 'Shaky', 'cc_ref' => '6666666', 'latest_q_score' => 50, 'latest_stability' => 20]);

    Livewire::test(PirCatalogue::class)
        ->set('stabilityMax', 50)
        ->assertSee('Shaky')
        ->assertDontSee('Stable');
});

it('filters by keyword on cc_ref', function () {
    pirCharity(['name' => 'Alpha', 'cc_ref' => '9990001', 'latest_q_score' => 50, 'latest_stability' => 50]);
    pirCharity(['name' => 'Beta', 'cc_ref' => '8880002', 'latest_q_score' => 50, 'latest_stability' => 50]);

    Livewire::test(PirCatalogue::class)
        ->set('search', '9990001')
        ->assertSee('Alpha')
        ->assertDontSee('Beta');
});

it('filters by Q score max and stability min together', function () {
    pirCharity(['name' => 'InBand', 'cc_ref' => '7770003', 'latest_q_score' => 40, 'latest_stability' => 60]);
    pirCharity(['name' => 'TooHighQ', 'cc_ref' => '7770004', 'latest_q_score' => 90, 'latest_stability' => 60]);
    pirCharity(['name' => 'TooLowStab', 'cc_ref' => '7770005', 'latest_q_score' => 40, 'latest_stability' => 10]);

    Livewire::test(PirCatalogue::class)
        ->set('qMax', 50)
        ->set('stabilityMin', 30)
        ->assertSee('InBand')
        ->assertDontSee('TooHighQ')
        ->assertDontSee('TooLowStab');
});

it('ignores an unwhitelisted sortField from the URL (no SQL injection)', function () {
    pirCharity(['name' => 'Gamma', 'cc_ref' => '6660006', 'latest_q_score' => 50, 'latest_stability' => 50]);

    // A malicious sortField hydrated from the query string must not reach orderBy();
    // render() falls back to 'name' rather than erroring or injecting SQL.
    Livewire::test(PirCatalogue::class)
        ->set('sortField', 'latest_q_score); drop table charities;--')
        ->assertSee('Gamma');
});
