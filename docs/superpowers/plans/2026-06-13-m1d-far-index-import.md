# M1d-v1 FAR Index Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import a supplied FAR index file (charity name, CC ref, Q score, Stability) to upsert real `Charity` + FAR `Report` + a current `Issue` per import batch — replacing the random demo seeder data with structured, re-runnable import logic, driven by an Artisan command.

**Architecture:** Three small units. `ImportBatch` records a single import run and its counts. `FarIndexImporter` is the business logic: given a batch + normalised rows, it upserts charities by **CC ref** (the identity spine), ensures each charity's FAR report, and creates a new current issue (flipping prior issues off-current) — all in one transaction. `FarIndexCsv` reads a CSV into normalised rows via the already-installed `league/csv`. An `import:far-index` Artisan command wires them together. (A Filament admin upload page is the next slice, M1d-2.)

**Tech Stack:** Laravel 13, Pest, MySQL, `league/csv` (already installed). No new dependencies.

**Reference spec:** `docs/superpowers/specs/2026-06-12-qs-analysis-ecommerce-portal-design.md` §8 (ingestion / Import Batch), §3 (CC ref = identity spine). Builds on M0 models.

**Format note:** v1 imports **CSV** (export the supplied Excel index as CSV — one click). `.xlsx` support via the already-installed `openspout` is a fast follow.

**Deferred:** Filament upload UI (M1d-2); PDF/report-asset upload; relationship-dataset import; validate-then-publish workflow + rollback (later M1d slices).

---

## File Structure

- Create `app/Models/ImportBatch.php` + migration + `database/factories/ImportBatchFactory.php` — one import run + its counts.
- Create `app/Services/FarIndexImporter.php` — upsert business logic. One method: `import(ImportBatch, iterable $rows): ImportBatch`.
- Create `app/Support/FarIndexCsv.php` — CSV → normalised rows. One static method: `read(string $path): array`.
- Create `app/Console/Commands/ImportFarIndex.php` — `import:far-index {path} {label}` wiring.
- Tests: `tests/Unit/...`/`tests/Feature/FarIndexImporterTest.php`, `tests/Feature/FarIndexCsvTest.php`, `tests/Feature/ImportFarIndexCommandTest.php`, and fixture `tests/fixtures/far-index-sample.csv`.

---

## Task 1: ImportBatch model

**Files:** Create `app/Models/ImportBatch.php`, migration `database/migrations/2026_06_13_000001_create_import_batches_table.php`, `database/factories/ImportBatchFactory.php`. Test `tests/Feature/Models/ImportBatchTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Models/ImportBatchTest.php`:
```php
<?php

use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an import batch with default status and zero counts', function () {
    $batch = ImportBatch::factory()->create(['label' => '2026 H1']);

    expect($batch->label)->toBe('2026 H1')
        ->and($batch->status)->toBe('pending')
        ->and($batch->rows)->toBe(0)
        ->and($batch->charities_created)->toBe(0);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=ImportBatchTest`
Expected: FAIL ("Class App\Models\ImportBatch not found").

- [ ] **Step 3: Implement migration, model, factory**

Migration `2026_06_13_000001_create_import_batches_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('type')->default('far_index');
            $table->string('status')->default('pending');
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('charities_created')->default(0);
            $table->unsignedInteger('charities_updated')->default(0);
            $table->unsignedInteger('issues_created')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
```

`app/Models/ImportBatch.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'label', 'type', 'status', 'rows',
        'charities_created', 'charities_updated', 'issues_created',
    ];

    protected function casts(): array
    {
        return [
            'rows' => 'integer',
            'charities_created' => 'integer',
            'charities_updated' => 'integer',
            'issues_created' => 'integer',
        ];
    }
}
```

`database/factories/ImportBatchFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => '2026 H1',
            'type' => 'far_index',
            'status' => 'pending',
        ];
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=ImportBatchTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ImportBatch.php database/migrations/2026_06_13_000001_create_import_batches_table.php database/factories/ImportBatchFactory.php tests/Feature/Models/ImportBatchTest.php
git commit -m "feat: add ImportBatch model"
```

---

## Task 2: FarIndexImporter service

**Files:** Create `app/Services/FarIndexImporter.php`. Test `tests/Feature/FarIndexImporterTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/FarIndexImporterTest.php`:
```php
<?php

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Services\FarIndexImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a charity, FAR report and current issue from a new row', function () {
    $batch = ImportBatch::factory()->create(['label' => '2026 H1']);

    (new FarIndexImporter)->import($batch, [
        ['cc_ref' => '1234567', 'name' => 'Acme Trust', 'q_score' => 55.5, 'stability' => 60.0],
    ]);

    $charity = Charity::where('cc_ref', '1234567')->first();
    expect($charity)->not->toBeNull()
        ->and($charity->name)->toBe('Acme Trust');

    $report = $charity->report;
    expect($report->type)->toBe(ReportType::FAR);

    $issue = $report->currentIssue;
    expect($issue->version_label)->toBe('2026 H1')
        ->and((float) $issue->q_score)->toBe(55.5);

    $batch->refresh();
    expect($batch->status)->toBe('completed')
        ->and($batch->rows)->toBe(1)
        ->and($batch->charities_created)->toBe(1)
        ->and($batch->issues_created)->toBe(1);
});

it('updates an existing charity and flips the current issue on a new batch', function () {
    (new FarIndexImporter)->import(
        ImportBatch::factory()->create(['label' => '2025 H2']),
        [['cc_ref' => '1234567', 'name' => 'Old Name', 'q_score' => 40.0, 'stability' => 50.0]],
    );

    $b2 = ImportBatch::factory()->create(['label' => '2026 H1']);
    (new FarIndexImporter)->import($b2, [
        ['cc_ref' => '1234567', 'name' => 'New Name', 'q_score' => 70.0, 'stability' => 80.0],
    ]);

    $charity = Charity::where('cc_ref', '1234567')->first();
    expect(Charity::count())->toBe(1)
        ->and($charity->name)->toBe('New Name')
        ->and((float) $charity->latest_q_score)->toBe(70.0);

    $report = $charity->report;
    expect($report->issues()->count())->toBe(2)
        ->and($report->currentIssue->version_label)->toBe('2026 H1');

    expect($b2->fresh()->charities_updated)->toBe(1);
});

it('skips rows with a blank cc_ref', function () {
    $batch = ImportBatch::factory()->create(['label' => '2026 H1']);

    (new FarIndexImporter)->import($batch, [
        ['cc_ref' => '', 'name' => 'No Ref', 'q_score' => 1.0, 'stability' => 2.0],
    ]);

    expect(Charity::count())->toBe(0)
        ->and($batch->fresh()->rows)->toBe(0);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=FarIndexImporterTest`
Expected: FAIL ("Class App\Services\FarIndexImporter not found").

- [ ] **Step 3: Implement**

`app/Services/FarIndexImporter.php`:
```php
<?php

namespace App\Services;

use App\Enums\ReportType;
use App\Models\Charity;
use App\Models\ImportBatch;
use App\Models\Issue;
use App\Models\Report;
use Illuminate\Support\Facades\DB;

class FarIndexImporter
{
    /**
     * Upsert charities, FAR reports and a current issue from normalised index rows.
     *
     * @param  iterable<array{cc_ref:string,name:string,q_score:float|null,stability:float|null}>  $rows
     */
    public function import(ImportBatch $batch, iterable $rows): ImportBatch
    {
        $rowCount = 0;
        $created = 0;
        $updated = 0;
        $issuesCreated = 0;

        DB::transaction(function () use ($batch, $rows, &$rowCount, &$created, &$updated, &$issuesCreated) {
            foreach ($rows as $row) {
                $ccRef = trim((string) $row['cc_ref']);
                if ($ccRef === '') {
                    continue;
                }
                $rowCount++;

                $charity = Charity::where('cc_ref', $ccRef)->first();
                if ($charity) {
                    $charity->update([
                        'name' => $row['name'],
                        'latest_q_score' => $row['q_score'],
                        'latest_stability' => $row['stability'],
                    ]);
                    $updated++;
                } else {
                    $charity = Charity::create([
                        'cc_ref' => $ccRef,
                        'name' => $row['name'],
                        'latest_q_score' => $row['q_score'],
                        'latest_stability' => $row['stability'],
                    ]);
                    $created++;
                }

                $report = Report::firstOrCreate(
                    ['type' => ReportType::FAR, 'charity_id' => $charity->id],
                    ['name' => $charity->name.' — Financial Analysis Report', 'slug' => 'far-'.$ccRef],
                );

                $existing = Issue::where('report_id', $report->id)
                    ->where('version_label', $batch->label)
                    ->first();

                if ($existing) {
                    $existing->update(['q_score' => $row['q_score'], 'stability' => $row['stability']]);
                } else {
                    Issue::where('report_id', $report->id)->update(['is_current' => false]);
                    Issue::create([
                        'report_id' => $report->id,
                        'version_label' => $batch->label,
                        'published_at' => now(),
                        'is_current' => true,
                        'q_score' => $row['q_score'],
                        'stability' => $row['stability'],
                    ]);
                    $issuesCreated++;
                }
            }
        });

        $batch->update([
            'status' => 'completed',
            'rows' => $rowCount,
            'charities_created' => $created,
            'charities_updated' => $updated,
            'issues_created' => $issuesCreated,
        ]);

        return $batch;
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=FarIndexImporterTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/FarIndexImporter.php tests/Feature/FarIndexImporterTest.php
git commit -m "feat: add FarIndexImporter upsert service"
```

---

## Task 3: FarIndexCsv reader

**Files:** Create `app/Support/FarIndexCsv.php`, fixture `tests/fixtures/far-index-sample.csv`. Test `tests/Feature/FarIndexCsvTest.php`.

- [ ] **Step 1: Create the fixture**

`tests/fixtures/far-index-sample.csv` (exact contents):
```
Charity Name,CC Ref,Q Score,Stability
Acme Trust,1234567,55.5,60
Beacon Foundation,7654321,42.1,75.2
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/FarIndexCsvTest.php`:
```php
<?php

use App\Support\FarIndexCsv;

it('reads and normalises a FAR index csv', function () {
    $rows = FarIndexCsv::read(base_path('tests/fixtures/far-index-sample.csv'));

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toMatchArray([
        'cc_ref' => '1234567',
        'name' => 'Acme Trust',
        'q_score' => 55.5,
        'stability' => 60.0,
    ]);
    expect($rows[1]['cc_ref'])->toBe('7654321')
        ->and($rows[1]['q_score'])->toBe(42.1);
});
```

- [ ] **Step 3: Run it to verify it fails**

Run: `php artisan test --filter=FarIndexCsvTest`
Expected: FAIL ("Class App\Support\FarIndexCsv not found").

- [ ] **Step 4: Implement**

`app/Support/FarIndexCsv.php`:
```php
<?php

namespace App\Support;

use League\Csv\Reader;

class FarIndexCsv
{
    /**
     * Read a FAR index CSV into normalised rows keyed cc_ref/name/q_score/stability.
     * Header matching is case- and punctuation-insensitive.
     *
     * @return array<int, array{cc_ref:string,name:string,q_score:float|null,stability:float|null}>
     */
    public static function read(string $path): array
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $map = [
            'charityname' => 'name',
            'charity' => 'name',
            'name' => 'name',
            'ccref' => 'cc_ref',
            'charitycommissionreference' => 'cc_ref',
            'charitycommissionref' => 'cc_ref',
            'regno' => 'cc_ref',
            'qscore' => 'q_score',
            'stability' => 'stability',
        ];

        $rows = [];
        foreach ($csv->getRecords() as $record) {
            $row = ['cc_ref' => '', 'name' => '', 'q_score' => null, 'stability' => null];

            foreach ($record as $header => $value) {
                $normalised = preg_replace('/[^a-z0-9]/', '', strtolower((string) $header));
                $key = $map[$normalised] ?? null;
                if ($key === null) {
                    continue;
                }

                if ($key === 'q_score' || $key === 'stability') {
                    $row[$key] = ($value === '' || $value === null) ? null : (float) $value;
                } else {
                    $row[$key] = trim((string) $value);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
```

- [ ] **Step 5: Run it to verify it passes**

Run: `php artisan test --filter=FarIndexCsvTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/FarIndexCsv.php tests/fixtures/far-index-sample.csv tests/Feature/FarIndexCsvTest.php
git commit -m "feat: add FarIndexCsv reader"
```

---

## Task 4: import:far-index Artisan command

**Files:** Create `app/Console/Commands/ImportFarIndex.php`. Test `tests/Feature/ImportFarIndexCommandTest.php`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/ImportFarIndexCommandTest.php`:
```php
<?php

use App\Models\Charity;
use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports a FAR index csv via the artisan command', function () {
    $this->artisan('import:far-index', [
        'path' => base_path('tests/fixtures/far-index-sample.csv'),
        'label' => '2026 H1',
    ])->assertSuccessful();

    expect(Charity::count())->toBe(2);

    $batch = ImportBatch::first();
    expect($batch->label)->toBe('2026 H1')
        ->and($batch->status)->toBe('completed')
        ->and($batch->charities_created)->toBe(2)
        ->and($batch->issues_created)->toBe(2);
});

it('fails cleanly when the file is missing', function () {
    $this->artisan('import:far-index', ['path' => '/no/such/file.csv', 'label' => '2026 H1'])
        ->assertFailed();

    expect(ImportBatch::count())->toBe(0);
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=ImportFarIndexCommandTest`
Expected: FAIL (command `import:far-index` not defined).

- [ ] **Step 3: Implement**

`app/Console/Commands/ImportFarIndex.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\FarIndexImporter;
use App\Support\FarIndexCsv;
use Illuminate\Console\Command;

class ImportFarIndex extends Command
{
    protected $signature = 'import:far-index {path : Path to the FAR index CSV} {label : Issue label, e.g. "2026 H1"}';

    protected $description = 'Import a FAR index CSV: upsert charities, FAR reports and the current issue.';

    public function handle(FarIndexImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = FarIndexCsv::read($path);

        $batch = ImportBatch::create([
            'label' => (string) $this->argument('label'),
            'type' => 'far_index',
            'status' => 'pending',
        ]);

        $importer->import($batch, $rows);
        $batch->refresh();

        $this->info("Imported '{$batch->label}': {$batch->rows} rows — {$batch->charities_created} charities created, {$batch->charities_updated} updated, {$batch->issues_created} issues created.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=ImportFarIndexCommandTest`
Expected: PASS (2 tests). Laravel 13 auto-registers commands in `app/Console/Commands`, so no manual registration is needed.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: all green (M1c's 80 plus the new tests).

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ImportFarIndex.php tests/Feature/ImportFarIndexCommandTest.php
git commit -m "feat: add import:far-index artisan command"
```

---

## Self-Review

**1. Spec coverage (§8 ingestion):**
- Match on **CC ref**, upsert charity → Task 2 ✓
- Ensure FAR report per charity → Task 2 (`Report::firstOrCreate` type+charity_id) ✓
- New issue per batch, flip prior `is_current` off → Task 2 ✓
- Import run tracked with counts → Task 1 (`ImportBatch`) ✓
- Read the supplied index file → Task 3 (CSV; `.xlsx` deferred) ✓
- Run the import → Task 4 (Artisan command; Filament UI deferred to M1d-2) ✓
- PDF/asset upload, relationship data, validate-then-publish, rollback → **deferred** (noted), not gaps for this slice.

**2. Placeholder scan:** No TBD/TODO; every code step is complete. Header-alias map in Task 3 is concrete. Deferred items are explicitly out of scope, not placeholders.

**3. Type consistency:** `FarIndexImporter::import(ImportBatch, iterable): ImportBatch` (Task 2) is called exactly that way by the command (Task 4). `FarIndexCsv::read(string): array` (Task 3) returns rows with keys `cc_ref/name/q_score/stability` — the exact shape `FarIndexImporter` consumes. `ImportBatch` columns (`label/status/rows/charities_created/charities_updated/issues_created`, Task 1) match every read/write in Tasks 2 and 4. Uses M0's `Charity` (cc_ref, latest_q_score, latest_stability fillable), `Report` (FAR subject invariant satisfied: type+charity_id only), and `Issue` (version_label unique per report, is_current) correctly.
