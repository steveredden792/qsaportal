<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\FarPirReference;
use App\Models\Issue;
use App\Models\Provider;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires a charity for a PIR and forbids a provider', function () {
    $report = Report::factory()->pir()->create();
    expect($report->subject())->toBeInstanceOf(Charity::class);

    expect(fn () => Report::factory()->pir()->create(['charity_id' => null]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => Report::factory()->pir()->create(['provider_id' => Provider::factory()->create()->id]))
        ->toThrow(InvalidArgumentException::class);
});

it('requires a provider for a FAR and forbids a charity', function () {
    $report = Report::factory()->far()->create();
    expect($report->subject())->toBeInstanceOf(Provider::class);

    expect(fn () => Report::factory()->far()->create(['provider_id' => null]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => Report::factory()->far()->create(['charity_id' => Charity::factory()->create()->id]))
        ->toThrow(InvalidArgumentException::class);
});

it('stores a tier on a FAR report', function () {
    $report = Report::factory()->far()->create(['tier' => 'premium']);
    expect($report->fresh()->tier)->toBe('premium');
});

it('links a FAR issue to referenced charities', function () {
    $far = Report::factory()->far()->create();
    $issue = Issue::factory()->for($far)->create(['is_current' => true]);
    $charities = Charity::factory(3)->create();

    $charities->each(fn (Charity $c) => FarPirReference::create([
        'issue_id' => $issue->id,
        'charity_id' => $c->id,
    ]));

    expect($issue->referencedCharities)->toHaveCount(3)
        ->and($issue->referencedCharities->first())->toBeInstanceOf(Charity::class);
});
