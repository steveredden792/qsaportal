<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    // Stub temporaryUrl so the fake disk can produce a predictable signed URL.
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path, $expiry, array $options = []) => 'https://signed.example/'.$path
    );
});

it('redirects an authenticated user to a pre-signed URL for a teaser', function () {
    $asset = Asset::factory()->create([
        'type' => AssetType::Teaser, 'disk' => 's3', 'path' => 'teasers/sample.pdf',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('assets.download', $asset))
        ->assertRedirect('https://signed.example/teasers/sample.pdf');
});

it('forbids access to a report pdf (no entitlement yet)', function () {
    $asset = Asset::factory()->create(['type' => AssetType::ReportPdf, 'disk' => 's3']);

    $this->actingAs(User::factory()->create())
        ->get(route('assets.download', $asset))
        ->assertForbidden();
});

it('redirects guests to login', function () {
    $asset = Asset::factory()->create(['type' => AssetType::Teaser, 'disk' => 's3']);

    $this->get(route('assets.download', $asset))->assertRedirect('/login');
});
