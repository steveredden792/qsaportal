<?php

use App\Models\Issue;
use App\Models\Report;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to a report', function () {
    $report = Report::factory()->far()->create();
    $issue = Issue::factory()->for($report)->create();

    expect($issue->report->is($report))->toBeTrue();
});

it('scopes to the current issue', function () {
    $report = Report::factory()->far()->create();
    Issue::factory()->for($report)->create(['is_current' => false, 'version_label' => '2025 H2']);
    $current = Issue::factory()->for($report)->create(['is_current' => true, 'version_label' => '2026 H1']);

    expect(Issue::current()->pluck('id'))->toContain($current->id)
        ->and(Issue::current()->count())->toBe(1);
});

it('forbids duplicate version labels per report', function () {
    $report = Report::factory()->far()->create();
    Issue::factory()->for($report)->create(['version_label' => '2026 H1']);
    Issue::factory()->for($report)->create(['version_label' => '2026 H1']);
})->throws(QueryException::class);
