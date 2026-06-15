<?php

use App\Models\Charity;
use App\Models\Report;
use App\Livewire\FarCatalogue;
use Livewire\Livewire;

function farCharity(array $attrs): Charity
{
    $charity = Charity::factory()->create($attrs);
    Report::factory()->far()->for($charity)->create(['slug' => 'far-'.$charity->cc_ref]);

    return $charity;
}

it('lists FAR charities', function () {
    farCharity(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);

    Livewire::test(FarCatalogue::class)->assertSee('Oxfam');
});

it('filters by keyword on name', function () {
    farCharity(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);
    farCharity(['name' => 'Barnardos', 'cc_ref' => '2222222', 'latest_q_score' => 40, 'latest_stability' => 30]);

    Livewire::test(FarCatalogue::class)
        ->set('search', 'Oxf')
        ->assertSee('Oxfam')
        ->assertDontSee('Barnardos');
});

it('filters by Q score range', function () {
    farCharity(['name' => 'HighQ', 'cc_ref' => '3333333', 'latest_q_score' => 65, 'latest_stability' => 50]);
    farCharity(['name' => 'LowQ', 'cc_ref' => '4444444', 'latest_q_score' => 25, 'latest_stability' => 50]);

    Livewire::test(FarCatalogue::class)
        ->set('qMin', 50)
        ->assertSee('HighQ')
        ->assertDontSee('LowQ');
});

it('filters by stability range', function () {
    farCharity(['name' => 'Stable', 'cc_ref' => '5555555', 'latest_q_score' => 50, 'latest_stability' => 80]);
    farCharity(['name' => 'Shaky', 'cc_ref' => '6666666', 'latest_q_score' => 50, 'latest_stability' => 20]);

    Livewire::test(FarCatalogue::class)
        ->set('stabilityMax', 50)
        ->assertSee('Shaky')
        ->assertDontSee('Stable');
});
