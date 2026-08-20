# PIR Q Grade / Stability Grade Design Spec

**Date:** 2026-08-20
**Status:** Approved for planning
**Extends:** the PIR import pipeline (`app/Support/PirIndexFile.php`, `app/Support/IndexRows.php`, `app/Services/PirIndexImporter.php`) and the PIR catalogue (`app/Livewire/PirCatalogue.php`, `resources/views/livewire/pir-catalogue.blade.php`).

## 1. Background

The PIR index files supplied by the third-party actuary firm carry two extra columns beyond the existing `name` / `cc_ref` / `q_score` / `stability` / `filename` set:

- **Q Grade** — a letter grade (e.g. `a`, `aa`, `b`, `bb`, `bbb`) alongside the existing numeric `q_score`.
- **Stability Grade** — a numeric grade, roughly in the 1–10 range, alongside the existing numeric `stability`.

Both are supplementary bands the actuary provides next to the raw scores, not replacements for them. Both are optional (a batch with them blank still imports).

## 2. Schema

Mirrors the existing `q_score`/`stability` pattern: a versioned copy on `issues` and a denormalized "latest" cache on `charities`.

**`issues` table** (new nullable columns):
- `q_grade` — `string`
- `stability_grade` — `decimal(3,1)`

**`charities` table** (new nullable columns):
- `latest_q_grade` — `string`
- `latest_stability_grade` — `decimal(3,1)`

`stability_grade` uses one decimal place rather than an integer column — the source data was described as "looks like between 1 and 10" without confirmation it's always a whole number, and `decimal(3,1)` costs nothing if it turns out to always be an integer, whereas an integer column would silently truncate a fractional value if one ever appears. `q_grade` is a plain string with no format/enum constraint — the letter-grade vocabulary (`a`, `aa`, `bbb`, …) isn't fully enumerated and may extend; validating it up front would risk rejecting valid future values.

## 3. Import pipeline

**`app/Support/PirIndexFile.php`** — extend the header map:
- `"qgrade"` → `q_grade` (string, trimmed; blank/absent → `null`, since the column is optional and nothing meaningful was supplied)
- `"stabilitygrade"` → `stability_grade` (cast to `float`, same pattern as `q_score`/`stability`; blank/null → `null`)

(Header normalization already lowercases and strips punctuation/spaces, so `"Q Grade"` / `"Stability Grade"` in the source file both match.)

**`app/Services/PirIndexImporter.php`** — no new validation rules; both fields are optional exactly like `q_score`/`stability` are today. `import()` passes `q_grade`/`stability_grade` through on both the `Charity::create`/`update` calls (as `latest_q_grade`/`latest_stability_grade`) and the `Issue::create`/`update` calls (as `q_grade`/`stability_grade`), alongside the existing score fields.

## 4. Catalogue UI

`resources/views/livewire/pir-catalogue.blade.php` gains two new table columns, "Q Grade" and "Stability Grade", positioned next to the existing "Q score" / "Stability" columns. Display-only for this iteration:

- Not sortable — `PirCatalogue::SORTABLE` is unchanged.
- Not filterable — no new `qGradeMin`/`qGradeMax`-style URL-bound properties. Letter grades have no obvious numeric ordering to range-filter on, and nothing in the current requirement calls for filtering by grade.

A small legend/key element (e.g. a collapsible note or footnote near the table) explains what the grades mean. The design only builds the mechanism — a static block of markup the page owner can edit — not the content; the actual grade definitions are supplied by the user directly in the blade file as a follow-up, not derived from any data source.

## 5. Testing

- `PirIndexFile`/`IndexRows` — extend existing fixture-based tests to cover a row with both new columns populated, and a row with them blank (must not fail validation, must resolve to `null`/absent).
- `PirIndexImporter` — extend the existing "publishes a valid PIR batch" test to assert `q_grade`/`stability_grade` land on both the created `Issue` and the created/updated `Charity`.
- Catalogue: existing `PirCatalogue`/`pir-catalogue` render test extended to assert the two new columns render for a charity with grades set; no new sort/filter tests needed since neither is added.

## 6. Out of scope

- No change to the FAR import pipeline or FAR-side schema — this is PIR-only, per the source data.
- No filtering or sorting by grade in this iteration.
- No enum/validation constraint on `q_grade`'s letter vocabulary.
- The earlier charities/providers table rename discussed and withdrawn during brainstorming — not part of this spec.
