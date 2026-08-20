# PIR Q Grade / Stability Grade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add supplementary `q_grade` (letter) and `stability_grade` (numeric) fields to the PIR import pipeline, schema, and catalogue display, alongside the existing `q_score`/`stability` numeric fields.

**Architecture:** Two new nullable columns on `issues` (versioned) and two more on `charities` (denormalized "latest" cache), mirroring the existing `q_score`/`stability` columns exactly. The import pipeline (`PirIndexFile` → `PirIndexImporter`) reads and writes them the same way it already reads/writes the scores. The PIR catalogue gains two display-only table columns plus a static legend note.

**Tech Stack:** Laravel 13, Eloquent, Pest.

## Global Constraints

- `q_grade` is a nullable `string` with no format/enum validation — the letter-grade vocabulary (`a`, `aa`, `bbb`, …) is open-ended.
- `stability_grade` is a nullable `decimal(3,1)` — numeric, roughly 1–10, may carry one decimal place.
- Both fields are optional on import: a blank or absent column must not fail validation and must resolve to `null`, never an empty string.
- New file header aliases: `"Q Grade"` → `q_grade`, `"Stability Grade"` → `stability_grade` (matched via the existing lowercase/punctuation-stripped header normalization — no code changes needed to the normalizer itself).
- No sorting or filtering by grade in this iteration — display only.
- PIR only. No changes to the FAR import pipeline, FAR schema, or FAR pages.
- The catalogue legend explains the mechanism only; the plan supplies real placeholder copy (a complete sentence, not a TODO marker) since the exact grade definitions are the user's to supply later.

---

### Task 1: Schema — grade columns on `issues` and `charities`

**Files:**
- Create: `database/migrations/2026_08_20_120000_add_grade_columns_to_pir_tables.php`
- Modify: `app/Models/Charity.php`, `app/Models/Issue.php`
- Test: `tests/Feature/PirGradeColumnsTest.php`

**Interfaces:**
- Produces: `Charity::latest_q_grade` (string|null), `Charity::latest_stability_grade` (decimal string|null via `decimal:1` cast), `Issue::q_grade` (string|null), `Issue::stability_grade` (decimal string|null via `decimal:1` cast) — all mass-assignable. Later tasks (2-4) rely on these being fillable and persisted.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PirGradeColumnsTest.php`:

```php
<?php

use App\Models\Charity;
use App\Models\Issue;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists q_grade and stability_grade on an issue', function () {
    $report = Report::factory()->pir()->create();

    $issue = Issue::create([
        'report_id' => $report->id,
        'version_label' => '2026 H1',
        'published_at' => now(),
        'is_current' => true,
        'q_score' => 55.5,
        'stability' => 60.0,
        'q_grade' => 'bbb',
        'stability_grade' => 7.5,
    ]);

    $issue->refresh();
    expect($issue->q_grade)->toBe('bbb')
        ->and((float) $issue->stability_grade)->toBe(7.5);
});

it('persists latest_q_grade and latest_stability_grade on a charity', function () {
    $charity = Charity::create([
        'cc_ref' => '1234567',
        'name' => 'Acme Trust',
        'latest_q_score' => 55.5,
        'latest_stability' => 60.0,
        'latest_q_grade' => 'bbb',
        'latest_stability_grade' => 7.5,
    ]);

    $charity->refresh();
    expect($charity->latest_q_grade)->toBe('bbb')
        ->and((float) $charity->latest_stability_grade)->toBe(7.5);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/PirGradeColumnsTest.php`
Expected: FAIL — `q_grade`/`stability_grade` and `latest_q_grade`/`latest_stability_grade` are neither columns nor fillable yet, so both `create()` calls raise a mass-assignment or unknown-column error.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_20_120000_add_grade_columns_to_pir_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->string('q_grade')->nullable()->after('q_score');
            $table->decimal('stability_grade', 3, 1)->nullable()->after('stability');
        });

        Schema::table('charities', function (Blueprint $table) {
            $table->string('latest_q_grade')->nullable()->after('latest_q_score');
            $table->decimal('latest_stability_grade', 3, 1)->nullable()->after('latest_stability');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['q_grade', 'stability_grade']);
        });

        Schema::table('charities', function (Blueprint $table) {
            $table->dropColumn(['latest_q_grade', 'latest_stability_grade']);
        });
    }
};
```

- [ ] **Step 4: Update the `Charity` model**

In `app/Models/Charity.php`, replace the `$fillable` line and `casts()` method:

```php
    protected $fillable = ['cc_ref', 'name', 'latest_q_score', 'latest_stability', 'latest_q_grade', 'latest_stability_grade'];

    protected function casts(): array
    {
        return [
            'latest_q_score' => 'decimal:2',
            'latest_stability' => 'decimal:2',
            'latest_stability_grade' => 'decimal:1',
        ];
    }
```

- [ ] **Step 5: Update the `Issue` model**

In `app/Models/Issue.php`, replace the `$fillable` line and `casts()` method:

```php
    protected $fillable = ['report_id', 'version_label', 'published_at', 'is_current', 'q_score', 'stability', 'q_grade', 'stability_grade'];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'is_current' => 'boolean',
            'q_score' => 'decimal:2',
            'stability' => 'decimal:2',
            'stability_grade' => 'decimal:1',
        ];
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/PirGradeColumnsTest.php`
Expected: PASS — 2/2.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_20_120000_add_grade_columns_to_pir_tables.php app/Models/Charity.php app/Models/Issue.php tests/Feature/PirGradeColumnsTest.php
git commit -m "feat: add q_grade and stability_grade columns to issues and charities"
```

---

### Task 2: `PirIndexFile` — read Q Grade / Stability Grade columns

**Files:**
- Modify: `app/Support/PirIndexFile.php`
- Test: `tests/Feature/PirIndexFileTest.php`

**Interfaces:**
- Consumes: nothing from Task 1 (this task only touches file parsing, not persistence).
- Produces: `PirIndexFile::read()` rows now always include `q_grade` (string|null) and `stability_grade` (float|null) keys — Task 3 relies on these keys always being present (even when null).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PirIndexFileTest.php`:

```php
it('reads the q grade and stability grade columns', function () {
    $csv = tempnam(sys_get_temp_dir(), 'pir').'.csv';
    file_put_contents($csv, "CC Ref,Charity Name,Q Score,Stability,Q Grade,Stability Grade,Filename\n1111111,Oxfam,61.5,55.0,bbb,7.5,oxfam-1111111.pdf\n");

    $rows = PirIndexFile::read($csv);

    @unlink($csv);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['q_grade'])->toBe('bbb')
        ->and($rows[0]['stability_grade'])->toBe(7.5);
});

it('always includes q_grade and stability_grade keys, defaulting to null when the columns are absent', function () {
    $rows = PirIndexFile::read(base_path('tests/fixtures/pir-index-sample.csv'));

    expect(array_key_exists('q_grade', $rows[0]))->toBeTrue()
        ->and($rows[0]['q_grade'])->toBeNull()
        ->and(array_key_exists('stability_grade', $rows[0]))->toBeTrue()
        ->and($rows[0]['stability_grade'])->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/PirIndexFileTest.php`
Expected: FAIL — 2 new failures. The first because `q_grade`/`stability_grade` aren't mapped yet (the columns are silently skipped), the second because `array_key_exists('q_grade', $rows[0])` is `false` — the default row array doesn't declare that key yet.

- [ ] **Step 3: Implement**

In `app/Support/PirIndexFile.php`, replace the `$map` array (adding two entries), the default `$row` array, and the value-assignment logic:

```php
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
            'qgrade' => 'q_grade',
            'stabilitygrade' => 'stability_grade',
            'filename' => 'filename',
            'file' => 'filename',
            'pdffilename' => 'filename',
        ];

        $rows = [];
        foreach (IndexRows::read($path) as $record) {
            $row = ['cc_ref' => '', 'name' => '', 'q_score' => null, 'stability' => null, 'q_grade' => null, 'stability_grade' => null, 'filename' => ''];

            foreach ($record as $header => $value) {
                $key = $map[$header] ?? null;
                if ($key === null) {
                    continue;
                }

                if ($key === 'q_score' || $key === 'stability' || $key === 'stability_grade') {
                    $row[$key] = ($value === '' || $value === null) ? null : (float) $value;
                } elseif ($key === 'q_grade') {
                    $trimmed = trim((string) $value);
                    $row[$key] = $trimmed === '' ? null : $trimmed;
                } else {
                    $row[$key] = trim((string) $value);
                }
            }

            $rows[] = $row;
        }
```

Also update the class docblock's `@return` type to include the two new keys:

```php
    /**
     * Read a PIR index (CSV or XLSX) into normalised rows.
     *
     * @return array<int, array{cc_ref:string,name:string,q_score:float|null,stability:float|null,q_grade:string|null,stability_grade:float|null,filename:string}>
     */
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/PirIndexFileTest.php`
Expected: PASS — 6/6.

- [ ] **Step 5: Commit**

```bash
git add app/Support/PirIndexFile.php tests/Feature/PirIndexFileTest.php
git commit -m "feat: read Q Grade and Stability Grade columns in PirIndexFile"
```

---

### Task 3: `PirIndexImporter` — persist grades on `Issue` and `Charity`

**Files:**
- Modify: `app/Services/PirIndexImporter.php`
- Test: `tests/Feature/PirIndexImporterTest.php`

**Interfaces:**
- Consumes: Task 1's `Charity::latest_q_grade`/`latest_stability_grade` and `Issue::q_grade`/`stability_grade` fillable columns; Task 2's row shape (`q_grade`/`stability_grade` keys, possibly absent on rows built by hand in older tests — access defensively with `??`).
- Produces: nothing new consumed by later tasks; Task 4 reads `Charity::latest_q_grade`/`latest_stability_grade` directly, already available from Task 1.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PirIndexImporterTest.php`:

```php
it('persists q_grade and stability_grade on the charity and issue', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('pir/2026-07/acme.pdf', 'pdf');

    $batch = ImportBatch::factory()->create(['label' => '2026 H1', 'folder' => '2026-07']);

    (new PirIndexImporter)->import($batch, [
        ['cc_ref' => '1234567', 'name' => 'Acme Trust', 'q_score' => 55.5, 'stability' => 60.0, 'q_grade' => 'bbb', 'stability_grade' => 7.5, 'filename' => 'acme.pdf'],
    ]);

    $charity = Charity::where('cc_ref', '1234567')->first();
    expect($charity->latest_q_grade)->toBe('bbb')
        ->and((float) $charity->latest_stability_grade)->toBe(7.5);

    $issue = $charity->report->currentIssue;
    expect($issue->q_grade)->toBe('bbb')
        ->and((float) $issue->stability_grade)->toBe(7.5);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/PirIndexImporterTest.php`
Expected: FAIL on the new test only (the existing tests in this file still pass) — `$charity->latest_q_grade` and `$issue->q_grade` are both `null` because `import()` doesn't write them yet.

- [ ] **Step 3: Implement**

In `app/Services/PirIndexImporter.php`, replace the `Charity::where(...)->first()` / update-or-create block:

```php
                $charity = Charity::where('cc_ref', $ccRef)->first();
                if ($charity) {
                    $charity->update([
                        'name' => $row['name'],
                        'latest_q_score' => $row['q_score'],
                        'latest_stability' => $row['stability'],
                        'latest_q_grade' => $row['q_grade'] ?? null,
                        'latest_stability_grade' => $row['stability_grade'] ?? null,
                    ]);
                    $updated++;
                } else {
                    $charity = Charity::create([
                        'cc_ref' => $ccRef,
                        'name' => $row['name'],
                        'latest_q_score' => $row['q_score'],
                        'latest_stability' => $row['stability'],
                        'latest_q_grade' => $row['q_grade'] ?? null,
                        'latest_stability_grade' => $row['stability_grade'] ?? null,
                    ]);
                    $created++;
                }
```

And replace the issue update-or-create block:

```php
                if ($issue) {
                    $issue->update([
                        'q_score' => $row['q_score'],
                        'stability' => $row['stability'],
                        'q_grade' => $row['q_grade'] ?? null,
                        'stability_grade' => $row['stability_grade'] ?? null,
                    ]);
                } else {
                    Issue::where('report_id', $report->id)->update(['is_current' => false]);
                    $issue = Issue::create([
                        'report_id' => $report->id,
                        'version_label' => $batch->label,
                        'published_at' => now(),
                        'is_current' => true,
                        'q_score' => $row['q_score'],
                        'stability' => $row['stability'],
                        'q_grade' => $row['q_grade'] ?? null,
                        'stability_grade' => $row['stability_grade'] ?? null,
                    ]);
                    $issuesCreated++;
                }
```

Also update the class docblock's `@param` type on `import()`:

```php
    /**
     * Validate then publish a PIR index. All-or-nothing: any row error
     * fails the batch and nothing is written.
     *
     * @param  iterable<array{cc_ref:string,name:string,q_score:float|null,stability:float|null,q_grade?:string|null,stability_grade?:float|null,filename:string}>  $rows
     */
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/PirIndexImporterTest.php`
Expected: PASS — 8/8 (the new test plus all 7 pre-existing tests, which still pass because `$row['q_grade'] ?? null` tolerates their row arrays not having the key at all).

- [ ] **Step 5: Commit**

```bash
git add app/Services/PirIndexImporter.php tests/Feature/PirIndexImporterTest.php
git commit -m "feat: persist q_grade and stability_grade during PIR import"
```

---

### Task 4: PIR catalogue — display Q Grade / Stability Grade columns

**Files:**
- Modify: `resources/views/livewire/pir-catalogue.blade.php`
- Test: `tests/Feature/PirCatalogueTest.php`

**Interfaces:**
- Consumes: `Charity::latest_q_grade`/`latest_stability_grade` (Task 1's columns; the existing `pirCharity()` test helper already mass-assigns whatever attributes are passed to it, so no helper changes are needed).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PirCatalogueTest.php`:

```php
it('shows Q Grade and Stability Grade columns', function () {
    pirCharity([
        'name' => 'Oxfam',
        'cc_ref' => '1111111',
        'latest_q_score' => 60,
        'latest_stability' => 50,
        'latest_q_grade' => 'bbb',
        'latest_stability_grade' => 7.5,
    ]);

    Livewire::test(PirCatalogue::class)
        ->assertSee('Q Grade')
        ->assertSee('Stability Grade')
        ->assertSee('bbb')
        ->assertSee('7.5');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/PirCatalogueTest.php`
Expected: FAIL — the blade view doesn't render "Q Grade", "Stability Grade", "bbb", or "7.5" yet.

- [ ] **Step 3: Implement**

In `resources/views/livewire/pir-catalogue.blade.php`, replace the table header row:

```blade
                <tr>
                    <th class="cursor-pointer px-4 py-3 font-semibold" wire:click="sortBy('name')">Charity</th>
                    <th class="px-4 py-3 font-semibold">CC ref</th>
                    <th class="cursor-pointer px-4 py-3 font-semibold" wire:click="sortBy('latest_q_score')">Q score</th>
                    <th class="px-4 py-3 font-semibold">Q Grade</th>
                    <th class="cursor-pointer px-4 py-3 font-semibold" wire:click="sortBy('latest_stability')">Stability</th>
                    <th class="px-4 py-3 font-semibold">Stability Grade</th>
                    <th class="px-4 py-3 font-semibold"></th>
                </tr>
```

Replace the data row:

```blade
                    <tr wire:key="charity-{{ $charity->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $charity->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $charity->cc_ref }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $charity->latest_q_score }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $charity->latest_q_grade }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $charity->latest_stability }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $charity->latest_stability_grade }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('reports.show', $charity->report->slug) }}"
                                   class="font-medium text-brand transition hover:text-brand-light">View report</a>
                                <livewire:add-to-basket :report="$charity->report" :key="'atb-'.$charity->id" />
                            </div>
                        </td>
                    </tr>
```

Replace the empty-state row's `colspan` (5 → 7, for the two new columns):

```blade
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No reports match your filters.</td></tr>
```

Add a legend note directly below the table (after the closing `</div>` of the `overflow-x-auto` table wrapper, before the pagination `<div>`):

```blade
    <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
        <span class="font-semibold text-slate-700">Grades:</span>
        Q Grade and Stability Grade are supplementary bands supplied alongside the numeric Q score and stability figures.
    </div>

    <div class="mt-4">{{ $charities->links() }}</div>
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/PirCatalogueTest.php`
Expected: PASS — 9/9.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/pir-catalogue.blade.php tests/Feature/PirCatalogueTest.php
git commit -m "feat: show Q Grade and Stability Grade on the PIR catalogue"
```

---

### Task 5: Full regression pass

**Files:** none (verification only).

**Interfaces:** none — this task only runs the suite.

- [ ] **Step 1: Run the full test suite**

Run: `./vendor/bin/pest`
Expected: PASS — this is purely additive (new nullable columns, new optional import keys, new display columns); nothing existing is renamed or removed. Confirmed via `grep -rn "latest_q_score\|latest_stability\b" app database tests --include="*.php"` during planning: every hit outside Tasks 1-4's own files (`database/seeders/CatalogueDemoSeeder.php`, `tests/Feature/CharityIndexesTest.php`, `tests/Feature/ReportDetailTest.php`, `app/Livewire/PirCatalogue.php`'s query/sort logic) reads the pre-existing `latest_q_score`/`latest_stability` columns unchanged by this plan, so none of them are at risk.

- [ ] **Step 2: Commit (only if Step 1 required any fix)**

If the full run is green with no changes beyond Tasks 1-4, skip this step — there is nothing new to commit.
