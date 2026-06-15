# M1a — Catalogue (FAR + PPR + PMR) + Detail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Public, browsable catalogues for all three report types — a PIR-style FAR table (Charity · CC ref · Q score · Stability) with keyword + Q-score-range + Stability-range filtering, plus simple PPR and PMR listings — and detail pages for each type showing tier pricing, all backed by a demo seeder and a pricing config.

**Architecture:** Laravel 13 + Livewire 3 (full-page component for the interactive FAR catalogue) + plain controllers for the low-volume PPR/PMR listings and all detail pages + Blade + Tailwind 3. The FAR catalogue queries `charities` (each charity has one FAR `Report`) filtered on indexed columns; PPR/PMR query `reports` by type with their subject. Prices come from `config/pricing.php` + small `Pricing`/`Money` helpers (the full `Product` model is M2). No entitlement logic yet (M1c) — pages show prices and a login-to-buy CTA (a disabled seam to M2 commerce).

**Tech Stack:** Laravel 13, Livewire 3, Pest, Tailwind 3, MySQL (`qsa_portal` / `qsa_portal_test`).

**Reference spec:** `docs/superpowers/specs/2026-06-12-qs-analysis-ecommerce-portal-design.md` (§5 Catalogue & search, §4 tier semantics, Appendix A pricing).

**Builds on M0:** `Charity`/`Provider`/`Market`, `Report` (type + `far()/ppr()/pmr()` factory states + unique `slug` + `subject()`), `Issue` (`is_current`, `currentIssue` relation, `q_score`/`stability`), `ProviderCharityLink`.

**Pricing (dummy, per spec):** FAR single £25; PPR Standard £50 / Enhanced £75 / Premium £100; PMR Standard £50 / Premium £100.

---

## File Structure

- `config/pricing.php` — dummy prices in pence
- `app/Support/Money.php` — format pence → "£X.XX"
- `app/Support/Pricing.php` — look up a price by type/tier
- `database/migrations/2026_06_13_000001_add_search_indexes_to_charities.php` — index Q-score/Stability
- `database/seeders/CatalogueDemoSeeder.php` (+ register in `DatabaseSeeder`) — FAR/PPR/PMR demo data + links
- `resources/views/components/public.blade.php` — anonymous Blade layout component (used as both page wrapper and Livewire layout)
- `app/Livewire/FarCatalogue.php` + `resources/views/livewire/far-catalogue.blade.php` — interactive FAR catalogue
- `app/Http/Controllers/CatalogueController.php` + `resources/views/catalogue/ppr.blade.php` + `resources/views/catalogue/pmr.blade.php` — PPR/PMR listings
- `app/Http/Controllers/ReportController.php` + `resources/views/reports/{far,ppr,pmr}-detail.blade.php` — detail pages
- `routes/web.php` — catalogue + detail routes
- Tests under `tests/Feature/` and `tests/Unit/`

---

## Task 1: Pricing config + Money/Pricing helpers

**Files:** Create `config/pricing.php`, `app/Support/Money.php`, `app/Support/Pricing.php`. Test `tests/Unit/PricingTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/PricingTest.php`:
```php
<?php

use App\Support\Money;
use App\Support\Pricing;

it('formats pence as pounds', function () {
    expect(Money::format(2500))->toBe('£25.00')
        ->and(Money::format(0))->toBe('£0.00')
        ->and(Money::format(187500))->toBe('£1,875.00');
});

it('returns configured prices in pence', function () {
    expect(Pricing::for('far', 'single'))->toBe(2500)
        ->and(Pricing::for('ppr', 'standard'))->toBe(5000)
        ->and(Pricing::for('ppr', 'enhanced'))->toBe(7500)
        ->and(Pricing::for('ppr', 'premium'))->toBe(10000)
        ->and(Pricing::for('pmr', 'standard'))->toBe(5000)
        ->and(Pricing::for('pmr', 'premium'))->toBe(10000);
});

it('returns null for an unknown price', function () {
    expect(Pricing::for('pmr', 'enhanced'))->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=PricingTest`
Expected: FAIL ("Class App\Support\Money not found").

- [ ] **Step 3: Create the config and helpers**

`config/pricing.php`:
```php
<?php

// All prices in pence (integers). Dummy values set 2026-06-13 — revisit with client.
return [
    'far' => ['single' => 2500],
    'ppr' => ['standard' => 5000, 'enhanced' => 7500, 'premium' => 10000],
    'pmr' => ['standard' => 5000, 'premium' => 10000],
];
```

`app/Support/Money.php`:
```php
<?php

namespace App\Support;

class Money
{
    public static function format(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }
}
```

`app/Support/Pricing.php`:
```php
<?php

namespace App\Support;

class Pricing
{
    public static function for(string $type, string $tier): ?int
    {
        $value = config("pricing.{$type}.{$tier}");

        return is_int($value) ? $value : null;
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=PricingTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add pricing config and Money/Pricing helpers"
```

---

## Task 2: Search indexes on charities

**Files:** Create `database/migrations/2026_06_13_000001_add_search_indexes_to_charities.php`. Test `tests/Feature/CharityIndexesTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/CharityIndexesTest.php`:
```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('indexes the charity search columns', function () {
    $indexes = collect(Schema::getIndexes('charities'))
        ->pluck('columns')
        ->flatten()
        ->all();

    expect($indexes)->toContain('latest_q_score')
        ->and($indexes)->toContain('latest_stability');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=CharityIndexesTest`
Expected: FAIL.

- [ ] **Step 3: Create the migration**

`database/migrations/2026_06_13_000001_add_search_indexes_to_charities.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('charities', function (Blueprint $table) {
            $table->index('latest_q_score');
            $table->index('latest_stability');
        });
    }

    public function down(): void
    {
        Schema::table('charities', function (Blueprint $table) {
            $table->dropIndex(['latest_q_score']);
            $table->dropIndex(['latest_stability']);
        });
    }
};
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=CharityIndexesTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: index charity Q-score and stability for search"
```

---

## Task 3: Demo data seeder (FAR + PPR + PMR + links)

**Files:** Create `database/seeders/CatalogueDemoSeeder.php`. Modify `database/seeders/DatabaseSeeder.php`. Test `tests/Feature/CatalogueDemoSeederTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/CatalogueDemoSeederTest.php`:
```php
<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\Issue;
use App\Models\Provider;
use App\Models\ProviderCharityLink;
use App\Models\Report;
use Database\Seeders\CatalogueDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds FAR, PPR and PMR catalogue data with current issues and links', function () {
    $this->seed(CatalogueDemoSeeder::class);

    expect(Charity::count())->toBe(60)
        ->and(Provider::count())->toBe(8)
        ->and(Report::where('type', ReportType::FAR)->count())->toBe(60)
        ->and(Report::where('type', ReportType::PPR)->count())->toBe(8)
        ->and(Report::where('type', ReportType::PMR)->count())->toBe(5)
        ->and(Issue::where('is_current', true)->count())->toBe(73)
        ->and(ProviderCharityLink::count())->toBe(40);

    $far = Report::where('type', ReportType::FAR)->first();
    expect($far->slug)->toStartWith('far-')
        ->and($far->currentIssue)->not->toBeNull();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=CatalogueDemoSeederTest`
Expected: FAIL ("Class Database\Seeders\CatalogueDemoSeeder not found").

- [ ] **Step 3: Create the seeder and register it**

`database/seeders/CatalogueDemoSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Charity;
use App\Models\Issue;
use App\Models\Market;
use App\Models\Provider;
use App\Models\ProviderCharityLink;
use App\Models\Report;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $charities = Charity::factory(60)->create();

        $charities->each(function (Charity $charity) {
            $report = Report::factory()->far()->for($charity)->create([
                'name' => $charity->name.' — Financial Analysis Report',
                'slug' => 'far-'.$charity->cc_ref,
            ]);
            Issue::factory()->for($report)->create([
                'version_label' => '2026 H1',
                'is_current' => true,
                'q_score' => $charity->latest_q_score,
                'stability' => $charity->latest_stability,
            ]);
        });

        Provider::factory(8)->create()->each(function (Provider $provider) use ($charities) {
            $report = Report::factory()->ppr()->for($provider)->create([
                'name' => $provider->name.' — Provider Portfolio Report',
                'slug' => 'ppr-'.Str::lower($provider->code),
            ]);
            Issue::factory()->for($report)->create([
                'version_label' => '2026 H1',
                'is_current' => true,
                'q_score' => null,
                'stability' => null,
            ]);
            $charities->random(5)->each(fn (Charity $c) => ProviderCharityLink::firstOrCreate([
                'provider_id' => $provider->id,
                'charity_id' => $c->id,
            ]));
        });

        Market::factory(5)->create()->each(function (Market $market) {
            $report = Report::factory()->pmr()->for($market)->create([
                'name' => $market->name.' — Provider Market Report',
                'slug' => 'pmr-'.Str::lower($market->code),
            ]);
            Issue::factory()->for($report)->create([
                'version_label' => '2026 H1',
                'is_current' => true,
                'q_score' => null,
                'stability' => null,
            ]);
        });
    }
}
```

Modify `database/seeders/DatabaseSeeder.php` — set the `run()` body to:
```php
    public function run(): void
    {
        $this->call(CatalogueDemoSeeder::class);
    }
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=CatalogueDemoSeederTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add catalogue demo data seeder (FAR/PPR/PMR + links)"
```

---

## Task 4: Public layout component

**Files:** Create `resources/views/components/public.blade.php`. Test `tests/Feature/PublicLayoutTest.php`.

A single anonymous Blade component used two ways: as a page wrapper (`<x-public title="...">…</x-public>`) and as the Livewire full-page layout (`#[Layout('components.public')]`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/PublicLayoutTest.php`:
```php
<?php

it('renders the public layout component with a title and slot', function () {
    $this->blade('<x-public title="Probe">PROBE_CONTENT</x-public>')
        ->assertSee('PROBE_CONTENT')
        ->assertSee('QS Analysis');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=PublicLayoutTest`
Expected: FAIL (component not found).

- [ ] **Step 3: Create the component**

`resources/views/components/public.blade.php`:
```blade
@props(['title' => 'QS Analysis'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <header class="bg-brand text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
            <a href="{{ url('/') }}" class="text-lg font-semibold">QS Analysis</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('catalogue.far') }}" class="hover:underline">FAR</a>
                <a href="{{ route('catalogue.ppr') }}" class="hover:underline">PPR</a>
                <a href="{{ route('catalogue.pmr') }}" class="hover:underline">PMR</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="hover:underline">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hover:underline">Log in</a>
                    <a href="{{ route('register') }}" class="rounded bg-white px-3 py-1 font-medium text-brand">Register</a>
                @endauth
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=PublicLayoutTest`
Expected: PASS. (The build manifest must exist for `@vite`; it does from M0's `npm run build`.)

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add public layout component"
```

---

## Task 5: FAR catalogue Livewire component

**Files:** Create `app/Livewire/FarCatalogue.php`, `resources/views/livewire/far-catalogue.blade.php`. Modify `routes/web.php`. Test `tests/Feature/FarCatalogueTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/FarCatalogueTest.php`:
```php
<?php

use App\Models\Charity;
use App\Models\Report;
use App\Livewire\FarCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function farCharity(array $attrs): Charity
{
    $charity = Charity::factory()->create($attrs);
    Report::factory()->far()->for($charity)->create(['slug' => 'far-'.$charity->cc_ref]);

    return $charity;
}

it('lists FAR charities', function () {
    farCharity(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);

    Livewire::test(FarCatalogue::class)->assertSee('Oxfam');
});

it('filters by keyword on name', function () {
    farCharity(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);
    farCharity(['name' => 'Barnardos', 'cc_ref' => '2222222', 'latest_q_score' => 40, 'latest_stability' => 30]);

    Livewire::test(FarCatalogue::class)
        ->set('search', 'Oxf')
        ->assertSee('Oxfam')
        ->assertDontSee('Barnardos');
});

it('filters by Q score range', function () {
    farCharity(['name' => 'HighQ', 'cc_ref' => '3333333', 'latest_q_score' => 65, 'latest_stability' => 50]);
    farCharity(['name' => 'LowQ', 'cc_ref' => '4444444', 'latest_q_score' => 25, 'latest_stability' => 50]);

    Livewire::test(FarCatalogue::class)
        ->set('qMin', 50)
        ->assertSee('HighQ')
        ->assertDontSee('LowQ');
});

it('filters by stability range', function () {
    farCharity(['name' => 'Stable', 'cc_ref' => '5555555', 'latest_q_score' => 50, 'latest_stability' => 80]);
    farCharity(['name' => 'Shaky', 'cc_ref' => '6666666', 'latest_q_score' => 50, 'latest_stability' => 20]);

    Livewire::test(FarCatalogue::class)
        ->set('stabilityMax', 50)
        ->assertSee('Shaky')
        ->assertDontSee('Stable');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=FarCatalogueTest`
Expected: FAIL ("Class App\Livewire\FarCatalogue not found").

- [ ] **Step 3: Create the component, view, and route**

`app/Livewire/FarCatalogue.php`:
```php
<?php

namespace App\Livewire;

use App\Models\Charity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.public')]
class FarCatalogue extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $qMin = null;

    #[Url]
    public ?int $qMax = null;

    #[Url]
    public ?int $stabilityMin = null;

    #[Url]
    public ?int $stabilityMax = null;

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDir = 'asc';

    public function updating($name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['name', 'latest_q_score', 'latest_stability'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
    }

    public function render(): View
    {
        $charities = Charity::query()
            ->whereHas('report', fn ($q) => $q->where('type', 'far'))
            ->with('report:id,charity_id,slug')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('cc_ref', 'like', $term));
            })
            ->when($this->qMin !== null, fn ($q) => $q->where('latest_q_score', '>=', $this->qMin))
            ->when($this->qMax !== null, fn ($q) => $q->where('latest_q_score', '<=', $this->qMax))
            ->when($this->stabilityMin !== null, fn ($q) => $q->where('latest_stability', '>=', $this->stabilityMin))
            ->when($this->stabilityMax !== null, fn ($q) => $q->where('latest_stability', '<=', $this->stabilityMax))
            ->orderBy($this->sortField, $this->sortDir === 'desc' ? 'desc' : 'asc')
            ->paginate(20);

        return view('livewire.far-catalogue', ['charities' => $charities]);
    }
}
```

`resources/views/livewire/far-catalogue.blade.php`:
```blade
<div>
    <h1 class="mb-6 text-2xl font-bold text-brand">Financial Analysis Reports</h1>

    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-5">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search charity name or CC ref"
               class="md:col-span-2 rounded border-gray-300">
        <input type="number" wire:model.live="qMin" placeholder="Q min" class="rounded border-gray-300">
        <input type="number" wire:model.live="qMax" placeholder="Q max" class="rounded border-gray-300">
        <input type="number" wire:model.live="stabilityMin" placeholder="Stability min" class="rounded border-gray-300">
        <input type="number" wire:model.live="stabilityMax" placeholder="Stability max" class="rounded border-gray-300">
    </div>

    <div class="overflow-x-auto rounded border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="cursor-pointer px-4 py-2" wire:click="sortBy('name')">Charity</th>
                    <th class="px-4 py-2">CC ref</th>
                    <th class="cursor-pointer px-4 py-2" wire:click="sortBy('latest_q_score')">Q score</th>
                    <th class="cursor-pointer px-4 py-2" wire:click="sortBy('latest_stability')">Stability</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($charities as $charity)
                    <tr wire:key="charity-{{ $charity->id }}">
                        <td class="px-4 py-2 font-medium">{{ $charity->name }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $charity->cc_ref }}</td>
                        <td class="px-4 py-2">{{ $charity->latest_q_score }}</td>
                        <td class="px-4 py-2">{{ $charity->latest_stability }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('reports.show', $charity->report->slug) }}"
                               class="text-brand hover:underline">View report</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No reports match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $charities->links() }}</div>
</div>
```

Modify `routes/web.php` — add after `Route::view('/', 'welcome');`:
```php
Route::get('/catalogue/far', \App\Livewire\FarCatalogue::class)->name('catalogue.far');
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=FarCatalogueTest`
Expected: PASS (4 cases).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add FAR catalogue with keyword, Q-score and stability filters"
```

---

## Task 6: PPR & PMR catalogue listings

**Files:** Create `app/Http/Controllers/CatalogueController.php`, `resources/views/catalogue/ppr.blade.php`, `resources/views/catalogue/pmr.blade.php`. Modify `routes/web.php`. Test `tests/Feature/CatalogueListingTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/CatalogueListingTest.php`:
```php
<?php

use App\Models\Market;
use App\Models\Provider;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists PPR provider reports with a from-price', function () {
    $provider = Provider::factory()->create(['name' => 'Acme Care', 'code' => 'PRV-1000']);
    Report::factory()->ppr()->for($provider)->create(['slug' => 'ppr-prv-1000', 'name' => 'Acme Care PPR']);

    $this->get('/catalogue/ppr')
        ->assertOk()
        ->assertSee('Acme Care')
        ->assertSee('£50.00')
        ->assertSee('/reports/ppr-prv-1000');
});

it('lists PMR market reports with a from-price', function () {
    $market = Market::factory()->create(['name' => 'Homelessness', 'code' => 'MKT-2000']);
    Report::factory()->pmr()->for($market)->create(['slug' => 'pmr-mkt-2000', 'name' => 'Homelessness PMR']);

    $this->get('/catalogue/pmr')
        ->assertOk()
        ->assertSee('Homelessness')
        ->assertSee('£50.00')
        ->assertSee('/reports/pmr-mkt-2000');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=CatalogueListingTest`
Expected: FAIL (routes/controller missing).

- [ ] **Step 3: Create the controller, views, and routes**

`app/Http/Controllers/CatalogueController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Report;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function ppr(): View
    {
        $reports = Report::query()
            ->where('type', ReportType::PPR)
            ->with('provider')
            ->orderBy('name')
            ->paginate(20);

        return view('catalogue.ppr', ['reports' => $reports]);
    }

    public function pmr(): View
    {
        $reports = Report::query()
            ->where('type', ReportType::PMR)
            ->with('market')
            ->orderBy('name')
            ->paginate(20);

        return view('catalogue.pmr', ['reports' => $reports]);
    }
}
```

`resources/views/catalogue/ppr.blade.php`:
```blade
<x-public title="Provider Portfolio Reports">
    <h1 class="mb-6 text-2xl font-bold text-brand">Provider Portfolio Reports</h1>
    <div class="divide-y divide-gray-100 rounded border border-gray-200 bg-white">
        @forelse ($reports as $report)
            <div class="flex items-center justify-between px-4 py-3">
                <a href="{{ route('reports.show', $report->slug) }}" class="font-medium text-brand hover:underline">
                    {{ $report->provider->name }}
                </a>
                <span class="text-sm text-gray-600">from {{ \App\Support\Money::format(\App\Support\Pricing::for('ppr', 'standard')) }}</span>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-gray-500">No provider reports yet.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $reports->links() }}</div>
</x-public>
```

`resources/views/catalogue/pmr.blade.php`:
```blade
<x-public title="Provider Market Reports">
    <h1 class="mb-6 text-2xl font-bold text-brand">Provider Market Reports</h1>
    <div class="divide-y divide-gray-100 rounded border border-gray-200 bg-white">
        @forelse ($reports as $report)
            <div class="flex items-center justify-between px-4 py-3">
                <a href="{{ route('reports.show', $report->slug) }}" class="font-medium text-brand hover:underline">
                    {{ $report->market->name }}
                </a>
                <span class="text-sm text-gray-600">from {{ \App\Support\Money::format(\App\Support\Pricing::for('pmr', 'standard')) }}</span>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-gray-500">No market reports yet.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $reports->links() }}</div>
</x-public>
```

Modify `routes/web.php` — add:
```php
Route::get('/catalogue/ppr', [\App\Http\Controllers\CatalogueController::class, 'ppr'])->name('catalogue.ppr');
Route::get('/catalogue/pmr', [\App\Http\Controllers\CatalogueController::class, 'pmr'])->name('catalogue.pmr');
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=CatalogueListingTest`
Expected: PASS (2 cases).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add PPR and PMR catalogue listings"
```

---

## Task 7: Report detail pages (FAR + PPR + PMR)

**Files:** Create `app/Http/Controllers/ReportController.php`, `resources/views/reports/far-detail.blade.php`, `resources/views/reports/ppr-detail.blade.php`, `resources/views/reports/pmr-detail.blade.php`. Modify `routes/web.php`. Test `tests/Feature/ReportDetailTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportDetailTest.php`:
```php
<?php

use App\Models\Charity;
use App\Models\Issue;
use App\Models\Market;
use App\Models\Provider;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a FAR detail page with charity data and price', function () {
    $charity = Charity::factory()->create(['name' => 'Oxfam', 'cc_ref' => '1111111', 'latest_q_score' => 60, 'latest_stability' => 50]);
    $report = Report::factory()->far()->for($charity)->create(['slug' => 'far-1111111']);
    Issue::factory()->for($report)->create(['is_current' => true, 'version_label' => '2026 H1']);

    $this->get('/reports/far-1111111')
        ->assertOk()->assertSee('Oxfam')->assertSee('1111111')->assertSee('2026 H1')->assertSee('£25.00');
});

it('shows a PPR detail page with three tier prices', function () {
    $provider = Provider::factory()->create(['name' => 'Acme Care', 'code' => 'PRV-1000']);
    $report = Report::factory()->ppr()->for($provider)->create(['slug' => 'ppr-prv-1000']);
    Issue::factory()->for($report)->create(['is_current' => true]);

    $this->get('/reports/ppr-prv-1000')
        ->assertOk()->assertSee('Acme Care')
        ->assertSee('£50.00')->assertSee('£75.00')->assertSee('£100.00');
});

it('shows a PMR detail page with two tier prices', function () {
    $market = Market::factory()->create(['name' => 'Homelessness', 'code' => 'MKT-2000']);
    $report = Report::factory()->pmr()->for($market)->create(['slug' => 'pmr-mkt-2000']);
    Issue::factory()->for($report)->create(['is_current' => true]);

    $this->get('/reports/pmr-mkt-2000')
        ->assertOk()->assertSee('Homelessness')
        ->assertSee('£50.00')->assertSee('£100.00')->assertDontSee('£75.00');
});

it('returns 404 for an unknown report slug', function () {
    $this->get('/reports/does-not-exist')->assertNotFound();
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=ReportDetailTest`
Expected: FAIL.

- [ ] **Step 3: Create the controller, views, and route**

`app/Http/Controllers/ReportController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Report;
use App\Support\Pricing;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function show(Report $report): View
    {
        $report->load('charity', 'provider', 'market', 'currentIssue');

        return match ($report->type) {
            ReportType::FAR => view('reports.far-detail', [
                'report' => $report,
                'charity' => $report->charity,
                'issue' => $report->currentIssue,
                'price' => Pricing::for('far', 'single'),
            ]),
            ReportType::PPR => view('reports.ppr-detail', [
                'report' => $report,
                'provider' => $report->provider,
                'issue' => $report->currentIssue,
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
                'tiers' => [
                    ['name' => 'Standard', 'price' => Pricing::for('pmr', 'standard'), 'desc' => 'Category report.'],
                    ['name' => 'Premium', 'price' => Pricing::for('pmr', 'premium'), 'desc' => 'Category report + supporting data and defined FAR access.'],
                ],
            ]),
        };
    }
}
```

`resources/views/reports/far-detail.blade.php`:
```blade
<x-public :title="$charity->name">
    <a href="{{ route('catalogue.far') }}" class="text-sm text-brand hover:underline">&larr; Back to FAR catalogue</a>
    <h1 class="mt-2 text-2xl font-bold text-brand">{{ $charity->name }}</h1>
    <p class="text-gray-600">Charity Commission ref: {{ $charity->cc_ref }}</p>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:max-w-md">
        <div class="rounded border border-gray-200 bg-white p-4">
            <div class="text-xs uppercase text-gray-500">Q score</div>
            <div class="text-2xl font-semibold">{{ $charity->latest_q_score }}</div>
        </div>
        <div class="rounded border border-gray-200 bg-white p-4">
            <div class="text-xs uppercase text-gray-500">Stability</div>
            <div class="text-2xl font-semibold">{{ $charity->latest_stability }}</div>
        </div>
    </div>

    @if ($issue)
        <p class="mt-4 text-sm text-gray-600">Current issue: {{ $issue->version_label }}
            (published {{ $issue->published_at?->format('j M Y') }})</p>
    @endif

    <div class="mt-6 flex items-center gap-4">
        <span class="text-2xl font-bold">{{ \App\Support\Money::format($price) }}</span>
        @auth
            <button class="rounded bg-brand px-5 py-2 font-medium text-white" disabled title="Checkout arrives in M2">Buy now</button>
        @else
            <a href="{{ route('login') }}" class="rounded bg-brand px-5 py-2 font-medium text-white">Log in to buy</a>
        @endauth
    </div>
</x-public>
```

`resources/views/reports/ppr-detail.blade.php`:
```blade
<x-public :title="$provider->name">
    <a href="{{ route('catalogue.ppr') }}" class="text-sm text-brand hover:underline">&larr; Back to PPR catalogue</a>
    <h1 class="mt-2 text-2xl font-bold text-brand">{{ $provider->name }}</h1>
    <p class="text-gray-600">Provider Portfolio Report</p>
    @if ($issue)
        <p class="mt-1 text-sm text-gray-600">Current version: {{ $issue->version_label }}</p>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach ($tiers as $tier)
            <div class="rounded border border-gray-200 bg-white p-4">
                <div class="text-lg font-semibold">{{ $tier['name'] }}</div>
                <div class="my-2 text-2xl font-bold">{{ \App\Support\Money::format($tier['price']) }}</div>
                <p class="text-sm text-gray-600">{{ $tier['desc'] }}</p>
                @auth
                    <button class="mt-3 w-full rounded bg-brand px-3 py-2 text-sm font-medium text-white" disabled title="Checkout arrives in M2">Buy {{ $tier['name'] }}</button>
                @else
                    <a href="{{ route('login') }}" class="mt-3 block rounded bg-brand px-3 py-2 text-center text-sm font-medium text-white">Log in to buy</a>
                @endauth
            </div>
        @endforeach
    </div>
</x-public>
```

`resources/views/reports/pmr-detail.blade.php`:
```blade
<x-public :title="$market->name">
    <a href="{{ route('catalogue.pmr') }}" class="text-sm text-brand hover:underline">&larr; Back to PMR catalogue</a>
    <h1 class="mt-2 text-2xl font-bold text-brand">{{ $market->name }}</h1>
    <p class="text-gray-600">Provider Market Report</p>
    @if ($issue)
        <p class="mt-1 text-sm text-gray-600">Current version: {{ $issue->version_label }}</p>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 sm:max-w-xl">
        @foreach ($tiers as $tier)
            <div class="rounded border border-gray-200 bg-white p-4">
                <div class="text-lg font-semibold">{{ $tier['name'] }}</div>
                <div class="my-2 text-2xl font-bold">{{ \App\Support\Money::format($tier['price']) }}</div>
                <p class="text-sm text-gray-600">{{ $tier['desc'] }}</p>
                @auth
                    <button class="mt-3 w-full rounded bg-brand px-3 py-2 text-sm font-medium text-white" disabled title="Checkout arrives in M2">Buy {{ $tier['name'] }}</button>
                @else
                    <a href="{{ route('login') }}" class="mt-3 block rounded bg-brand px-3 py-2 text-center text-sm font-medium text-white">Log in to buy</a>
                @endauth
            </div>
        @endforeach
    </div>
</x-public>
```

Modify `routes/web.php` — add (route-model binding on `slug`):
```php
Route::get('/reports/{report:slug}', [\App\Http\Controllers\ReportController::class, 'show'])->name('reports.show');
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=ReportDetailTest`
Expected: PASS (4 cases).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add FAR/PPR/PMR report detail pages with tier pricing"
```

---

## Task 8: Homepage CTA + full-suite green

**Files:** Modify `resources/views/welcome.blade.php`. Test `tests/Feature/HomepageTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/HomepageTest.php`:
```php
<?php

it('links to all three catalogues from the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('catalogue.far'))
        ->assertSee(route('catalogue.ppr'))
        ->assertSee(route('catalogue.pmr'));
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=HomepageTest`
Expected: FAIL.

- [ ] **Step 3: Add the links**

In `resources/views/welcome.blade.php`, insert this markup inside the existing `<body>` content (e.g. near the top of the main content area):
```blade
<nav class="flex gap-3 p-6">
    <a href="{{ route('catalogue.far') }}" class="rounded bg-brand px-5 py-2 font-medium text-white">FAR catalogue</a>
    <a href="{{ route('catalogue.ppr') }}" class="rounded bg-brand px-5 py-2 font-medium text-white">PPR catalogue</a>
    <a href="{{ route('catalogue.pmr') }}" class="rounded bg-brand px-5 py-2 font-medium text-white">PMR catalogue</a>
</nav>
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=HomepageTest`
Expected: PASS.

- [ ] **Step 5: Seed and confirm the full suite**

Run:
```bash
php artisan migrate:fresh --seed
php artisan test
```
Expected: seed populates FAR/PPR/PMR demo data; full suite ALL green. Then recreate the local admin user:
```bash
php artisan tinker --execute="App\Models\User::firstOrCreate(['email'=>'admin@qsanalysis.local'],['name'=>'QSA Admin','password'=>'password','email_verified_at'=>now()]);"
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: link homepage to all three catalogues"
```

---

## Self-Review

**1. Spec coverage (§5 Catalogue & search; §4 tiers; Appendix A):**
- FAR catalogue columns + keyword/Q/Stability filters + sort + server pagination → Task 5 ✓
- PPR catalogue (tens of rows, simple browse) → Task 6 ✓
- PMR catalogue (simple browse) → Task 6 ✓
- FAR detail (Q/Stability, current version, £25) → Task 7 ✓
- PPR detail with Standard/Enhanced/Premium tiers + correct descriptions/prices → Task 7 ✓
- PMR detail with Standard/Premium tiers (no Enhanced) → Task 7 ✓ (test asserts £75 absent)
- Public browse (anonymous) → unauthenticated routes ✓
- Demo data → Task 3 ✓; Pricing (Appendix A) → Tasks 1/6/7 ✓
- Entitlement-aware buttons + secure delivery → **out of scope** (M1c); buttons are price + login CTA seams ✓

**2. Placeholder scan:** No TBD/TODO. Disabled "Buy" buttons are deliberate seams to M2 with explanatory `title`, not placeholders.

**3. Type consistency:** `Pricing::for($type,$tier): ?int` and `Money::format(int): string` used consistently (Tasks 1,6,7). Route names `catalogue.far|ppr|pmr` and `reports.show` defined in Tasks 5/6/7 and referenced across Tasks 4–8. Livewire props (`search`,`qMin`,`qMax`,`stabilityMin`,`stabilityMax`,`sortField`,`sortDir`) match component and tests. Slug conventions `far-{cc_ref}`/`ppr-{lower code}`/`pmr-{lower code}` match between seeder (Task 3), tests, and detail routing. The `<x-public>` component (Task 4) is used as both page wrapper (Tasks 6,7) and Livewire layout (Task 5).

---

## Remaining M1 sub-plans (written when reached)

- **M1c** — `AccessService` + secure pre-signed S3 download endpoint (needs IAM `s3:ListBucket`); upgrades catalogue/detail buttons to entitlement-aware state.
- **M1d** — Import Batch v1: admin upload of the Excel index + PDFs + provider↔charity links, validate → publish.
