<?php

use App\Models\Charity;
use App\Models\Issue;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists q_grade and stability_grade on an issue', function () {
    $report = Report::factory()->pir()->create();

    $issue = Issue::create([
        'report_id' => $report->id,
        'version_label' => '2026 H1',
        'published_at' => now(),
        'is_current' => true,
        'q_score' => 55.5,
        'stability' => 60.0,
        'q_grade' => 'bbb',
        'stability_grade' => 7.5,
    ]);

    $issue->refresh();
    expect($issue->q_grade)->toBe('bbb')
        ->and((float) $issue->stability_grade)->toBe(7.5);
});

it('persists latest_q_grade and latest_stability_grade on a charity', function () {
    $charity = Charity::create([
        'cc_ref' => '1234567',
        'name' => 'Acme Trust',
        'latest_q_score' => 55.5,
        'latest_stability' => 60.0,
        'latest_q_grade' => 'bbb',
        'latest_stability_grade' => 7.5,
    ]);

    $charity->refresh();
    expect($charity->latest_q_grade)->toBe('bbb')
        ->and((float) $charity->latest_stability_grade)->toBe(7.5);
});
