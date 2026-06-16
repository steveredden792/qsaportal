<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\User;
use App\Services\AccessService;

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
