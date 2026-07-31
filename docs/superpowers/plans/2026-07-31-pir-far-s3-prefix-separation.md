# PIR/FAR S3 Prefix Separation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separate PIR and FAR report PDFs on S3 into distinct `pir/` and `far/` prefixes so the two report types never share a folder.

**Architecture:** `PirIndexImporter` and `FarIndexImporter` each gain a private `S3_PREFIX` constant and a private `s3Path()` helper that builds `{prefix}/{folder}/{filename}`. Both the S3 existence check and the `Asset::path` write switch from raw `$batch->folder.'/'.$filename` concatenation to this helper. No schema, form, or console command changes.

**Tech Stack:** Laravel 13, Pest, `Illuminate\Support\Facades\Storage` (fake S3 disk in tests).

## Global Constraints

- Greenfield change — no existing S3 objects or `Asset` rows to migrate (confirmed with user).
- `ImportBatch->folder` keeps storing the bare issue date (e.g. `2026-07`) — no change to its format or to how it's populated.
- No new/visible/editable prefix field in either Filament import page or console command — the prefix is implicit in which importer class runs.
- `DownloadController` and `ImportBatch` model are untouched — they already treat `Asset::path` as an opaque full key.

---

### Task 1: Prefix PIR imports with `pir/`

**Files:**
- Modify: `app/Services/PirIndexImporter.php:84-92` (Asset write), `app/Services/PirIndexImporter.php:135-136` (exists check)
- Test: `tests/Feature/PirIndexImporterTest.php`

**Interfaces:**
- Produces: `PirIndexImporter::S3_PREFIX` (string constant, `'pir'`) and private `PirIndexImporter::s3Path(ImportBatch $batch, string $filename): string` — not consumed outside this class, but Task 2 mirrors the same shape in `FarIndexImporter` for consistency.

- [ ] **Step 1: Update failing-first test fixtures to expect the `pir/` prefix**

Edit `tests/Feature/PirIndexImporterTest.php`, replacing every faked S3 path and asserted `Asset::path` value to include the `pir/` prefix. Apply these exact replacements:

Line 17: `Storage::disk('s3')->put('2026-07/acme.pdf', 'pdf');` → `Storage::disk('s3')->put('pir/2026-07/acme.pdf', 'pdf');`

Lines 45-46:
```php
    Storage::disk('s3')->put('2025-12/acme.pdf', 'pdf');
    Storage::disk('s3')->put('2026-07/acme2.pdf', 'pdf');
```
→
```php
    Storage::disk('s3')->put('pir/2025-12/acme.pdf', 'pdf');
    Storage::disk('s3')->put('pir/2026-07/acme2.pdf', 'pdf');
```

Line 72: `Storage::disk('s3')->put('2026-07/oxfam.pdf', 'pdf');` → `Storage::disk('s3')->put('pir/2026-07/oxfam.pdf', 'pdf');`

Line 91: `Storage::disk('s3')->put('2026-07/a.pdf', 'pdf');` → `Storage::disk('s3')->put('pir/2026-07/a.pdf', 'pdf');`

Line 107: `Storage::disk('s3')->put('2026-07/oxfam.pdf', 'pdf');` → `Storage::disk('s3')->put('pir/2026-07/oxfam.pdf', 'pdf');`

Line 120: `->and($asset->path)->toBe('2026-07/oxfam.pdf')` → `->and($asset->path)->toBe('pir/2026-07/oxfam.pdf')`

Line 138: `expect($asset->path)->toBe('2026-07/oxfam.pdf');` → `expect($asset->path)->toBe('pir/2026-07/oxfam.pdf');`

(Line 128 and 145's `Storage::fake('s3')` calls stay as-is — those tests deliberately upload nothing.)

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/PirIndexImporterTest.php`
Expected: FAIL — the two tests asserting `$asset->path` now expect `pir/2026-07/oxfam.pdf` but the importer still writes bare `2026-07/oxfam.pdf`; the missing-file test may also fail since the importer looks for the unprefixed key that no longer matches what's faked.

- [ ] **Step 3: Add the `S3_PREFIX` constant and `s3Path()` helper, and use them**

In `app/Services/PirIndexImporter.php`, add the constant and helper method, then replace both concatenation sites.

Add just inside the class, before `import()`:
```php
class PirIndexImporter
{
    private const S3_PREFIX = 'pir';

    /**
     * Validate then publish a PIR index. All-or-nothing: any row error
     * fails the batch and nothing is written.
     *
     * @param  iterable<array{cc_ref:string,name:string,q_score:float|null,stability:float|null,filename:string}>  $rows
     */
    public function import(ImportBatch $batch, iterable $rows): ImportBatch
    {
```

Replace line 88:
```php
                        'path' => $batch->folder.'/'.$row['filename'],
```
with:
```php
                        'path' => $this->s3Path($batch, $row['filename']),
```

Replace lines 135-136:
```php
            } elseif (config('reports.validate_import_files') && ! Storage::disk('s3')->exists($batch->folder.'/'.$filename)) {
                $errors[] = ['row' => $line, 'error' => "File not found on S3: {$batch->folder}/{$filename}"];
```
with:
```php
            } elseif (config('reports.validate_import_files') && ! Storage::disk('s3')->exists($this->s3Path($batch, $filename))) {
                $errors[] = ['row' => $line, 'error' => "File not found on S3: {$this->s3Path($batch, $filename)}"];
```

Add the helper method after `validate()` (i.e. at the end of the class, before the closing `}`):
```php

    private function s3Path(ImportBatch $batch, string $filename): string
    {
        return self::S3_PREFIX.'/'.$batch->folder.'/'.$filename;
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/PirIndexImporterTest.php`
Expected: PASS — all 7 tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PirIndexImporter.php tests/Feature/PirIndexImporterTest.php
git commit -m "feat: prefix PIR report assets under pir/ on S3"
```

---

### Task 2: Prefix FAR imports with `far/`

**Files:**
- Modify: `app/Services/FarIndexImporter.php:75-83` (Asset write), `app/Services/FarIndexImporter.php:140-141` (exists check)
- Test: `tests/Feature/FarIndexImporterTest.php`

**Interfaces:**
- Consumes: nothing from Task 1 (parallel, independent class).
- Produces: `FarIndexImporter::S3_PREFIX` (string constant, `'far'`) and private `FarIndexImporter::s3Path(ImportBatch $batch, string $filename): string`, mirroring Task 1's shape in `PirIndexImporter`.

- [ ] **Step 1: Update failing-first test fixtures to expect the `far/` prefix**

Edit `tests/Feature/FarIndexImporterTest.php`, applying these exact replacements:

Line 29: `Storage::disk('s3')->put('2026-07/acme.pdf', 'pdf');` → `Storage::disk('s3')->put('far/2026-07/acme.pdf', 'pdf');`

Line 49: `->and($issue->assets()->where('type', AssetType::ReportPdf)->first()->path)->toBe('2026-07/acme.pdf')` → `->and($issue->assets()->where('type', AssetType::ReportPdf)->first()->path)->toBe('far/2026-07/acme.pdf')`

Lines 55-56:
```php
    Storage::disk('s3')->put('2026-07/acme.pdf', 'pdf');
    Storage::disk('s3')->put('2027-01/acme2.pdf', 'pdf');
```
→
```php
    Storage::disk('s3')->put('far/2026-07/acme.pdf', 'pdf');
    Storage::disk('s3')->put('far/2027-01/acme2.pdf', 'pdf');
```

Line 76: `Storage::disk('s3')->put('2026-07/acme.pdf', 'pdf');` → `Storage::disk('s3')->put('far/2026-07/acme.pdf', 'pdf');`

(Line 96's `Storage::fake('s3')` stays as-is — that test deliberately uploads nothing.)

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/FarIndexImporterTest.php`
Expected: FAIL — the asset-path assertion expects `far/2026-07/acme.pdf` but the importer still writes bare `2026-07/acme.pdf`; the reimport test may also fail since the faked keys no longer match what the importer looks up.

- [ ] **Step 3: Add the `S3_PREFIX` constant and `s3Path()` helper, and use them**

In `app/Services/FarIndexImporter.php`, add the constant just inside the class, before `import()`:
```php
class FarIndexImporter
{
    private const S3_PREFIX = 'far';

    /**
     * Validate then publish a FAR index. All-or-nothing: any row error
     * fails the batch and nothing is written.
     *
     * @param  iterable<array{provider_ref:string,name:string,tier:string,filename:string,related_cc_refs:array<int,string>}>  $rows
     */
    public function import(ImportBatch $batch, iterable $rows): ImportBatch
    {
```

Replace line 79:
```php
                        'path' => $batch->folder.'/'.$row['filename'],
```
with:
```php
                        'path' => $this->s3Path($batch, $row['filename']),
```

Replace lines 140-141:
```php
            } elseif (config('reports.validate_import_files') && ! Storage::disk('s3')->exists($batch->folder.'/'.$filename)) {
                $errors[] = ['row' => $line, 'error' => "File not found on S3: {$batch->folder}/{$filename}"];
```
with:
```php
            } elseif (config('reports.validate_import_files') && ! Storage::disk('s3')->exists($this->s3Path($batch, $filename))) {
                $errors[] = ['row' => $line, 'error' => "File not found on S3: {$this->s3Path($batch, $filename)}"];
```

Add the helper method at the end of the class, before the closing `}` (after `validate()`):
```php

    private function s3Path(ImportBatch $batch, string $filename): string
    {
        return self::S3_PREFIX.'/'.$batch->folder.'/'.$filename;
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/FarIndexImporterTest.php`
Expected: PASS — all 4 tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FarIndexImporter.php tests/Feature/FarIndexImporterTest.php
git commit -m "feat: prefix FAR report assets under far/ on S3"
```

---

### Task 3: Full regression pass

**Files:** none (verification only).

**Interfaces:** none — this task only runs the suite.

- [ ] **Step 1: Run the full test suite**

Run: `./vendor/bin/pest`
Expected: PASS — no other test references the old unprefixed PIR/FAR S3 paths (confirmed during planning via repo-wide search for `Storage::disk('s3')` and `->folder`, which returned only the two importers touched above).

- [ ] **Step 2: Commit (only if Step 1 required any fix)**

If the full run is green with no changes beyond Tasks 1-2, skip this step — there is nothing new to commit.
