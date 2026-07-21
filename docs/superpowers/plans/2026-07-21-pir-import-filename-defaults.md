# PIR Import Filename Defaults Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Auto-populate the Label and S3 folder fields on the Filament PIR index import dialog from the uploaded file's `YYYYMM` filename prefix — per `docs/superpowers/specs/2026-07-21-pir-import-filename-defaults-design.md`.

**Architecture:** A pure static helper `App\Support\ImportDefaults::fromFilename()` does the parsing (unit-tested); the Filament page's `FileUpload` becomes `->live()` with an `afterStateUpdated` hook that reads the temporary upload's client filename and `$set()`s both fields when the helper returns values. `runImport()` and the artisan command are untouched.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Filament v4 (`Filament\Schemas\Components\Utilities\Set` — verified present in vendor), Livewire 3 `TemporaryUploadedFile`.

## Global Constraints

- Work on branch `pir-import-filename-defaults` (create from `main`).
- Parse the **client** original filename (`getClientOriginalName()`), never Livewire's temporary server name.
- Valid prefix = first six characters `YYYYMM`, month `01`–`12`, any four-digit year. Label format `F Y` (e.g. `July 2026`), folder format `Y-m` (e.g. `2026-07`).
- On every (re-)upload with a valid prefix, **overwrite** both fields; on an invalid prefix, touch nothing (no warning, no error). Fields stay required, manually editable; submission behaviour unchanged.
- PIR page only — do NOT touch `ImportFarIndex` (FARs await client sign-off).
- Full suite (`php artisan test`, ~2–3 min on the `/mnt/c` mount) green before the commit.

---

### Task 1: ImportDefaults helper + live FileUpload hook

**Files:**
- Create: `app/Support/ImportDefaults.php`
- Modify: `app/Filament/Pages/ImportPirIndex.php` (the `FileUpload::make('file')` builder chain only)
- Test: `tests/Unit/ImportDefaultsTest.php`

**Interfaces:**
- Consumes: `Livewire\Features\SupportFileUploads\TemporaryUploadedFile` (already imported in the page), `Filament\Schemas\Components\Utilities\Set` (Filament v4's form setter — path verified: `vendor/filament/schemas/src/Components/Utilities/Set.php`).
- Produces: `App\Support\ImportDefaults::fromFilename(string $filename): ?array` — `['label' => 'July 2026', 'folder' => '2026-07']` or `null`. (The FAR page will reuse this helper in a later milestone.)

- [ ] **Step 1: Write the failing helper tests**

`tests/Unit/ImportDefaultsTest.php`:

```php
<?php

use App\Support\ImportDefaults;

it('derives label and folder from a YYYYMM prefix', function () {
    expect(ImportDefaults::fromFilename('202607-pir-index.xlsx'))
        ->toBe(['label' => 'July 2026', 'folder' => '2026-07']);
});

it('parses a bare date filename', function () {
    expect(ImportDefaults::fromFilename('202607.csv'))
        ->toBe(['label' => 'July 2026', 'folder' => '2026-07']);
});

it('handles january and december boundaries', function () {
    expect(ImportDefaults::fromFilename('202601_index.csv'))
        ->toBe(['label' => 'January 2026', 'folder' => '2026-01'])
        ->and(ImportDefaults::fromFilename('202512 index.xlsx'))
        ->toBe(['label' => 'December 2025', 'folder' => '2025-12']);
});

it('rejects invalid months', function () {
    expect(ImportDefaults::fromFilename('202613-pir-index.xlsx'))->toBeNull()
        ->and(ImportDefaults::fromFilename('202600-pir-index.xlsx'))->toBeNull();
});

it('rejects filenames without a leading six-digit prefix', function () {
    expect(ImportDefaults::fromFilename('pir-index-final.xlsx'))->toBeNull()
        ->and(ImportDefaults::fromFilename('2026-07-index.csv'))->toBeNull()
        ->and(ImportDefaults::fromFilename('20260.csv'))->toBeNull()
        ->and(ImportDefaults::fromFilename(''))->toBeNull();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test tests/Unit/ImportDefaultsTest.php`
Expected: FAIL — `App\Support\ImportDefaults` not found.

- [ ] **Step 3: Implement the helper**

`app/Support/ImportDefaults.php`:

```php
<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ImportDefaults
{
    /**
     * Derive Label + S3-folder defaults from a YYYYMM-prefixed index
     * filename (e.g. "202607-pir-index.xlsx" → July 2026 / 2026-07).
     * Null when the first six characters are not a valid YYYYMM.
     *
     * @return array{label: string, folder: string}|null
     */
    public static function fromFilename(string $filename): ?array
    {
        if (! preg_match('/^(\d{4})(\d{2})/', $filename, $matches)) {
            return null;
        }

        $month = (int) $matches[2];

        if ($month < 1 || $month > 12) {
            return null;
        }

        $date = CarbonImmutable::createFromDate((int) $matches[1], $month, 1);

        return [
            'label' => $date->format('F Y'),
            'folder' => $date->format('Y-m'),
        ];
    }
}
```

- [ ] **Step 4: Run the helper tests to verify they pass**

Run: `php artisan test tests/Unit/ImportDefaultsTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Wire the live hook into the Filament page**

`app/Filament/Pages/ImportPirIndex.php` — add two imports:

```php
use App\Support\ImportDefaults;
use Filament\Schemas\Components\Utilities\Set;
```

and replace the `FileUpload::make('file')` builder chain (currently ending `->storeFiles(false)->required(),`) with:

```php
                    FileUpload::make('file')
                        ->label('PIR Index File (.csv or .xlsx)')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->storeFiles(false)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $file = is_array($state) ? reset($state) : $state;

                            if (! $file instanceof TemporaryUploadedFile) {
                                return;
                            }

                            $defaults = ImportDefaults::fromFilename($file->getClientOriginalName());

                            if ($defaults !== null) {
                                $set('label', $defaults['label']);
                                $set('folder', $defaults['folder']);
                            }
                        })
                        ->required(),
```

Leave the `label`/`folder` `TextInput`s, the action closure, and `runImport()` exactly as they are.

- [ ] **Step 6: Run the page render test, then the full suite**

Run: `php artisan test tests/Feature/Admin/ImportPirIndexPageTest.php && php artisan test`
Expected: PASS — render test still green (the hook is inert until a file is uploaded), full suite green.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: auto-populate PIR import label and folder from YYYYMM filename prefix"
```

---

## Self-Review

**1. Spec coverage:** §1 parse-client-filename/overwrite/silent-null → Steps 3+5 with constraints copied verbatim ✓; §2 helper + `afterStateUpdated` shape → Steps 3/5 ✓; §3 helper unit tests incl. boundaries + render-test-only page coverage → Steps 1/6 ✓; §4 out-of-scope (FAR page, artisan command) → Global Constraints ✓.

**2. Placeholder scan:** none — full code in every step.

**3. Type consistency:** `fromFilename(string): ?array` matches its call site; `Set` import path verified against vendor; `TemporaryUploadedFile` already imported in the page (existing `use` statement).
