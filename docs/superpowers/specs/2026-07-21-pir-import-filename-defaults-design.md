# PIR Import Filename Defaults Design Spec

**Date:** 2026-07-21
**Status:** Approved for planning
**Extends:** the Filament PIR index import page (`app/Filament/Pages/ImportPirIndex.php`, last revised in M1.5 Task 5).

## 1. Behaviour

PIR index files arrive named with a `YYYYMM` prefix (e.g. `202607-pir-index.xlsx`). When an admin selects a file in the **Import PIR Index** dialog:

1. The page reads the first six characters of the uploaded file's **client** filename (the name on the admin's machine — not Livewire's temporary server name).
2. If they match `YYYYMM` with a valid month (`01`–`12`):
   - **Issue Label** is set to the friendly date: `202607…` → `July 2026` (PHP format `F Y`).
   - **S3 publication folder** is set to `2026-07` (format `Y-m`).
   - Both fields are **overwritten** on every (re-)upload; the admin can still edit them afterwards — they remain ordinary required text inputs, and submission behaviour is unchanged.
3. If the prefix does not parse (no leading six digits, or month outside 01–12), both fields are left untouched — blank on a first upload, exactly as today. No warning, no validation error. Any four-digit year is accepted.

## 2. Shape

- **`App\Support\ImportDefaults`** — pure helper, one static method:
  `fromFilename(string $filename): ?array` → `['label' => 'July 2026', 'folder' => '2026-07']` or `null`.
- **`app/Filament/Pages/ImportPirIndex.php`** — the `FileUpload` gains `->live()` and an `afterStateUpdated` hook: resolve the `TemporaryUploadedFile`'s `getClientOriginalName()`, call the helper, and when it returns an array `$set()` both fields. No other page changes; `runImport()` untouched.

## 3. Testing

- Helper unit tests: valid prefix (`202607-pir-index.xlsx` → July 2026 / 2026-07), January and December boundaries, bad month (`202613…` → null), too few digits / non-numeric prefix → null, bare `202607.csv` → parsed.
- The Filament page keeps its existing render test; the hook is mechanical wiring (Filament action-form interaction isn't worth driving in tests — same helper/surface split as the M2a refund service).

## 4. Out of scope

- The FAR import page (`ImportFarIndex`) gets the identical hook when FAR work resumes after client sign-off — not built now.
- No change to the artisan `import:pir-index` command (its arguments are explicit).
