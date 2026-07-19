<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('grants an authenticated user access to a teaser', function () {
    $asset = new Asset(['type' => AssetType::Teaser]);
    expect((new AccessService)->canAccess(new User, $asset))->toBeTrue();
});

it('denies a guest access to a teaser', function () {
    $asset = new Asset(['type' => AssetType::Teaser]);
    expect((new AccessService)->canAccess(null, $asset))->toBeFalse();
});

it('denies access to a report pdf until entitlements exist', function () {
    $asset = new Asset(['type' => AssetType::ReportPdf]);
    expect((new AccessService)->canAccess(new User, $asset))->toBeFalse();
});

it('denies access to a dataset until entitlements exist', function () {
    $asset = new Asset(['type' => AssetType::Dataset]);
    expect((new AccessService)->canAccess(new User, $asset))->toBeFalse();
});

it('grants report pdf access with an active entitlement for the issue', function () {
    $user = User::factory()->create();
    $issue = Issue::factory()->create();
    $asset = Asset::factory()->for($issue)->create(['type' => AssetType::ReportPdf]);
    Entitlement::factory()->create(['user_id' => $user->id, 'issue_id' => $issue->id]);

    expect((new AccessService)->canAccess($user, $asset))->toBeTrue();
});

it('denies report pdf access without an entitlement, or with an expired or revoked one', function () {
    $user = User::factory()->create();
    $issue = Issue::factory()->create();
    $asset = Asset::factory()->for($issue)->create(['type' => AssetType::ReportPdf]);

    expect((new AccessService)->canAccess($user, $asset))->toBeFalse();

    Entitlement::factory()->expired()->create(['user_id' => $user->id, 'issue_id' => $issue->id]);
    expect((new AccessService)->canAccess($user, $asset))->toBeFalse();

    Entitlement::factory()->revoked()->create(['user_id' => $user->id, 'issue_id' => $issue->id]);
    expect((new AccessService)->canAccess($user, $asset))->toBeFalse();
});

it('denies access to an entitlement for a different issue', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['type' => AssetType::ReportPdf]);
    Entitlement::factory()->create(['user_id' => $user->id]);

    expect((new AccessService)->canAccess($user, $asset))->toBeFalse();
});
