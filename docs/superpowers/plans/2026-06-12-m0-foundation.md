# M0 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a running Laravel 13 application with customer auth, a Filament admin panel, S3 wiring, and the core catalogue data model (Charity, Provider, Market, Report, Issue, Asset, ProviderCharityLink) — all tested.

**Architecture:** Laravel 13 monolith (Blade + Livewire + Tailwind) on Nginx + MariaDB, files on Amazon S3, Filament admin at `/admin`. This milestone builds the skeleton and the data model that every later milestone (catalogue, commerce, subscriptions, ingestion) depends on. No commerce or entitlement logic yet.

**Tech Stack:** PHP 8.3+, Laravel 13, Livewire, Tailwind CSS, Laravel Breeze (Livewire stack), Filament v3, Flysystem S3, Pest (tests), MariaDB.

**Reference spec:** `docs/superpowers/specs/2026-06-12-qs-analysis-ecommerce-portal-design.md` (§3 Domain & data model, §2 Architecture).

---

## Prerequisites

- PHP 8.3+ and Composer installed and on PATH (`php -v`, `composer -V`).
- Node 20+ and npm (`node -v`).
- MariaDB running (WAMP) with an empty database `qs_portal` and credentials you can use.
- An AWS S3 bucket name + IAM access key/secret (can be a dev bucket). For M0 we only **wire** S3; uploads are exercised in M1.
- The repo already contains `.git`, `.gitignore`, `docs/`, `project-brief.md`, `.claude/`. The Laravel scaffold must be merged in **without** clobbering these.

---

## File Structure

Created/modified in this milestone:

- `app/Enums/ReportType.php` — FAR/PPR/PMR enum (one responsibility: report type values)
- `app/Enums/AssetType.php` — report_pdf/dataset/teaser enum
- `app/Models/Charity.php` + `database/migrations/*_create_charities_table.php` + `database/factories/CharityFactory.php`
- `app/Models/Provider.php` + migration + factory
- `app/Models/Market.php` + migration + factory
- `app/Models/Report.php` + migration + factory — the catalogue Title; type + nullable subject FK + subject invariant
- `app/Models/Issue.php` + migration + factory — versioned release of a Report
- `app/Models/Asset.php` + migration + factory — a file on an Issue
- `app/Models/ProviderCharityLink.php` + migration + factory — the relationship dataset
- `config/filesystems.php` — add/confirm the `s3` disk
- `tests/Feature/Models/*Test.php` — one Pest test file per model
- Framework installs (Breeze, Filament) touch many files; those tasks verify by booting + running the suite.

Each model + its migration + factory + test form one self-contained task.

---

## Task 1: Scaffold Laravel 13 into the existing repo

**Files:** entire Laravel skeleton merged into repo root.

- [ ] **Step 1: Create the Laravel 13 skeleton in a temp directory**

Run:
```bash
composer create-project laravel/laravel /tmp/qs-laravel "^13.0"
```
Expected: Composer installs Laravel 13 into `/tmp/qs-laravel` and prints "Application ready!".

- [ ] **Step 2: Merge the skeleton into the repo, preserving existing files**

Run (from repo root `/mnt/c/wamp/www/qs-analysis-portal`):
```bash
rsync -a --ignore-existing \
  --exclude='.git' --exclude='.gitignore' --exclude='docs' \
  --exclude='project-brief.md' --exclude='.claude' \
  /tmp/qs-laravel/ ./
```
Expected: `composer.json`, `artisan`, `app/`, `config/`, `routes/`, etc. now exist in the repo root; `docs/`, `project-brief.md`, `.gitignore`, `.git` untouched.

- [ ] **Step 3: Install PHP + JS dependencies and generate the app key**

Run:
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```
Expected: `vendor/` and `node_modules/` populated; `.env` created; "Application key set successfully."

- [ ] **Step 4: Confirm Laravel's `.gitignore` entries are present**

Ensure `.gitignore` contains at least `/vendor`, `/node_modules`, `.env`, `/public/build`, `/storage/*.key`. (Our committed `.gitignore` already covers these.) Add `/.phpunit.cache` if missing.

- [ ] **Step 5: Verify the app boots**

Run:
```bash
php artisan about
```
Expected: prints environment table with Laravel version `13.x`, PHP version `8.3+`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 13 application"
```

---

## Task 2: Configure environment and database

**Files:** Modify `.env`.

- [ ] **Step 1: Set app + database env values**

Edit `.env` so these keys read (use your real DB credentials):
```env
APP_NAME="QS Analysis Portal"
APP_URL=http://localhost
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qs_portal
DB_USERNAME=root
DB_PASSWORD=
```
Note: Laravel 13 supports a dedicated `mariadb` connection driver; if unavailable, use `mysql`.

- [ ] **Step 2: Run the default migrations to confirm DB connectivity**

Run:
```bash
php artisan migrate
```
Expected: default tables (`users`, `cache`, `jobs`, etc.) created with no connection error.

- [ ] **Step 3: Run the test suite (baseline green)**

Run:
```bash
php artisan test
```
Expected: the default example tests PASS.

- [ ] **Step 4: Commit**

```bash
git add .env.example
git commit -m "chore: configure database connection" --allow-empty
```
(`.env` is gitignored; commit only the example if you mirrored keys into it.)

---

## Task 3: Install Breeze (Livewire) auth with email verification

**Files:** Breeze publishes auth Livewire components, routes, and Blade views; modifies `app/Models/User.php`.

- [ ] **Step 1: Install Breeze with the Livewire stack**

Run:
```bash
composer require laravel/breeze --dev
php artisan breeze:install livewire
npm install && npm run build
php artisan migrate
```
Expected: auth routes/views installed; Tailwind + Livewire + Alpine wired; build succeeds.
Note: If `breeze:install` is unavailable on Laravel 13, install the official **Livewire starter kit** instead — the required deliverable is identical: register, login, password reset, and email-verification screens on Livewire + Tailwind.

- [ ] **Step 2: Require email verification on the User model**

In `app/Models/User.php`, implement `MustVerifyEmail`:
```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    // ...existing...
}
```

- [ ] **Step 3: Write a failing test for verification gating**

Create `tests/Feature/Auth/VerificationGateTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unverified users away from the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirect('/verify-email');
});

it('allows verified users to view the dashboard', function () {
    $user = User::factory()->create(); // verified by default

    $this->actingAs($user)->get('/dashboard')->assertOk();
});
```

- [ ] **Step 4: Run it to verify behavior**

Run:
```bash
php artisan test --filter=VerificationGateTest
```
Expected: PASS if Breeze's `dashboard` route already has the `verified` middleware. If the first test FAILS (no redirect), add `'verified'` to the `dashboard` route middleware in `routes/web.php`, then re-run to green.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add customer auth with required email verification"
```

---

## Task 4: Install the Filament admin panel

**Files:** Creates `app/Providers/Filament/AdminPanelProvider.php`, publishes Filament assets.

- [ ] **Step 1: Install Filament v3 panels**

Run:
```bash
composer require filament/filament:"^3.0"
php artisan filament:install --panels
```
Expected: an admin panel registered at path `/admin`.

- [ ] **Step 2: Create an admin user**

Run:
```bash
php artisan make:filament-user
```
Enter name/email/password when prompted. Expected: "User created successfully."

- [ ] **Step 3: Smoke-test the panel boots**

Create `tests/Feature/Admin/AdminPanelTest.php`:
```php
<?php

it('serves the admin login page', function () {
    $this->get('/admin/login')->assertOk();
});
```
Run:
```bash
php artisan test --filter=AdminPanelTest
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: install Filament admin panel"
```

---

## Task 5: Configure the S3 filesystem disk

**Files:** Modify `config/filesystems.php` (the `s3` disk ships by default; this confirms it) and `.env`.

- [ ] **Step 1: Install the S3 driver**

Run:
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```
Expected: package installed.

- [ ] **Step 2: Set S3 env keys**

Add to `.env`:
```env
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=eu-west-2
AWS_BUCKET=qs-portal-reports
AWS_USE_PATH_STYLE_ENDPOINT=false
FILESYSTEM_DISK=local
```
(Keep app default disk `local`; report assets will explicitly target the `s3` disk.)

- [ ] **Step 3: Write a test that the s3 disk is configured**

Create `tests/Feature/StorageConfigTest.php`:
```php
<?php

it('has an s3 disk configured', function () {
    expect(config('filesystems.disks.s3.driver'))->toBe('s3');
    expect(config('filesystems.disks.s3.bucket'))->not->toBeNull();
});
```
Run:
```bash
php artisan test --filter=StorageConfigTest
```
Expected: PASS (set `AWS_BUCKET` in `phpunit.xml` env or `.env.testing` if the bucket assertion fails under testing).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: wire up S3 filesystem disk"
```

---

## Task 6: Create the report and asset enums

**Files:** Create `app/Enums/ReportType.php`, `app/Enums/AssetType.php`. Test `tests/Unit/EnumsTest.php`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/EnumsTest.php`:
```php
<?php

use App\Enums\AssetType;
use App\Enums\ReportType;

it('exposes the three report types', function () {
    expect(ReportType::cases())->toHaveCount(3);
    expect(ReportType::FAR->value)->toBe('far');
    expect(ReportType::PPR->value)->toBe('ppr');
    expect(ReportType::PMR->value)->toBe('pmr');
});

it('exposes the asset types', function () {
    expect(AssetType::ReportPdf->value)->toBe('report_pdf');
    expect(AssetType::Dataset->value)->toBe('dataset');
    expect(AssetType::Teaser->value)->toBe('teaser');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run:
```bash
php artisan test --filter=EnumsTest
```
Expected: FAIL with "Class App\Enums\ReportType not found".

- [ ] **Step 3: Create the enums**

`app/Enums/ReportType.php`:
```php
<?php

namespace App\Enums;

enum ReportType: string
{
    case FAR = 'far';
    case PPR = 'ppr';
    case PMR = 'pmr';
}
```

`app/Enums/AssetType.php`:
```php
<?php

namespace App\Enums;

enum AssetType: string
{
    case ReportPdf = 'report_pdf';
    case Dataset = 'dataset';
    case Teaser = 'teaser';
}
```

- [ ] **Step 4: Run it to verify it passes**

Run:
```bash
php artisan test --filter=EnumsTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add ReportType and AssetType enums"
```

---

## Task 7: Charity model

**Files:** Create `app/Models/Charity.php`, `database/migrations/2026_06_12_000001_create_charities_table.php`, `database/factories/CharityFactory.php`. Test `tests/Feature/Models/CharityTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/CharityTest.php`:
```php
<?php

use App\Models\Charity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a charity from the factory', function () {
    $charity = Charity::factory()->create(['cc_ref' => '1234567']);
    expect($charity->cc_ref)->toBe('1234567')
        ->and($charity->name)->not->toBeNull();
});

it('enforces a unique cc_ref', function () {
    Charity::factory()->create(['cc_ref' => '1234567']);
    Charity::factory()->create(['cc_ref' => '1234567']);
})->throws(QueryException::class);
```

- [ ] **Step 2: Run it to verify it fails**

Run:
```bash
php artisan test --filter=CharityTest
```
Expected: FAIL with "Class App\Models\Charity not found".

- [ ] **Step 3: Create migration, model, factory**

Migration `database/migrations/2026_06_12_000001_create_charities_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('charities', function (Blueprint $table) {
            $table->id();
            $table->string('cc_ref')->unique();
            $table->string('name');
            $table->decimal('latest_q_score', 5, 2)->nullable();
            $table->decimal('latest_stability', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charities');
    }
};
```

`app/Models/Charity.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Charity extends Model
{
    use HasFactory;

    protected $fillable = ['cc_ref', 'name', 'latest_q_score', 'latest_stability'];

    protected function casts(): array
    {
        return [
            'latest_q_score' => 'decimal:2',
            'latest_stability' => 'decimal:2',
        ];
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_charity_links');
    }
}
```

`database/factories/CharityFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CharityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cc_ref' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'name' => fake()->company().' Trust',
            'latest_q_score' => fake()->randomFloat(2, 20, 70),
            'latest_stability' => fake()->randomFloat(2, 15, 85),
        ];
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run:
```bash
php artisan test --filter=CharityTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Charity model"
```

---

## Task 8: Provider model

**Files:** Create `app/Models/Provider.php`, migration `..._000002_create_providers_table.php`, `database/factories/ProviderFactory.php`. Test `tests/Feature/Models/ProviderTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/ProviderTest.php`:
```php
<?php

use App\Models\Provider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a provider from the factory', function () {
    $provider = Provider::factory()->create(['code' => 'PRV-001']);
    expect($provider->code)->toBe('PRV-001');
});

it('enforces a unique code', function () {
    Provider::factory()->create(['code' => 'PRV-001']);
    Provider::factory()->create(['code' => 'PRV-001']);
})->throws(QueryException::class);
```

- [ ] **Step 2: Run it to verify it fails**

Run:
```bash
php artisan test --filter=ProviderTest
```
Expected: FAIL "Class App\Models\Provider not found".

- [ ] **Step 3: Create migration, model, factory**

Migration `..._000002_create_providers_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
```

`app/Models/Provider.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    public function charities(): BelongsToMany
    {
        return $this->belongsToMany(Charity::class, 'provider_charity_links');
    }
}
```

`database/factories/ProviderFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'PRV-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->company(),
        ];
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run:
```bash
php artisan test --filter=ProviderTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Provider model"
```

---

## Task 9: Market model

**Files:** Create `app/Models/Market.php`, migration `..._000003_create_markets_table.php`, `database/factories/MarketFactory.php`. Test `tests/Feature/Models/MarketTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/MarketTest.php`:
```php
<?php

use App\Models\Market;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a market from the factory', function () {
    $market = Market::factory()->create(['code' => 'MKT-001']);
    expect($market->code)->toBe('MKT-001');
});

it('enforces a unique code', function () {
    Market::factory()->create(['code' => 'MKT-001']);
    Market::factory()->create(['code' => 'MKT-001']);
})->throws(QueryException::class);
```

- [ ] **Step 2: Run it to verify it fails**

Run:
```bash
php artisan test --filter=MarketTest
```
Expected: FAIL "Class App\Models\Market not found".

- [ ] **Step 3: Create migration, model, factory**

Migration `..._000003_create_markets_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
```

`app/Models/Market.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Market extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }
}
```

`database/factories/MarketFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MarketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'MKT-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->words(3, true),
        ];
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run:
```bash
php artisan test --filter=MarketTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Market model"
```

---

## Task 10: Report model (catalogue Title with subject invariant)

**Files:** Create `app/Models/Report.php`, migration `..._000004_create_reports_table.php`, `database/factories/ReportFactory.php`. Test `tests/Feature/Models/ReportTest.php`.

Depends on Tasks 6–9.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/ReportTest.php`:
```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run:
```bash
php artisan test --filter=ReportTest
```
Expected: FAIL "Class App\Models\Report not found".

- [ ] **Step 3: Create migration, model, factory**

Migration `..._000004_create_reports_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // ReportType: far|ppr|pmr
            $table->foreignId('charity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
```

`app/Models/Report.php`:
```php
<?php

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Report extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'charity_id', 'provider_id', 'market_id', 'name', 'slug'];

    protected function casts(): array
    {
        return ['type' => ReportType::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Report $report): void {
            $type = $report->type instanceof ReportType ? $report->type : ReportType::from($report->type);
            $required = match ($type) {
                ReportType::FAR => 'charity_id',
                ReportType::PPR => 'provider_id',
                ReportType::PMR => 'market_id',
            };

            foreach (['charity_id', 'provider_id', 'market_id'] as $column) {
                if ($column === $required && empty($report->{$column})) {
                    throw new InvalidArgumentException("A {$type->value} report requires {$column}.");
                }
                if ($column !== $required && ! empty($report->{$column})) {
                    throw new InvalidArgumentException("A {$type->value} report must not set {$column}.");
                }
            }
        });
    }

    public function charity(): BelongsTo
    {
        return $this->belongsTo(Charity::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function currentIssue(): HasOne
    {
        return $this->hasOne(Issue::class)->where('is_current', true);
    }

    public function subject(): Charity|Provider|Market|null
    {
        return match ($this->type) {
            ReportType::FAR => $this->charity,
            ReportType::PPR => $this->provider,
            ReportType::PMR => $this->market,
        };
    }
}
```

`database/factories/ReportFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\Market;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ReportType::FAR,
            'charity_id' => Charity::factory(),
            'provider_id' => null,
            'market_id' => null,
            'name' => fake()->company().' Report',
            'slug' => fake()->unique()->slug(),
        ];
    }

    public function far(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::FAR,
            'charity_id' => Charity::factory(),
            'provider_id' => null,
            'market_id' => null,
        ]);
    }

    public function ppr(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::PPR,
            'charity_id' => null,
            'provider_id' => Provider::factory(),
            'market_id' => null,
        ]);
    }

    public function pmr(): static
    {
        return $this->state(fn () => [
            'type' => ReportType::PMR,
            'charity_id' => null,
            'provider_id' => null,
            'market_id' => Market::factory(),
        ]);
    }
}
```
Note: `->for($charity)` overrides `charity_id` for the FAR state; `->for($provider)` and `->for($market)` work the same for PPR/PMR because Laravel matches the related model to the `charity()`/`provider()`/`market()` relationship.

- [ ] **Step 4: Run it to verify it passes**

Run:
```bash
php artisan test --filter=ReportTest
```
Expected: PASS (all 5 cases).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Report model with subject invariant"
```

---

## Task 11: Issue model (versioned release)

**Files:** Create `app/Models/Issue.php`, migration `..._000005_create_issues_table.php`, `database/factories/IssueFactory.php`. Test `tests/Feature/Models/IssueTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/IssueTest.php`:
```php
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
```

- [ ] **Step 2: Run it to verify it fails**

Run:
```bash
php artisan test --filter=IssueTest
```
Expected: FAIL "Class App\Models\Issue not found".

- [ ] **Step 3: Create migration, model, factory**

Migration `..._000005_create_issues_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('version_label');
            $table->date('published_at');
            $table->boolean('is_current')->default(false);
            $table->decimal('q_score', 5, 2)->nullable();
            $table->decimal('stability', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'version_label']);
            $table->index(['report_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
```

`app/Models/Issue.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = ['report_id', 'version_label', 'published_at', 'is_current', 'q_score', 'stability'];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'is_current' => 'boolean',
            'q_score' => 'decimal:2',
            'stability' => 'decimal:2',
        ];
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
```

`database/factories/IssueFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

class IssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory()->far(),
            'version_label' => fake()->unique()->numerify('20## H#'),
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'is_current' => true,
            'q_score' => fake()->randomFloat(2, 20, 70),
            'stability' => fake()->randomFloat(2, 15, 85),
        ];
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run:
```bash
php artisan test --filter=IssueTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Issue model with current scope"
```

---

## Task 12: Asset model (a file on an Issue)

**Files:** Create `app/Models/Asset.php`, migration `..._000006_create_assets_table.php`, `database/factories/AssetFactory.php`. Test `tests/Feature/Models/AssetTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/AssetTest.php`:
```php
<?php

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Issue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to an issue and casts its type', function () {
    $issue = Issue::factory()->create();
    $asset = Asset::factory()->for($issue)->create(['type' => AssetType::ReportPdf]);

    expect($asset->issue->is($issue))->toBeTrue()
        ->and($asset->type)->toBe(AssetType::ReportPdf)
        ->and($asset->disk)->toBe('s3');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run:
```bash
php artisan test --filter=AssetTest
```
Expected: FAIL "Class App\Models\Asset not found".

- [ ] **Step 3: Create migration, model, factory**

Migration `..._000006_create_assets_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // AssetType: report_pdf|dataset|teaser
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
            $table->index(['issue_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
```

`app/Models/Asset.php`:
```php
<?php

namespace App\Models;

use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = ['issue_id', 'type', 'disk', 'path', 'original_filename', 'size', 'mime'];

    protected function casts(): array
    {
        return ['type' => AssetType::class];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
```

`database/factories/AssetFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\AssetType;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'type' => AssetType::ReportPdf,
            'disk' => 's3',
            'path' => 'far/'.fake()->numerify('#######').'/2026-h1/report.pdf',
            'original_filename' => 'report.pdf',
            'size' => fake()->numberBetween(50_000, 5_000_000),
            'mime' => 'application/pdf',
        ];
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run:
```bash
php artisan test --filter=AssetTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Asset model"
```

---

## Task 13: ProviderCharityLink model (relationship dataset)

**Files:** Create `app/Models/ProviderCharityLink.php`, migration `..._000007_create_provider_charity_links_table.php`, `database/factories/ProviderCharityLinkFactory.php`. Test `tests/Feature/Models/ProviderCharityLinkTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/ProviderCharityLinkTest.php`:
```php
<?php

use App\Models\Charity;
use App\Models\Provider;
use App\Models\ProviderCharityLink;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links a provider to a charity both ways', function () {
    $provider = Provider::factory()->create();
    $charity = Charity::factory()->create();
    ProviderCharityLink::factory()->create([
        'provider_id' => $provider->id,
        'charity_id' => $charity->id,
    ]);

    expect($provider->charities->pluck('id'))->toContain($charity->id)
        ->and($charity->providers->pluck('id'))->toContain($provider->id);
});

it('forbids duplicate provider/charity pairs', function () {
    $provider = Provider::factory()->create();
    $charity = Charity::factory()->create();
    $payload = ['provider_id' => $provider->id, 'charity_id' => $charity->id];
    ProviderCharityLink::factory()->create($payload);
    ProviderCharityLink::factory()->create($payload);
})->throws(QueryException::class);
```

- [ ] **Step 2: Run it to verify it fails**

Run:
```bash
php artisan test --filter=ProviderCharityLinkTest
```
Expected: FAIL "Class App\Models\ProviderCharityLink not found".

- [ ] **Step 3: Create migration, model, factory**

Migration `..._000007_create_provider_charity_links_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_charity_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['provider_id', 'charity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_charity_links');
    }
};
```

`app/Models/ProviderCharityLink.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderCharityLink extends Model
{
    use HasFactory;

    protected $fillable = ['provider_id', 'charity_id'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function charity(): BelongsTo
    {
        return $this->belongsTo(Charity::class);
    }
}
```

`database/factories/ProviderCharityLinkFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Charity;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderCharityLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'charity_id' => Charity::factory(),
        ];
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run:
```bash
php artisan test --filter=ProviderCharityLinkTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add ProviderCharityLink model"
```

---

## Task 14: Full-suite green + brand basics

**Files:** Modify `tailwind.config.js`, `.env` (`APP_NAME`).

- [ ] **Step 1: Set the brand primary colour**

In `tailwind.config.js`, extend the theme with a brand palette (placeholder hex to be refined against qsanalysis.co.uk in M1):
```js
theme: {
  extend: {
    colors: {
      brand: {
        DEFAULT: '#0b3d5c',
        light: '#11597f',
      },
    },
  },
},
```

- [ ] **Step 2: Rebuild assets**

Run:
```bash
npm run build
```
Expected: build succeeds.

- [ ] **Step 3: Run the entire test suite**

Run:
```bash
php artisan test
```
Expected: ALL tests PASS (enums, all 7 models, auth verification, admin panel, storage config).

- [ ] **Step 4: Run a fresh migrate to confirm migrations are clean**

Run:
```bash
php artisan migrate:fresh
```
Expected: all tables drop and recreate with no errors.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: set brand palette and confirm full suite green"
```

---

## Self-Review

**1. Spec coverage (against §2 Architecture and §3 Data model):**
- Laravel 13 + Livewire + Tailwind → Tasks 1, 3 ✓
- Accounts / auth / email verification → Task 3 ✓
- Filament admin → Task 4 ✓
- S3 wiring → Task 5 ✓
- Report (Title) with FAR/PPR/PMR subject → Task 10 ✓
- Issue (version, is_current, FAR Q/Stability) → Task 11 ✓
- Asset (report_pdf/dataset/teaser) → Task 12 ✓
- Charity (CC ref unique spine, latest Q/Stability) → Task 7 ✓
- Provider / Market subject tables → Tasks 8, 9 ✓
- ProviderCharityLink relationship dataset → Task 13 ✓
- Commerce/entitlements/catalogue/checkout/delivery/ingestion → **out of scope for M0** (M1–M3 plans) ✓ by design.

**2. Placeholder scan:** Brand hex in Task 14 is explicitly a refine-in-M1 value with a concrete default, not a blank. The Breeze fallback note in Task 3 gives a concrete primary command + concrete fallback deliverable. No bare TODOs.

**3. Type consistency:** `ReportType` {FAR, PPR, PMR} and `AssetType` {ReportPdf, Dataset, Teaser} are defined in Task 6 and used consistently in Tasks 10/12. Relationship names (`report`, `charity`, `provider`, `market`, `issues`, `currentIssue`, `assets`, `charities`, `providers`) match across factories and tests. Factory states `far()/ppr()/pmr()` defined in Task 10 are the ones used by Issue/Report tests.

---

## Next milestones (separate plans, written when reached)

- **M1 Catalogue + delivery** — FAR/PPR/PMR catalogues, FAR search (keyword + Q/Stability ranges), detail pages, secure pre-signed S3 download endpoint, `AccessService` skeleton, Import Batch v1.
- **M2 Commerce (one-off)** — Products/pricing, single-step Stripe one-off checkout, orders, FAR single + packs + PPR + PMR, pack claims, dashboard, refunds.
- **M3 Subscriptions + Premium** — recurring Stripe, subscription claims + auto-issue, Premium CrossAccessGrant, bespoke Premium PMR.
- **M4 Hardening** — full Import Batch UX, audit, analytics, compliance pages, security review, UAT.
