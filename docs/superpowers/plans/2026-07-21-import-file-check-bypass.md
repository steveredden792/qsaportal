# Import File-Check Bypass Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let index imports publish locally without an S3 bucket by making the file-existence check switchable (`IMPORT_VALIDATE_FILES`, default true), plus a ready-to-upload sample PIR index — per `docs/superpowers/specs/2026-07-21-import-file-check-bypass-design.md`.

**Architecture:** One config key `reports.validate_import_files` guards only the `Storage::disk('s3')->exists()` call inside both importers' `validate()` methods. Every other validation is untouched. A 20-row sample CSV lands in git-ignored `storage/app/dev/`.

**Tech Stack:** Laravel 13, PHP 8.4, Pest (`Storage::fake('s3')` in tests).

## Global Constraints

- Work on branch `import-file-check-bypass` (create from `main`).
- Default MUST be `true` — production/CI/tests keep today's behaviour; only a local `.env` opt-out changes anything.
- When `false`, skip ONLY the S3 existence check, in BOTH `PirIndexImporter` and `FarIndexImporter`. Blank filenames still fail the row; all ref/name/tier/related-CC validation unchanged.
- `.env` is git-ignored — editing it is a local convenience step, never committed.
- Full suite (`php artisan test`, ~2–3 min) green before the commit.

---

### Task 1: Config flag, importer guards, sample CSV

**Files:**
- Modify: `config/reports.php`, `app/Services/PirIndexImporter.php` (validate() filename branch), `app/Services/FarIndexImporter.php` (validate() filename branch), `.env.example`, `.env` (local only)
- Create: `storage/app/dev/202607-pir-index.csv`
- Test: `tests/Feature/PirIndexImporterTest.php`, `tests/Feature/FarIndexImporterTest.php` (add cases)

**Interfaces:**
- Consumes: existing `PirIndexImporter::validate()` / `FarIndexImporter::validate()` private methods; `ImportBatch`; Pest tests' existing helpers (`farRow()` in FarIndexImporterTest).
- Produces: `config('reports.validate_import_files')` (bool, env `IMPORT_VALIDATE_FILES`, default true). Nothing else changes shape.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PirIndexImporterTest.php`:

```php
it('publishes without checking s3 when file validation is disabled', function () {
    config(['reports.validate_import_files' => false]);
    Storage::fake('s3'); // deliberately empty — no files uploaded

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'pir_index', 'folder' => '2026-07']);

    $result = app(PirIndexImporter::class)->import($batch, [
        ['cc_ref' => '1111111', 'name' => 'Oxfam', 'q_score' => 60.0, 'stability' => 50.0, 'filename' => 'oxfam.pdf'],
    ]);

    expect($result->status)->toBe('published');

    $asset = Issue::where('is_current', true)->firstOrFail()
        ->assets()->where('type', AssetType::ReportPdf)->firstOrFail();
    expect($asset->path)->toBe('2026-07/oxfam.pdf');
});

it('still fails blank filenames when file validation is disabled', function () {
    config(['reports.validate_import_files' => false]);
    Storage::fake('s3');

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'pir_index', 'folder' => '2026-07']);

    $result = app(PirIndexImporter::class)->import($batch, [
        ['cc_ref' => '1111111', 'name' => 'Oxfam', 'q_score' => null, 'stability' => null, 'filename' => ''],
    ]);

    expect($result->status)->toBe('failed')
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]['error'])->toBe('Missing filename');
});
```

Append to `tests/Feature/FarIndexImporterTest.php`:

```php
it('publishes without checking s3 when file validation is disabled', function () {
    config(['reports.validate_import_files' => false]);
    Storage::fake('s3'); // deliberately empty

    $batch = ImportBatch::create(['label' => '2026 H2', 'type' => 'far_index', 'folder' => '2026-07']);

    $result = app(FarIndexImporter::class)->import($batch, [farRow()]);

    expect($result->status)->toBe('published');
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Feature/PirIndexImporterTest.php tests/Feature/FarIndexImporterTest.php`
Expected: FAIL — the three new tests get `failed` batches ("File not found on S3: …") because the flag doesn't exist yet (`config()` returns the test-set value, but the importers don't read it).

- [ ] **Step 3: Add the config key and guard both importers**

`config/reports.php` — full new content:

```php
<?php

// Allowed FAR tiers. Placeholder labels pending client confirmation
// (spec 2026-07-17 §2: "validated on import against an admin-configurable
// allowed list" — admin UI arrives with M4; config is the M1.5 source).
return [
    'far_tiers' => ['standard', 'enhanced', 'premium'],

    // When false (local dev without an S3 bucket), index imports skip ONLY
    // the S3 file-existence check; all other row validation still runs.
    // See docs/superpowers/specs/2026-07-21-import-file-check-bypass-design.md.
    'validate_import_files' => env('IMPORT_VALIDATE_FILES', true),
];
```

`app/Services/PirIndexImporter.php` — in `validate()`, replace the filename branch:

```php
            if ($filename === '') {
                $errors[] = ['row' => $line, 'error' => 'Missing filename'];
            } elseif (config('reports.validate_import_files') && ! Storage::disk('s3')->exists($batch->folder.'/'.$filename)) {
                $errors[] = ['row' => $line, 'error' => "File not found on S3: {$batch->folder}/{$filename}"];
            }
```

`app/Services/FarIndexImporter.php` — in `validate()`, replace the identical filename branch with the same code as above.

- [ ] **Step 4: Run the importer tests to verify they pass**

Run: `php artisan test tests/Feature/PirIndexImporterTest.php tests/Feature/FarIndexImporterTest.php`
Expected: PASS — new tests green, existing S3-validation tests still green (default true).

- [ ] **Step 5: Env entries and the sample CSV**

Append to `.env.example`:

```
# Set to false in local dev (no S3 bucket) to let index imports publish
# without the file-existence check. Leave true everywhere else.
IMPORT_VALIDATE_FILES=true
```

Append to `.env` (local only, git-ignored):

```
IMPORT_VALIDATE_FILES=false
```

Create `storage/app/dev/202607-pir-index.csv` (the `202607` prefix drives the import dialog's auto-fill):

```csv
Charity Name,CC Ref,Q Score,Stability,Filename
Harborlight Trust,1000001,72.4,68.0,harborlight-trust-1000001.pdf
Willowbrook Foundation,1000002,58.1,61.5,willowbrook-foundation-1000002.pdf
Cedar Hospice Care,1000003,81.9,77.2,cedar-hospice-care-1000003.pdf
Northgate Youth Action,1000004,44.7,52.3,northgate-youth-action-1000004.pdf
Silverdale Age Support,1000005,66.0,70.8,silverdale-age-support-1000005.pdf
Brightwater Learning,1000006,37.2,41.6,brightwater-learning-1000006.pdf
Foxglove Animal Welfare,1000007,74.5,63.9,foxglove-animal-welfare-1000007.pdf
Kingsmead Community Hub,1000008,52.8,49.4,kingsmead-community-hub-1000008.pdf
Opal Mental Health,1000009,88.3,84.1,opal-mental-health-1000009.pdf
Thornbury Food Relief,1000010,61.7,58.2,thornbury-food-relief-1000010.pdf
Lantern Housing Aid,1000011,29.5,35.7,lantern-housing-aid-1000011.pdf
Maple Grove Carers,1000012,69.2,72.6,maple-grove-carers-1000012.pdf
Redwing Arts Outreach,1000013,47.9,44.8,redwing-arts-outreach-1000013.pdf
Stonebridge Veterans,1000014,76.8,80.3,stonebridge-veterans-1000014.pdf
Hazelmere Childcare,1000015,55.4,60.1,hazelmere-childcare-1000015.pdf
Copperfield Literacy,1000016,63.6,57.5,copperfield-literacy-1000016.pdf
Windrush Refugee Welcome,1000017,84.0,79.7,windrush-refugee-welcome-1000017.pdf
Bracken Hill Environment,1000018,41.3,46.9,bracken-hill-environment-1000018.pdf
Seafarers Haven,1000019,70.1,66.4,seafarers-haven-1000019.pdf
Amberley Disability Access,1000020,59.8,64.3,amberley-disability-access-1000020.pdf
```

(If `storage/app/dev/` is not covered by an existing storage `.gitignore`, do NOT add the CSV to git — it is local sample data; `git status` should stay clean of it. Laravel's default `storage/app/.gitignore` ignores everything except `public/`, so normally no action is needed.)

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS (151 tests — 148 existing + 3 new).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: make import S3 file-existence check switchable for keyless local dev"
```

(Verify `git status` shows neither `.env` nor `storage/app/dev/` staged — both are git-ignored.)

---

## Self-Review

**1. Spec coverage:** §1 flag default-true, exists()-only skip in both importers, blank-filename still fails, env docs → Steps 3/5 with tests in Step 1 ✓; §2 sample CSV at the exact path with 20 rows, header and `{slug}-{ccref}.pdf` filenames, 7-digit CC refs, scores 29–89 ✓; §3 the three tests specified ✓; §4 no download changes, no flag-removal task ✓.

**2. Placeholder scan:** none — full code/content in every step.

**3. Type consistency:** `config('reports.validate_import_files')` read in both importers matches the key added in Step 3; test imports (`Issue`, `AssetType`) already present in `PirIndexImporterTest.php` from M1.5; `farRow()` helper exists in `FarIndexImporterTest.php`.
