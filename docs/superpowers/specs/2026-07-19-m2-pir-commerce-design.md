# M2 PIR Commerce Design Spec

**Date:** 2026-07-19 (rev 2 — basket added)
**Status:** Approved for planning
**Supersedes/extends:** `2026-07-17-pir-far-product-model-revision-design.md` (M2 row), `2026-06-12-qs-analysis-ecommerce-portal-design.md` (§6 checkout, updated: hosted Stripe Checkout replaces on-page card entry; basket added to spare multi-report buyers repeated checkouts)

## 1. Context and scope

The FAR product is not yet signed off by the client (as of 2026-07-19). This milestone delivers **PIR single purchases with a basket**, end to end: add-to-basket, one Stripe checkout for the whole basket, webhook-driven fulfilment, entitlement-gated downloads, a purchases dashboard, and admin refunds.

**In scope:** PIR singles at the configured price (`config/pricing.php` → `pir.single`, currently 2500 pence dummy) bought via a DB-backed basket and hosted Stripe Checkout (Laravel Cashier behind a gateway interface); Basket/Order/OrderItem/Entitlement models; download gating through the existing `AccessService` seam; "My reports" dashboard; Filament Orders resource with per-item refunds.

**Out of scope (deliberate):** PIR packs and annual subscriptions, upsell/"buy latest report" (M3); professional accounts, quotes, FAR checkout (deferred pending client sign-off); VAT/invoicing and bespoke receipt emails (Stripe's receipt email suffices for MVP — open question §9); download audit log; promotions/discounts.

**Constraint:** no Stripe account exists yet. Everything is built and tested against a fake gateway; real test keys are wired in later by dropping them into `.env`. The full suite must pass with no Stripe credentials.

## 2. Data model

**basket_items**
- `id`, `user_id` FK, `report_id` FK, timestamps
- unique (`user_id`, `report_id`) — adding twice is a no-op
- DB-backed (not session): every PIR shopper is authenticated, so the basket survives logout and device switches. No parent basket table.
- Stores the *report*, not an issue: issues are frozen at checkout time (§4), not add time — a basket can outlive an import that flips the current issue.

**orders**
- `id`, `user_id` FK
- `total_pence` unsigned int, `currency` string default `gbp`
- `status` string: `pending → paid → fulfilled`, or `refunded` (terminal, only when **every** item is refunded). `paid` is transient — set by the webhook handler inside the fulfilment transaction; externally observable statuses are effectively `pending | fulfilled | refunded`.
- `stripe_checkout_session_id` nullable string, `stripe_payment_intent_id` nullable string
- timestamps

**order_items**
- `id`, `order_id` FK, `issue_id` FK (frozen to the report's current issue at checkout time)
- `amount_pence` unsigned int
- `refunded_at` nullable datetime (per-item refunds, §7)
- timestamps

**entitlements**
- `id`, `user_id` FK, `issue_id` FK, `order_item_id` FK
- `expires_at` datetime (fulfilment time + 12 months)
- `revoked_at` nullable datetime (set on refund of its order item)
- timestamps

No unique constraint on (`user_id`, `issue_id`): a user may legitimately re-buy after expiry, producing a second row. **Active** entitlement = `revoked_at IS NULL AND expires_at > now()`.

`User` gains Cashier's `Billable` trait and Cashier's customer-columns migration now (M3 subscriptions need them; adding them once avoids churn).

Abandoned `pending` orders are inert rows; a fresh checkout creates a new order. No cleanup job in this milestone.

## 3. Payment gateway boundary

```php
namespace App\Payments;

interface PaymentGateway
{
    /** Create a hosted checkout session for the order (one Stripe line per order item); returns the redirect URL. */
    public function checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string;

    /** Verify and parse an incoming webhook request; null when signature/payload invalid. */
    public function webhookEvent(Request $request): ?WebhookEvent;

    /** Refund one order item's amount against the order's payment (Stripe partial refund). */
    public function refundItem(OrderItem $item): void;
}
```

- `WebhookEvent` is a small DTO: `type` (e.g. `checkout.session.completed`), `orderId` (from session metadata), `paymentIntentId`.
- **StripeGateway** implements it with Cashier/Stripe SDK: Checkout session created with `metadata.order_id` and one line item per report, webhook verified with Stripe's signing secret.
- **FakePaymentGateway** is bound in tests: records `checkoutUrl`/`refundItem` calls, returns a stub URL, and lets tests hand-craft `WebhookEvent`s. Container binding in a service provider; tests swap via `app->instance()`.

## 4. Basket and purchase flow

1. **Add to basket:** buttons on PIR catalogue rows and the detail page. Button states: **Add to basket** → **In basket** (already added) → hidden entirely when the user holds an active entitlement for the report's current issue (they see the Download link instead). A user entitled to an *older* issue still sees Add to basket for the current one (no upsell copy this milestone; that's M3).
2. **Basket page `/basket`** (`auth` + `verified`, Livewire): lines with report name and price, remove per line, total, **Checkout** button (disabled when empty). Nav shows a basket icon with live line count for authenticated users.
3. **`POST /checkout`** (`auth` + `verified`): re-validates every basket line — report is a PIR with a current issue and the user holds no active entitlement for that current issue. Any stale line → redirect back to the basket with a notice naming the offending reports (nothing created). Otherwise: create one `pending` Order with an OrderItem per line (each frozen to that report's current issue, priced at `Pricing::for('pir', 'single')`, total = sum), then redirect to `PaymentGateway::checkoutUrl()`.
4. **Webhook `POST /webhooks/stripe`** (no CSRF, no auth — signature is the auth): `PaymentGateway::webhookEvent()` verifies; invalid → 400. On `checkout.session.completed`, `FulfilOrder` runs in one DB transaction:
   - Load the order by `orderId`; if already `fulfilled` or `refunded`, no-op (idempotent — duplicate deliveries are safe).
   - Set `stripe_payment_intent_id`, status `paid` → create one Entitlement per order item (`expires_at` = now + 12 months) → status `fulfilled` → delete the purchased reports' basket lines.
   - Access is **never** granted on client-side success; the webhook is the only fulfilment path.
5. **Success page `GET /checkout/success/{order}`** (auth, owner-only): reads live order status — `fulfilled` → "Payment confirmed — your reports are in My reports"; `pending` → "Payment processing — check back shortly". No side effects.
6. **Cancel URL** → back to the basket, which is intact (lines are only cleared on fulfilment).

## 5. Download access

`AccessService::canAccess()` (the seam M1 left) becomes:

- `Teaser` → any authenticated user (unchanged).
- `ReportPdf` / `Dataset` → user has an **active** entitlement for the asset's issue.

`DownloadController` and the signed-S3-URL mechanics are untouched.

## 6. Dashboard — My reports

Livewire page at `/my-reports` (`auth` + `verified`), nav link when authenticated. Lists the user's entitlements (newest first): report name, issue label, purchase date, status chip, and a download button while active.

Status chips: **Active** (>30 days left), **Expiring soon** (≤30 days left), **Expired** (past `expires_at` or revoked). Revoked entitlements display as Expired — no separate customer-facing "refunded" state.

## 7. Refunds (admin)

Filament **Orders** resource, group "Shop": table (buyer, item count, total, status, created) with an items detail view. Refunds are **per order item**: a Refund action on each unrefunded item of a `fulfilled` order → confirm modal → `PaymentGateway::refundItem()` (Stripe partial refund of that item's amount) → item `refunded_at = now()`, its entitlement `revoked_at = now()`. A **Refund all** convenience action loops the remaining items. When every item is refunded the order's status becomes `refunded`. Gateway errors surface as a danger notification; nothing is revoked when the refund call throws.

## 8. Testing

Pest feature tests, `FakePaymentGateway` bound, `Storage::fake('s3')` where downloads are exercised; no Stripe keys anywhere.

- Basket: add (idempotent), remove, count badge, button states (Add/In basket/hidden-when-owned), guests/unverified bounced.
- Checkout: creates pending order + items frozen to current issues with correct total; redirects to gateway URL; empty basket rejected; stale line (report becomes owned or loses current issue) → notice, nothing created.
- Webhook: valid event fulfils exactly once — entitlement per item, basket lines cleared (duplicate delivery → no double entitlements); invalid signature → 400 and nothing written; unknown order → no-op 200.
- Success page: owner-only; reflects status; grants nothing.
- Access: ReportPdf allowed with active entitlement, denied without, denied after expiry (boundary: 12 months + 1 day) and after revocation; teaser behaviour unchanged.
- Dashboard: lists entitlements with correct chips (boundary: 30 days).
- Refund: per-item refund revokes only that item's entitlement; refunding the last item flips the order to `refunded`; gateway failure leaves everything intact.
- Full suite green throughout.

## 9. Open questions (not blockers)

1. VAT treatment and whether Stripe Tax is needed — client question; MVP charges the configured gross price.
2. Real PIR single price — 2500p is a dummy pending client confirmation (carried from v1 Appendix A).
3. Receipt emails beyond Stripe's default — revisit when a Stripe account exists.
4. Basket-level quantity discounts (e.g. 5+ PIRs) — packs are the M3 answer; flag if the client wants ad-hoc discounts sooner.

## 10. Milestone map (revised)

| Milestone | Contents |
|---|---|
| **M2a (this spec)** | PIR singles + basket: checkout, webhook fulfilment, entitlements, dashboard, per-item refunds |
| **M2b (awaiting FAR sign-off)** | Professional accounts, quotes, FAR checkout |
| **M3** | PIR packs + annual subscriptions, live claims, upsell, renewal reminders |
