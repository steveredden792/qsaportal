# PIR/FAR S3 Prefix Separation Design Spec

**Date:** 2026-07-31
**Status:** Approved for planning
**Extends:** `App\Services\PirIndexImporter` and `App\Services\FarIndexImporter`.

## 1. Behaviour

PIR and FAR report PDFs currently share one flat S3 layout: `{bucket}/{issue_date}/{filename}` (e.g. `2026-07/oxfam.pdf`), where `issue_date` comes from `ImportBatch->folder`. Because both report types can land in the same `issue_date` folder, their files are not namespaced apart on S3.

Going forward, each importer writes to a type-specific prefix under the same bucket:

- PIR: `{bucket}/pir/{issue_date}/{filename}`
- FAR: `{bucket}/far/{issue_date}/{filename}`

This applies to both the existence check (`Storage::disk('s3')->exists(...)`, gated by `config('reports.validate_import_files')`) and the path written to `Asset::path` on publish. `DownloadController` needs no change — it already streams whatever full path is stored on the `Asset` row.

Greenfield change: no real files have been imported yet, so there is no existing S3 data to move and no backfill needed for previously published `Asset` rows.

## 2. Shape

- **`App\Services\PirIndexImporter`** — add `private const S3_PREFIX = 'pir';` and a private helper `s3Path(ImportBatch $batch, string $filename): string` returning `self::S3_PREFIX.'/'.$batch->folder.'/'.$filename`. Replace the two existing `$batch->folder.'/'.$filename` concatenations (the exists-check and the `Asset::path` write) with calls to this helper.
- **`App\Services\FarIndexImporter`** — identical change, `S3_PREFIX = 'far'`.
- No change to `ImportBatch` (the `folder` column keeps storing the bare issue date, e.g. `2026-07`), no change to the `type` column's existing values (`pir_index`/`far_index`), no change to either Filament import page, console command, or `DownloadController`.
- Rationale for keeping the prefix local to each importer class (over deriving it from `ImportBatch->type` in a shared model method): each importer class already only ever handles its own report type and doesn't otherwise inspect `batch->type` — a hardcoded constant matches that existing trust model without adding a public method for two call sites.

## 3. Testing

- `tests/Feature/PirIndexImporterTest.php` — update every faked S3 path (`Storage::disk('s3')->put(...)`) and every asserted `Asset::path` value to include the `pir/` prefix.
- `tests/Feature/FarIndexImporterTest.php` — same, with the `far/` prefix.
- No new test cases needed beyond updating existing fixtures/assertions — the prefixing logic is a one-line constant, not new branching behaviour.

## 4. Out of scope

- No S3 data migration (greenfield, confirmed with user).
- No visible/editable prefix field in either import form — the prefix is implicit in which import page/command is used.
- No change to the `YYYY-MM` folder-prefix filename-parsing work (`App\Support\ImportDefaults`) done earlier — that still derives the bare `2026-07` folder value; it's simply consumed by the new prefixed path.
