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
