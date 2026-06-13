<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\Market;
use App\Models\Provider;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links a FAR report to its charity subject', function () {
    $charity = Charity::factory()->create();
    $report = Report::factory()->far()->for($charity)->create();

    expect($report->type)->toBe(ReportType::FAR)
        ->and($report->subject()->is($charity))->toBeTrue();
});

it('links a PPR report to its provider subject', function () {
    $provider = Provider::factory()->create();
    $report = Report::factory()->ppr()->for($provider)->create();

    expect($report->subject()->is($provider))->toBeTrue();
});

it('links a PMR report to its market subject', function () {
    $market = Market::factory()->create();
    $report = Report::factory()->pmr()->for($market)->create();

    expect($report->subject()->is($market))->toBeTrue();
});

it('rejects a FAR report that also sets a provider', function () {
    Report::factory()->far()->create(['provider_id' => Provider::factory()]);
})->throws(InvalidArgumentException::class);

it('rejects a PPR report with no provider', function () {
    Report::factory()->ppr()->create(['provider_id' => null]);
})->throws(InvalidArgumentException::class);
