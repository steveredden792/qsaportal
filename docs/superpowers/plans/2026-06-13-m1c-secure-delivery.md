# M1c Secure Delivery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve report assets from the private S3 bucket only through an access-gated endpoint that issues short-lived pre-signed URLs, and surface the free (registration-gated) teaser on report detail pages.

**Architecture:** A single `AccessService` is the one place that answers "may this user open this asset?". For M1 the only freely-accessible asset is the **teaser** (any authenticated user); report PDFs and datasets are denied until entitlements land in M2. A `DownloadController` runs that check, then redirects to a pre-signed S3 URL (5-minute TTL). The client never sees the bucket or an S3 key.

**Tech Stack:** Laravel 13, Livewire/Blade, Filament, Pest, Amazon S3 (`league/flysystem-aws-s3-v3`), MySQL.

**Reference spec:** `docs/superpowers/specs/2026-06-12-qs-analysis-ecommerce-portal-design.md` §4 (entitlement engine), §6/§7 (catalogue + secure delivery). Builds on M0 (models `Report`/`Issue`/`Asset`, `AssetType` enum) and M1a (detail pages, `ReportController`).

**Deferred (not this slice):** entitlement-based access for paid assets (M2); download audit log (M4); real report PDFs via import (M1d).

---

## File Structure

- Create `app/Services/AccessService.php` — the single access-decision unit. One method: `canAccess(?User, Asset): bool`.
- Create `app/Http/Controllers/DownloadController.php` — gate + pre-signed redirect. Depends on `AccessService`.
- Modify `routes/web.php` — add the `auth`-protected `assets.download` route.
- Modify `app/Http/Controllers/ReportController.php` — load the current issue's teaser and pass it to the detail views.
- Modify `resources/views/reports/{far,ppr,pmr}-detail.blade.php` — show a "View free sample" link when a teaser exists and the user is authenticated.
- Modify `database/seeders/CatalogueDemoSeeder.php` — attach a teaser asset to each seeded report's current issue (DB only).
- Tests: `tests/Unit/AccessServiceTest.php`, `tests/Feature/DownloadTest.php`, `tests/Feature/TeaserLinkTest.php`.

---

## Task 1: AccessService (access-decision skeleton)

**Files:** Create `app/Services/AccessService.php`. Test `tests/Unit/AccessServiceTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/AccessServiceTest.php`:
```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=AccessServiceTest`
Expected: FAIL with "Class App\Services\AccessService not found".

- [ ] **Step 3: Implement**

`app/Services/AccessService.php`:
```php
<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\User;

class AccessService
{
    /**
     * Whether the user may open the asset.
     *
     * M1 rules (entitlement checks for paid assets arrive in M2):
     * - Teaser/sample assets are free but registration-gated: any authenticated user.
     * - Report PDFs and datasets require a purchase/entitlement: denied for now.
     */
    public function canAccess(?User $user, Asset $asset): bool
    {
        if ($asset->type === AssetType::Teaser) {
            return $user !== null;
        }

        return false;
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=AccessServiceTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AccessService.php tests/Unit/AccessServiceTest.php
git commit -m "feat: add AccessService access-decision skeleton"
```

---

## Task 2: Gated download endpoint with pre-signed URLs

**Files:** Create `app/Http/Controllers/DownloadController.php`. Modify `routes/web.php`. Test `tests/Feature/DownloadTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/DownloadTest.php`:
```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=DownloadTest`
Expected: FAIL (route `assets.download` not defined).

- [ ] **Step 3: Implement the controller**

`app/Http/Controllers/DownloadController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function show(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($this->access->canAccess($request->user(), $asset), 403);

        $url = Storage::disk($asset->disk)->temporaryUrl($asset->path, now()->addMinutes(5));

        return redirect()->away($url);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add after the `reports.show` route:
```php
Route::get('/assets/{asset}/download', [\App\Http\Controllers\DownloadController::class, 'show'])
    ->middleware('auth')
    ->name('assets.download');
```

- [ ] **Step 5: Run it to verify it passes**

Run: `php artisan test --filter=DownloadTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/DownloadController.php routes/web.php tests/Feature/DownloadTest.php
git commit -m "feat: add gated pre-signed S3 download endpoint"
```

---

## Task 3: Surface the teaser on report detail pages

**Files:** Modify `app/Http/Controllers/ReportController.php` and `resources/views/reports/{far,ppr,pmr}-detail.blade.php`. Test `tests/Feature/TeaserLinkTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/TeaserLinkTest.php`:
```php
<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Charity;
use App\Models\Issue;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function farReportWithTeaser(): array
{
    $report = Report::factory()->far()->for(Charity::factory())->create(['slug' => 'far-teaser-test']);
    $issue = Issue::factory()->for($report)->create(['is_current' => true]);
    $teaser = Asset::factory()->for($issue)->create(['type' => AssetType::Teaser]);

    return [$report, $teaser];
}

it('shows the sample link to an authenticated user when a teaser exists', function () {
    [$report, $teaser] = farReportWithTeaser();

    $this->actingAs(User::factory()->create())
        ->get('/reports/far-teaser-test')
        ->assertOk()
        ->assertSee(route('assets.download', $teaser), false);
});

it('does not show the sample link to guests', function () {
    [$report, $teaser] = farReportWithTeaser();

    $this->get('/reports/far-teaser-test')
        ->assertOk()
        ->assertDontSee(route('assets.download', $teaser), false);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=TeaserLinkTest`
Expected: FAIL (the link isn't rendered yet).

- [ ] **Step 3: Pass the teaser from the controller**

In `app/Http/Controllers/ReportController.php`, add the import at the top:
```php
use App\Enums\AssetType;
```
Replace the `show` method body's first line and add the teaser lookup, then include `'teaser' => $teaser` in all three `view(...)` arrays. The method becomes:
```php
    public function show(Report $report): View
    {
        $report->load('charity', 'provider', 'market', 'currentIssue.assets');
        $teaser = $report->currentIssue?->assets->firstWhere('type', AssetType::Teaser);

        return match ($report->type) {
            ReportType::FAR => view('reports.far-detail', [
                'report' => $report,
                'charity' => $report->charity,
                'issue' => $report->currentIssue,
                'teaser' => $teaser,
                'price' => Pricing::for('far', 'single'),
            ]),
            ReportType::PPR => view('reports.ppr-detail', [
                'report' => $report,
                'provider' => $report->provider,
                'issue' => $report->currentIssue,
                'teaser' => $teaser,
                'tiers' => [
                    ['name' => 'Standard', 'price' => Pricing::for('ppr', 'standard'), 'desc' => 'Named-provider report.'],
                    ['name' => 'Enhanced', 'price' => Pricing::for('ppr', 'enhanced'), 'desc' => 'Report + linked charity relationship dataset.'],
                    ['name' => 'Premium', 'price' => Pricing::for('ppr', 'premium'), 'desc' => 'Report + dataset + time-boxed FAR access to linked charities.'],
                ],
            ]),
            ReportType::PMR => view('reports.pmr-detail', [
                'report' => $report,
                'market' => $report->market,
                'issue' => $report->currentIssue,
                'teaser' => $teaser,
                'tiers' => [
                    ['name' => 'Standard', 'price' => Pricing::for('pmr', 'standard'), 'desc' => 'Category report.'],
                    ['name' => 'Premium', 'price' => Pricing::for('pmr', 'premium'), 'desc' => 'Category report + supporting data and defined FAR access.'],
                ],
            ]),
        };
    }
```

- [ ] **Step 4: Render the sample link in each detail view**

In each of `resources/views/reports/far-detail.blade.php`, `ppr-detail.blade.php`, `pmr-detail.blade.php`, add this block immediately **after** the closing `</div>` of the price/buy `<div class="mt-6 ...">` row:
```blade
    @auth
        @if ($teaser)
            <p class="mt-4">
                <a href="{{ route('assets.download', $teaser) }}" class="text-sm text-brand hover:underline">View free sample</a>
            </p>
        @endif
    @endauth
```

- [ ] **Step 5: Run it to verify it passes**

Run: `php artisan test --filter=TeaserLinkTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ReportController.php resources/views/reports/ tests/Feature/TeaserLinkTest.php
git commit -m "feat: surface registration-gated teaser link on report detail pages"
```

---

## Task 4: Seed demo teaser assets

**Files:** Modify `database/seeders/CatalogueDemoSeeder.php`.

This attaches a teaser asset (pointing at a shared placeholder key) to every seeded report's current issue, so the running app has something to deliver. The seeder stays DB-only; the placeholder PDF is uploaded once in the verification step below.

- [ ] **Step 1: Add the import**

At the top of `database/seeders/CatalogueDemoSeeder.php`, add:
```php
use App\Enums\AssetType;
use App\Models\Asset;
```

- [ ] **Step 2: Attach a teaser to each issue**

In `CatalogueDemoSeeder::run()`, immediately after **each** of the three `Issue::factory()->for($report)->create([...])` calls, capture the issue and attach a teaser. Change each `Issue::factory()->for($report)->create([...]);` to assign it: `$issue = Issue::factory()->for($report)->create([...]);` and add right after:
```php
            Asset::factory()->for($issue)->create([
                'type' => AssetType::Teaser,
                'disk' => 's3',
                'path' => 'teasers/sample-teaser.pdf',
                'original_filename' => 'sample-teaser.pdf',
                'mime' => 'application/pdf',
            ]);
```

- [ ] **Step 3: Re-seed and verify a teaser asset exists**

Run:
```bash
php artisan migrate:fresh --seed --force
php artisan tinker --execute="echo App\Models\Asset::where('type','teaser')->count().' teaser assets';"
```
Expected: a non-zero teaser asset count (one per report).

- [ ] **Step 4: Upload the placeholder teaser PDF to S3 (one-off, demo)**

Run:
```bash
php artisan tinker --execute="Illuminate\Support\Facades\Storage::disk('s3')->put('teasers/sample-teaser.pdf', file_get_contents('https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf'));"
```
(If the URL is unavailable, upload any small valid PDF to the key `teasers/sample-teaser.pdf`.) Expected: no error.

- [ ] **Step 5: Manual end-to-end check**

Recreate the local admin user (re-seed wiped it), serve the app, log in, open any report, click **View free sample** — it should redirect to a pre-signed S3 URL serving the placeholder PDF:
```bash
php artisan tinker --execute="App\Models\User::firstOrCreate(['email'=>'admin@qsanalysis.local'],['name'=>'QSA Admin','password'=>'password','email_verified_at'=>now()]);"
```

- [ ] **Step 6: Commit**

```bash
git add database/seeders/CatalogueDemoSeeder.php
git commit -m "feat: seed demo teaser assets on report issues"
```

---

## Self-Review

**1. Spec coverage:**
- §7 private-bucket delivery via gated pre-signed URL → Task 2 ✓
- §7 "resolves per entitlement; teaser registration-gated" → Tasks 1+2 (teaser=auth, paid=denied) ✓
- §4 single `AccessService` as the access source of truth → Task 1 ✓ (M2 extends the non-teaser branch)
- §6 teaser surfaced on detail page, registration-gated → Task 3 ✓
- Download audit log (§7) → **deferred to M4** (noted), not a gap for this slice.
- Paid-asset entitlement checks → **deferred to M2** by design.

**2. Placeholder scan:** No TBD/TODO; every code step has complete code. The "upload any small valid PDF" fallback in Task 4 Step 4 is a concrete demo instruction, not an implementation placeholder.

**3. Type consistency:** `AccessService::canAccess(?User, Asset): bool` is defined in Task 1 and used identically in Task 2. `AssetType::Teaser/ReportPdf/Dataset`, `Asset` `type`/`disk`/`path` columns, and the `assets.download` route name are consistent across Tasks 2–4. `currentIssue.assets` eager-load matches the `Issue hasMany assets` and `Report currentIssue` relations from M0.
