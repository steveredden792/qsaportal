<?php

use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the report summary fields on the PIR detail page', function () {
    $report = Report::factory()->pir()->create();
    Issue::factory()->create([
        'report_id' => $report->id,
        'is_current' => true,
        'accounting_date' => '2025-07-31',
        'charity_type' => 'Charitable company',
        'formation_date' => '2001-02-01',
        'stability_rank' => 663,
        'q_score_rank' => 469,
        'objectives' => 'Advances education for the public benefit.',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('reports.show', $report))
        ->assertOk()
        ->assertSee('Charitable company')
        ->assertSee('663')
        ->assertSee('469')
        ->assertSee('Advances education for the public benefit.');
});
