# QS Analysis Portal — PIR/FAR Product Model Revision

**Date:** 2026-07-17
**Status:** Approved design (supersedes the product model, gating, commerce, and ingestion sections of `2026-06-12-qs-analysis-ecommerce-portal-design.md`; that spec's architecture stack, S3 delivery mechanics, and compliance sections stand unchanged)

## 1. Why this revision

Client clarification (2026-07-17) redefined the product line. The v1 spec modelled three report types — FAR (charity), PPR (provider), PMR (market). The corrected model has **two**:

| | **PIR** — Public Information Report | **FAR** — Financial Analysis Report |
|---|---|---|
| Subject | Charity | Provider (professional services business) |
| v1 spec name | "FAR" | "PPR" / "PMR" (merged) |
| Audience | Anyone with a free registered account | Approved, fee-paid professional accounts |
| Pricing | Fixed: singles + packs + annual subscriptions | Negotiated per report; admin-recorded quote, paid online |
| Tiering | None | `tier` attribute per report (from the index spreadsheet) |
| Extras | — | 12-month access to related PIRs named in the FAR spreadsheet |

The names **PPR** and **PMR** disappear from spec, code, and UI. Everything M1 built under the name "FAR" (charity index import, search, catalogue, teasers) is renamed to PIR. This closes v1 open questions #5 (catalogue gating: gated behind free registration) and #7 (terminology: the marketing site's "PIR" was correct).

**Approach (approved):** in-place evolution. Report → Issue → Asset, `ImportBatch`, `AccessService`, and S3 delivery survive as built; the change is renames, re-gating, and new commerce pieces — not a rebuild.

## 2. Domain & data model changes

- **Report.type** → enum `PIR | FAR`. Migration: existing `FAR` rows become `PIR`; `PPR`/`PMR` rows are deleted (dev-only demo seeds — nothing purchased).
- **PIR subject = Charity** (unchanged from M1d: unique CC ref, latest Q score, Stability).
- **FAR subject = Provider**: name + unique provider reference from the spreadsheet. The **Market** table and **ProviderCharityLink** are dropped.
- **Report.tier** — nullable string, FAR only, validated on import against an admin-configurable allowed list.
- **Asset.s3_key** = `{YYYY-MM}/{filename}`: folder is the publication date, filename comes verbatim from the spreadsheet's filename column. The importer never derives keys.
- **FarPirReference** (new) — `far_issue_id ↔ charity_id`, from the CC refs listed in the FAR spreadsheet. Powers related-PIR grants.
- **Issue** semantics unchanged: each published import creates a new Issue per report and flips the prior one's `is_current` off. Older issues remain for in-window frozen entitlements.

## 3. Accounts & gating

One `users` table; two account flavours.

- **Free account** — existing Breeze registration + email verification. Unlocks the PIR Database Search, PIR detail pages, PIR teasers, and PIR purchasing. All PIR catalogue/search pages move behind `auth` + `verified` middleware (they are public today; this is a deliberate change).
- **Professional account** — a free account that has applied for FAR access. New `professional_accounts` table: `user_id`, business name, company number, nature of business, `status`, `approved_at`, `expires_at`.
  - Lifecycle: `pending` (application submitted) → `approved` (admin verifies in Filament; applicant emailed to pay) → `active` (annual fee paid via Stripe; `expires_at` = payment + 12 months) → `lapsed` (past `expires_at`). `rejected` is terminal from `pending`.
  - Renewal is **manual**: a lapsed provider pays the fee again; `expires_at` extends 12 months from the renewal payment date. No auto-renew machinery.
- **Gating matrix:** guests see marketing pages only. Free accounts see the PIR world. `active` professional accounts additionally see the FAR catalogue and can be quoted/purchase FARs. FARs never appear on PIR or public pages.
- **Account status vs report access:** professional-account status governs FAR *catalogue visibility and new purchases* only. Downloads of already-purchased reports are governed solely by each grant's own 12-month window (§4) — a lapsed provider can still download an in-window FAR but cannot browse or buy until renewing.

## 4. Commerce & access engine

### Products

- PIR single — fixed price.
- PIR packs — discounted multi-report allowances (sizes/prices per v1 Appendix A).
- PIR annual subscription — allowance with live claiming.
- Professional account fee — annual, manual renewal.
- FAR — per-report, quote-priced.

### Quotes (FAR purchases)

Admin creates a **Quote**: report × professional account × negotiated price. The provider sees open quotes in their dashboard and pays by Stripe checkout; payment consumes the quote and creates the entitlement. At most one open quote per report + provider.

### Access grants — the uniform 12-month rule

Every access grant shares one shape: `(user, report, frozen issue?, source, starts_at, expires_at = starts_at + 12 months)`.

1. **Entitlement** — PIR single or FAR purchase; frozen to the issue current at purchase.
2. **Claim** — against a pack allowance (frozen at claim) or an active subscription (live: receives issues published while the subscription is active). The claim itself still expires 12 months after it was made.
3. **RelatedPirGrant** — created on FAR purchase from that FAR issue's `FarPirReference` rows; resolves to each referenced charity's *current* PIR issue; same window as the FAR purchase.

`AccessService` answers the same single question as v1 — *can user U open this Asset (Issue I of Report R)?* — by unioning the sources, now with an `expires_at` check on every one. Enforcement is server-side everywhere and always re-checked at the pre-signed-URL download endpoint.

### Dashboard — "My reports"

Each purchased/claimed/granted report shows a **Status**:

- **Active** — within its 12-month window; live **Download** button.
- **Report Expired** — window passed; Download greyed out.
- **Upsell** — if a newer issue of the report exists, show **Buy latest report** (for FARs this is a request-a-quote action that notifies the admin to raise a quote, since prices are negotiated).

### Refunds

Admin-initiated via Stripe from Filament. Refunding revokes the grant — and for a FAR, its related-PIR grants.

## 5. Ingestion & catalogue publication

- **S3 contract:** the third party mass-uploads PDFs to `{bucket}/{YYYY-MM}/` (folder = publication date) and supplies one Excel index per report type. Each row names its file in a **filename** column.
- **PIR spreadsheet columns:** CC ref, charity name, Q score, Stability, filename. (The M1d importer gains filename + S3 validation.)
- **FAR spreadsheet columns:** provider ref, provider name, tier, filename, related charity CC refs.
- **Import flow** (extends the M1d Filament import page + `ImportBatch`):
  1. Admin uploads the spreadsheet and confirms the publication folder (e.g. `2026-07`).
  2. **Validate:** parse rows; HEAD-check every named file exists in that folder; validate tier against the allowed list; check CC/provider refs well-formed; flag duplicate rows. Errors are recorded per row on the batch.
  3. **Publish** — only when validation passes completely (**all-or-nothing**; a half-published current catalogue is worse than a delayed one): upsert Charities/Providers, ensure Reports, create the new Issue per report with its Asset at `{YYYY-MM}/{filename}`, flip prior `is_current`, write `FarPirReference` rows, update charity Q score/Stability.
  4. Batch lifecycle `draft → validated → published` (or `failed` with row errors); rerunnable after the third party corrects files.
- The published batch **is** the current catalogue: PIR search reads current PIR issues; the FAR catalogue reads current FAR issues.

## 6. Pages & code migration

- **Renames:** M1's "FAR" catalogue/search/detail/teaser pages and `import:far-index` become PIR (`import:pir-index`); `FarIndexFile`/`FarIndexImporter` renamed accordingly. PPR/PMR Livewire catalogues removed.
- **New pages:** professional-account application + status; open-quote list + payment; FAR catalogue + detail (active-professional middleware); admin Filament resources for professional-account approvals, quotes, and the FAR import.
- **Migrations:** enum rename `FAR→PIR`; drop PPR/PMR rows, Market, ProviderCharityLink; add `report.tier`, `professional_accounts`, `quotes`, `far_pir_references`; add expiry columns to grants. Dev database only — nothing is in production.

## 7. Testing

Pest feature tests per seam, as in M0/M1:

- Gating matrix: guest / free / pending / approved-unpaid / active / lapsed across PIR pages, FAR catalogue, and downloads.
- Professional-account lifecycle including manual renewal extending `expires_at` from payment date.
- Quote lifecycle: create, single-open-quote constraint, pay, consume, entitlement + related-PIR grants created.
- 12-month expiry boundaries for all three grant sources; dashboard status + upsell visibility.
- Import validation failure modes: missing S3 file, unknown tier, malformed/duplicate refs; all-or-nothing publish; `is_current` flip.

## 8. Milestones (reshaped)

| Milestone | Delivers |
|---|---|
| **M1.5 Model revision** | Renames + enum migration, PIR re-gating behind free registration, revised PIR import (filename + S3 validation), FAR import + Provider/tier/`FarPirReference` |
| **M2 Commerce** | PIR singles + packs, professional accounts (apply/approve/fee), quotes + FAR checkout, dashboard with status/expiry, refunds |
| **M3 Subscriptions + upsell** | PIR annual subscriptions with live claims, "Buy latest report" upsell, renewal reminders |
| **M4 Hardening** | Full import UX polish, audit, analytics, compliance pages, security review, UAT |

## 9. Assumptions to confirm

1. FAR spreadsheet delivers related charity CC refs as a delimiter-separated list in one column — confirm format with the third party.
2. PIR spreadsheet continues to carry Q score + Stability (as in the M1d index).
3. Publication cadence remains roughly twice yearly; the S3 folder name is the publication date, whatever the cadence.
4. Professional account fee amount — client to confirm (dummy value until then).
5. PIR single/pack/subscription prices — v1 Appendix A dummy prices stand until client confirms.
