# QS Analysis — E-commerce Portal Design Spec

**Date:** 2026-06-12
**Status:** Approved design — ready for implementation planning
**Author:** Brainstormed with Claude Code

---

## 1. Overview & goals

A custom e-commerce portal for selling and digitally delivering PDF analysis reports about UK charities and the providers that serve them. Content is produced by a third party and refreshed **twice a year**.

**Primary goal (confirmed):** launch the **full catalogue** — FAR, PPR and PMR, all tiers — on day one.

**Three report types:**

| Code | Name | Level | Subject |
|---|---|---|---|
| **FAR** | Financial Analysis Report | Charity | A single charity |
| **PPR** | Provider Portfolio Report | Provider | A named provider |
| **PMR** | Provider Market Report | Category | A provider market/category |

**Access tiers:**
- **Anonymous** — marketing/landing pages, pricing, and the public FAR catalogue index (matching the existing PIR page). Teasers and purchasing require an account. (Whether to gate the full catalogue browse is an open question — §12.)
- **Free (registered)** — teasers, samples, thought pieces. Registration gates the teaser so disclosure is controlled.
- **Paid (registered + entitlements)** — purchased/claimed reports.

**Identity spine:** the **Charity Commission reference number (CC ref)** is the unique identifier tying the charity/FAR domain together — across the Excel index, the S3 PDFs, the relationship dataset, and import matching.

---

## 2. Recommended architecture

A single **Laravel 13 monolith** rendered with **Blade + Livewire + Tailwind** (Alpine.js for micro-interactions), deployed on the **20i Laravel-optimised cloud server** (Nginx + MariaDB), with **Amazon S3** for files and **Stripe** for payments.

Chosen over Inertia/SPA and decoupled API options because it is the fastest to build and maintain for a Laravel/PHP team, gives strong SEO on public/catalogue pages, needs no separate frontend app or API contract, and matches the app's actual interactivity (server-side catalogue filtering, claim buttons, dashboard).

**Six layers:**

| Layer | Responsibility | Key tech |
|---|---|---|
| Public + catalogue | Marketing/teaser pages, FAR/PPR/PMR catalogues with live filtering | Blade + Livewire |
| Accounts | Register/login, individual accounts, dashboard | Laravel Fortify/Breeze |
| Commerce | Products, single-step checkout, payments, orders | Laravel Cashier (Stripe) |
| Entitlement engine | The access-rules core | `AccessService` + policies |
| Content + delivery | Metadata in MariaDB, private files in S3, gated pre-signed URLs | Flysystem S3 |
| Admin | Catalogue, versioning, orders, users, twice-yearly import | Filament |

**Supporting choices:** **Filament** for the admin panel (delivers "basic admin controls" with minimal effort); **Laravel Cashier** for both one-off PaymentIntents and recurring subscriptions plus webhook handling; **Laravel queues** for heavy import processing; **Laravel Scout + Meilisearch** considered for Phase 2 search scaling (MVP uses indexed SQL queries).

---

## 3. Domain & data model

**Catalogue**
- **Report (Title)** — one report subject. `type` = FAR | PPR | PMR with a subject: FAR→Charity, PPR→Provider, PMR→Market. The canonical catalogue unit.
- **Issue (Version)** — a twice-yearly release of a Report. Holds publish date, version label, `is_current`, and (for FAR) point-in-time Q score + Stability. Enables version control + "2 issues/year".
- **Asset** — a file on an Issue: report PDF, relationship-dataset file (Enhanced), or free teaser/sample. All private S3.
- **Charity / Provider / Market** — subject tables. Charity mirrors the Excel index (CC ref [unique], latest Q score, Stability).
- **ProviderCharityLink** — the supplied relationship dataset (provider ↔ charity by CC ref). Powers Enhanced (dataset deliverable) and Premium (FAR cross-access).

**Commerce & entitlements**
- **Product** — a purchasable SKU: FAR single (→FAR title), FAR pack (5–2,000), FAR subscription (20–2,000), PPR (Standard/Enhanced/Premium →PPR title), PMR (Standard/Premium →PMR title). Carries price + VAT treatment (currently none).
- **Order / OrderItem** — a Stripe purchase and its lines.
- **Entitlement** — direct access to a specific Report + tier (FAR single, PPR, PMR), with a version policy and optional expiry.
- **Allowance** — a pack or subscription balance: total + remaining; behaviour = frozen (pack) or live (subscription).
- **Claim** — a FAR title claimed against an Allowance. Pack claims freeze to the claimed Issue; subscription claims stay live for future Issues within the active period. Decrements the Allowance.
- **CrossAccessGrant** — from Premium PPR/PMR: time-boxed FAR access to a set of charities (by CC ref), with `expires_at`.
- **ImportBatch** — a twice-yearly ingestion run (draft → validated → published), enabling versioning and rollback.

---

## 4. Entitlement & access engine (the core)

All access routes through **one question**, answered by a single `AccessService` that unions the access sources and takes the most permissive:

> *Can user U open this Asset — Issue I of Report R at tier T?*

**Version policy by product:**

| Product | Version policy |
|---|---|
| FAR single (£25) | Frozen to the issue current at purchase (no future issues) |
| FAR pack | Frozen to the issue current at claim (no future issues) |
| FAR subscription | Live: claimed titles receive new issues published **within the active (12-month, auto-renewing) period**; on lapse, already-delivered issues remain, new issues stop, claiming pauses until renewal |
| PPR (Standard/Enhanced/Premium) | **Frozen to the purchased version** (no future versions) |
| PMR (Standard/Premium) | **Frozen to the purchased version** (no future versions) |

"Version control for PPR/PMR" therefore means: pin each purchase to the exact version bought and keep it available — not deliver new versions.

**Tier semantics:**
- **PPR Standard** → report PDF only.
- **PPR Enhanced** → report PDF + relationship dataset asset.
- **PPR Premium** → report PDF + dataset + **CrossAccessGrant** (time-boxed FAR access to the provider's linked charities).
- **PMR Standard** → category report PDF only.
- **PMR Premium** → category report + **CrossAccessGrant** (time-boxed FAR access to the charities referenced in the PMR index file) + agreed supporting data.

**Three access sources unioned by the engine:**
1. **Direct Entitlement** — FAR single, PPR, PMR (frozen as above).
2. **Claim** — against a pack (frozen) or active subscription (live) Allowance.
3. **CrossAccessGrant** — Premium PPR/PMR, resolves dynamically to the linked/referenced charities' latest FAR issue while `now < expires_at`; access ends at expiry.

**Catalogue state per report, per user** (drives the button):
- No entitlement, no balance → **Buy Now** (+ teaser link for free users)
- Unclaimed eligible FAR + remaining pack/active-sub balance → **Access Report** (claim)
- Already entitled/claimed → **Open**

**Claim flow:** click *Access Report* → verify `remaining > 0`, subscription active (if applicable), and not already claimed → create Claim (freeze issue for pack / live for sub) → atomically decrement balance → report appears in account. Irreversible, no approval. Protected by a DB transaction + unique `(allowance, report)` constraint against double-claim/oversell.

**Enforcement** is server-side at every touchpoint, and **always re-checked at the download endpoint** before any pre-signed URL is issued. The client is never trusted.

---

## 5. Catalogue & search

Three catalogues, each a Livewire-filtered, **server-paginated** index.

- **FAR catalogue** (~2,000 rows): columns **Charity name · CC ref · Q score · Stability** + state-aware action. Controls: **keyword** (name/CC ref), **Q score min/max**, **Stability min/max**, sort by name/Q/Stability. Indexed SQL queries keep it fast; **this search is MVP, not Phase 2**, because the catalogue is unusable without it.
- **PPR catalogue** (tens of rows): providers/PPR titles; detail page offers Standard/Enhanced/Premium. Simple browse.
- **PMR catalogue** (tens of rows): markets/categories; detail page offers Standard/Premium. Simple browse.
- **Detail pages**: summary, free teaser/sample (registration-gated), current version info, tier/price selection, single-step Buy/Access.

The catalogue **index** (name, CC ref, Q score, Stability) is public to match the existing PIR database page and aid SEO; **teaser/sample previews are registration-gated**. (Confirm whether full catalogue browse should also be gated — see open questions.)

---

## 6. Checkout & payments

Single-step from the detail page.
- **One-off** (FAR single, pack, PPR, PMR): Stripe PaymentIntent via Cashier; card entry on the detail page. Access granted **only on webhook confirmation**.
- **Subscription** (FAR sub): Stripe recurring subscription via Cashier (12-month term, auto-renew); creates the Allowance tied to live subscription status; renewals + status changes handled by webhooks.
- **Order lifecycle:** `pending → paid (webhook) → fulfilled (entitlement granted)`. Idempotent webhooks; reconciliation job; never grant on client-side success.
- **Refunds:** admin-initiated Stripe refund → revoke the associated entitlement/allowance/grant (cancel subscription where applicable). Refund policy for partially-claimed packs/subscriptions to be defined (see open questions).
- **VAT:** none currently (company not VAT-registered). Built **VAT-ready** — a per-product-type VAT setting defaulting to zero — so VAT (likely 20%) and VAT invoices can be switched on when revenue crosses the £90,000 registration threshold.
- Email receipt + "it's in your account" confirmation.

---

## 7. Secure S3 delivery

- **Private** bucket, no public URLs.
- Download endpoint runs the `AccessService` first → if allowed, mint a **short-TTL (~5 min) pre-signed URL** and redirect; else **403**.
- Resolves to the correct issue per entitlement: frozen → exact entitled issue; live subscription/within-period → current issue; expired Premium window → denied.
- **Download audit log** (who/what/when) for support + analytics.
- S3 keys never exposed; predictable versioned layout, e.g. `far/{cc_ref}/{issue}/report.pdf`.

---

## 8. Admin & content ingestion / versioning

Built on **Filament**.

**Everyday admin:** Reports/Issues/Assets · Charities/Providers/Markets · Provider↔Charity links · Products & pricing · Orders (view, Stripe refund, resend receipt) · Users (entitlements/allowances/claims + support actions) · Subscriptions (status, cancel) · Download audit log · manual entitlement grant · a light **sales/downloads dashboard** (basic analytics for MVP).

**Twice-yearly ingestion — built around an Import Batch for safety:**

Inputs: the **Excel index** (FAR metadata), the **PDFs** (FAR/PPR/PMR), and the **relationship/reference data** (provider↔charity for PPR; referenced charities for Premium PMR — both by CC ref).

1. Admin opens a new **Import Batch** (e.g. "2026 H1"), draft.
2. **Upload Excel** → parse + validate (CC ref format, required columns, numeric Q/Stability), **match on CC ref**: existing charity → new Issue; unseen CC ref → new Charity + Report + first Issue. Shows a **diff preview** (new / updated / unchanged / errors).
3. **Upload PDFs** (bulk, direct-to-S3) → matched by CC ref / identifier; flags unmatched files and titles missing a file.
4. **Upload relationship/reference data** → validates references resolve; flags orphans.
5. **Validation report** — counts, errors, warnings. Nothing live yet.
6. **Publish** (atomic) → creates new Issues, flips `is_current`, updates each charity's latest Q/Stability, activates links. **Previous issues retained** as version history.
7. On publish, **active subscriptions auto-receive** the new issue of their claimed titles; **frozen entitlements** keep their pinned version.
8. **Unpublish/rollback** a batch if needed.

Safety: idempotent CC-ref matching, validate-before-publish dry run, atomic publish in a transaction, full audit trail. Heavy work runs on **queued jobs**; PDFs go **direct-to-S3** to avoid PHP upload limits.

---

## 9. Compliance

Standard policies in MVP: GDPR (data handling, right-to-erasure considerations), Terms & Conditions, Privacy Policy, cookie consent, and email verification on registration.

---

## 10. MVP feature list

- Branded public shell + registration-gated free teaser/thought-piece content
- Register/login/email verification, individual accounts, **dashboard** (allowances, owned reports, downloads)
- **FAR catalogue** with keyword + Q-score range + Stability range search; **PPR & PMR** browse + detail pages
- All products: FAR single / packs / subscriptions; PPR Standard·Enhanced·Premium; PMR Standard·Premium
- **Entitlement engine**: direct entitlements, allowances + claims (pack frozen / sub live), Premium cross-access
- Single-step **Stripe** checkout (one-off + recurring) + webhooks; **refunds** + revocation
- **Secure S3 delivery** + download audit
- **Filament admin** + the twice-yearly **Import Batch** pipeline
- Basic sales/downloads analytics
- Standard compliance policies

---

## 11. Development plan & phased roadmap

The MVP ships as one launch; the build is sequenced into five milestones.

| Milestone | Delivers |
|---|---|
| **M0 Foundation** | Laravel 13 + auth + accounts, brand theme, Filament, S3 wiring, core data model (Report/Issue/Asset/Charity/Provider/Market) |
| **M1 Catalogue + delivery** | FAR/PPR/PMR catalogues, FAR search, detail pages, secure download endpoint + entitlement skeleton, Import Batch v1 (content to show) |
| **M2 Commerce (one-off)** | Products/pricing, single-step Stripe one-off, orders, FAR single + packs + PPR + PMR, pack claims, dashboard allowances, refunds |
| **M3 Subscriptions + Premium** | Recurring Stripe subscriptions, subscription claims + auto-issue on publish, Premium cross-access grants, bespoke Premium PMR handling |
| **M4 Hardening** | Full Import Batch UX, audit, analytics, compliance pages, security review, UAT |

**Phase 2:** faceted/saved search (Scout/Meilisearch), promotions/discount logic, smarter admin tools, marketing integrations, expanded customer self-service, advanced reporting, possibly invoice/PO payments.

**Phase 3+:** organisations & multi-seat accounts (the deferred account model), VAT activation at the £90k threshold (VAT invoices), public API.

---

## 12. Open questions / assumptions to confirm

1. **PPR/PMR pricing** — RESOLVED (2026-06-13): dummy prices, per report, no bundles or subscriptions — PPR Standard £50 / Enhanced £75 / Premium £100; PMR Standard £50 / Premium £100. Revisit with client before launch.
2. **Premium PMR references** — assumes the client supplies the referenced charities (by CC ref) in the PMR index file. To confirm.
3. **Enhanced dataset deliverable** — assumed a downloadable file asset referenced in the index file. Client to confirm format and referencing.
4. **Premium PMR "agreed supporting data"** — standardised or negotiated per sale? Needs an admin-configurable definition if bespoke.
5. **Catalogue gating** — keep the FAR index public (matches existing PIR page + SEO) or gate the full browse behind free registration? Teaser is gated either way.
6. **Refund policy** for partially-claimed packs/subscriptions (refund allowed once claims used? pro-rata?).
7. **Public terminology** — the existing marketing site uses "PIR"; the portal uses FAR/PPR/PMR. Reconcile copy.
8. **Subscription lapse/renew edge cases** — unclaimed allowance frozen then resumed on renewal (assumed).
9. **Cross-access window length** for Premium (e.g. 12 months) — confirm default and whether configurable per product/sale.

---

## 13. Risks

**Technical**
- **Entitlement engine** is the highest-risk logic (frozen vs live × three access sources). Mitigation: one well-tested `AccessService` as the single source of truth, built test-first.
- **Bulk twice-yearly ingestion** (2,000 PDFs + index + links matched on CC ref) is exposed to third-party data-quality issues. Mitigation: validate-before-publish, dry-run preview, atomic publish, rollback, queued processing.
- **Stripe webhook reliability/idempotency** — access depends on webhooks. Mitigation: idempotent handlers, reconciliation job, never grant on client success.
- **S3 access security** — pre-signed URL leakage / bucket misconfiguration. Mitigation: private bucket, short TTL, server-side gate on every download, audit.
- **Claim concurrency** — double-claim/oversell. Mitigation: transactions + unique constraints + atomic decrement.
- **Catalogue performance** at 2,000+ with filters. Mitigation: indexed columns + server pagination now; search engine in Phase 2.

**Commercial**
- **Premium cross-access generosity** — one Premium purchase can unlock many FARs (all linked/referenced charities) for the window. Review pricing vs value given away.
- **Subscription auto-renew** — needs clean dunning (Cashier) and cancellation comms to avoid disputes/chargebacks.
- **Perpetual frozen access** — buyers keep purchased versions indefinitely; historical version storage grows (cheap on S3, but noted).
- **VAT activation** — once over £90k, VAT treatment and VAT invoices must be correct; plan the switch before crossing the threshold.
- **Card-only at launch** — large B2B buyers (up to £3,000) may expect invoicing; revisit in Phase 2.
- **Bespoke Premium PMR** — "agreed/defined" terms risk inconsistent fulfilment without a clear admin workflow.

---

## Appendix A — Pricing (from brief; all currently no VAT)

**FAR single:** £25.00

**FAR report packs:**

| FARs | Price | | FARs | Price |
|---|---:|---|---|---:|
| 5 | £100 | | 200 | £800 |
| 10 | £150 | | 500 | £1,250 |
| 20 | £250 | | 1,000 | £1,500 |
| 50 | £450 | | 2,000 | £2,000 |
| 100 | £600 | | | |

**FAR subscriptions** (min 20, 2 issues/year, 12-month auto-renew):

| FARs | Price | | FARs | Price |
|---|---:|---|---|---:|
| 20 | £375 | | 500 | £1,875 |
| 50 | £675 | | 1,000 | £2,250 |
| 100 | £900 | | 2,000 | £3,000 |
| 200 | £1,200 | | | |

**PPR** (per report, no bundles): Standard £50 · Enhanced £75 · Premium £100.

**PMR** (per report, no bundles): Standard £50 · Premium £100.

_(Dummy prices set 2026-06-13; revisit with client. S3 bucket `qs-analysis-store` configured; IAM still needs `s3:ListBucket` for the import pipeline.)_

## Appendix B — Glossary

- **FAR** — Financial Analysis Report (charity-level)
- **PPR** — Provider Portfolio Report (provider-level)
- **PMR** — Provider Market Report (category-level)
- **CC ref** — Charity Commission reference number (unique charity identifier)
- **Title / Report** — a report subject; **Issue** — a versioned twice-yearly release; **Asset** — a file on an Issue
- **Allowance** — a pack/subscription claim balance; **Claim** — a FAR title taken against an allowance
- **CrossAccessGrant** — time-boxed FAR access granted by a Premium PPR/PMR purchase
