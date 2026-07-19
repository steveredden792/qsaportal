<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Entitlement;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function entitledIssue(User $user, array $entitlementAttrs = []): Entitlement
{
    $report = Report::factory()->pir()->create();
    $issue = Issue::factory()->for($report)->create(['is_current' => true]);
    Asset::factory()->for($issue)->create(['type' => AssetType::ReportPdf]);

    return Entitlement::factory()->create(array_merge([
        'user_id' => $user->id,
        'issue_id' => $issue->id,
    ], $entitlementAttrs));
}

it('lists entitlements with status chips and download links', function () {
    $user = User::factory()->create();
    $active = entitledIssue($user);
    $expiring = entitledIssue($user, ['expires_at' => now()->addDays(10)]);
    $expired = entitledIssue($user, ['expires_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('my-reports'))
        ->assertOk()
        ->assertSee($active->issue->report->name)
        ->assertSee($expiring->issue->report->name)
        ->assertSee($expired->issue->report->name)
        ->assertSee('Active')
        ->assertSee('Expiring soon')
        ->assertSee('Expired');
});

it('only shows the signed-in users entitlements', function () {
    $user = User::factory()->create();
    $other = entitledIssue(User::factory()->create());

    $this->actingAs($user)
        ->get(route('my-reports'))
        ->assertOk()
        ->assertDontSee($other->issue->report->name);
});

it('redirects guests to login', function () {
    $this->get(route('my-reports'))->assertRedirect(route('login'));
});

it('renders revoked entitlement as expired with no download link', function () {
    $user = User::factory()->create();
    $revoked = entitledIssue($user, ['revoked_at' => now()]);

    $this->actingAs($user)
        ->get(route('my-reports'))
        ->assertOk()
        ->assertSee('Expired')
        ->assertSee($revoked->issue->report->name);

    // Verify download link is not shown for revoked entitlement
    $pdf = $revoked->issue->assets->firstWhere('type', AssetType::ReportPdf);
    $downloadUrl = route('assets.download', $pdf);
    $this->actingAs($user)
        ->get(route('my-reports'))
        ->assertDontSee($downloadUrl, false);
});

it('shows download link for active entitlement but not expired', function () {
    $user = User::factory()->create();
    $active = entitledIssue($user);
    $expired = entitledIssue($user, ['expires_at' => now()->subDay()]);

    $activePdf = $active->issue->assets->firstWhere('type', AssetType::ReportPdf);
    $expiredPdf = $expired->issue->assets->firstWhere('type', AssetType::ReportPdf);

    $activeDownloadUrl = route('assets.download', $activePdf);
    $expiredDownloadUrl = route('assets.download', $expiredPdf);

    $response = $this->actingAs($user)->get(route('my-reports'));

    $response->assertOk()
        ->assertSee($activeDownloadUrl, false);

    $response->assertDontSee($expiredDownloadUrl, false);
});

it('shows expiring soon at 30-day boundary', function () {
    $user = User::factory()->create();
    entitledIssue($user, ['expires_at' => now()->addDays(30)]);

    $this->actingAs($user)
        ->get(route('my-reports'))
        ->assertOk()
        ->assertSee('Expiring soon');
});

it('shows active status at 31-day boundary', function () {
    $user = User::factory()->create();
    entitledIssue($user, ['expires_at' => now()->addDays(31)]);

    $this->actingAs($user)
        ->get(route('my-reports'))
        ->assertOk()
        ->assertSee('Active');
});
