# Import File-Check Bypass Design Spec

**Date:** 2026-07-21
**Status:** Approved for planning
**Purpose:** Let index imports publish locally (no S3 bucket exists yet) so the imported PIR catalogue can be tested end-to-end — search, detail pages, add-to-basket, checkout-to-fake-gateway.

## 1. Behaviour

- New config key **`reports.validate_import_files`**, read from env **`IMPORT_VALIDATE_FILES`**, **default `true`**.
- When `true` (production, CI, tests): behaviour is exactly today's — every row's `{folder}/{filename}` must exist on the `s3` disk or the batch fails.
- When `false` (local dev): **only** the `Storage::disk('s3')->exists()` check is skipped, in **both** `PirIndexImporter` and `FarIndexImporter` (kept symmetric). All other validation is unchanged — missing/duplicate CC or provider refs, missing charity/provider name, **missing filename column still fails the row**, FAR tier allow-list, unknown related CC refs. Published batches still write `ReportPdf` asset rows pointing at the (not yet existing) `{folder}/{filename}` S3 paths.
- `.env.example` documents the key (`IMPORT_VALIDATE_FILES=true` with a comment); the local `.env` gets `IMPORT_VALIDATE_FILES=false`.

## 2. Sample data

A ready-to-upload sample index at **`storage/app/dev/202607-pir-index.csv`** (git-ignored path; header `Charity Name,CC Ref,Q Score,Stability,Filename`) with ~20 plausible dummy charities (7-digit CC refs, Q/stability scores spread across 20–90, `{slug}-{ccref}.pdf` filenames). The `202607` filename prefix exercises the import dialog's auto-fill (Label "July 2026", folder "2026-07").

## 3. Testing

- Each importer gains one test: with `config(['reports.validate_import_files' => false])` and **no** `Storage::fake('s3')` file present, a batch with a well-formed row publishes (and the asset row records the expected path); a row with a **blank filename still fails**.
- All existing tests run with the default (`true`) and are untouched.

## 4. Out of scope / notes

- Downloads (teaser or purchased PDF) still error locally — the files genuinely don't exist. Accepted for this testing phase.
- Remove-the-flag is deliberately NOT planned: the default-true flag is harmless permanently and documents the S3 dependency.
