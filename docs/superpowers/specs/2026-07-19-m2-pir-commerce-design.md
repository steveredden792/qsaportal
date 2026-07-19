# M2 PIR Commerce Design Spec

**Date:** 2026-07-19
**Status:** Approved for planning
**Supersedes/extends:** `2026-07-17-pir-far-product-model-revision-design.md` (M2 row), `2026-06-12-qs-analysis-ecommerce-portal-design.md` (§6 checkout, updated: hosted Stripe Checkout replaces on-page card entry)

## 1. Context and scope

The FAR product is not yet signed off by the client (as of 2026-07-19). This milestone delivers **PIR single purchases only**, end to end: Stripe checkout, webhook-driven fulfilment, entitlement-gated downloads, a purchases dashboard, and admin refunds.

**In scope:** PIR single at the configured price (`config/pricing.php` → `pir.single`, currently 2500 pence dummy), hosted Stripe Checkout via Laravel Cashier behind a gateway interface, Order + Entitlement models, download gating through the existing `AccessService` seam, "My reports" dashboard, Filament Orders resource with refund action.

**Out of scope (deliberate):** PIR packs and annual subscriptions, upsell/"buy latest report" (M3); professional accounts, quotes, FAR checkout (deferred pending client sign-off); VAT/invoicing and bespoke receipt emails (Stripe's receipt email suffices for MVP — open question §9); download audit log.

**Constraint:** no Stripe account exists yet. Everything is built and tested against a fake gateway; real test keys are wired in later by dropping them into `.env`. The full suite must pass with no Stripe credentials.

## 2. Data model

Two new tables. No basket and no `order_items`: checkout is single-step from the PIR detail page and every purchasable in the revised product model (single, pack, subscription) is one line, so multi-line orders are YAGNI.

**orders**
- `id`, `user_id` FK, `issue_id` FK (frozen to the report's current issue at checkout time)
- `amount_pence` unsigned int, `currency` string default `gbp`
- `status` string: `pending → paid → fulfilled`, or `refunded` (from `fulfilled`). `paid` is transient — set by the webhook handler inside the fulfilment transaction; externally observable statuses are effectively `pending | fulfilled | refunded`.
- `stripe_checkout_session_id` nullable string, `stripe_payment_intent_id` nullable string
- timestamps

**entitlements**
- `id`, `user_id` FK, `issue_id` FK, `order_id` FK
- `expires_at` datetime (fulfilment time + 12 months)
- `revoked_at` nullable datetime (set on refund)
- timestamps

No unique constraint on (`user_id`, `issue_id`): a user may legitimately re-buy after expiry, producing a second row. **Active** entitlement = `revoked_at IS NULL AND expires_at > now()`.

`User` gains Cashier's `Billable` trait and Cashier's customer-columns migration now (M3 subscriptions need them; adding them once avoids churn).

Abandoned `pending` orders are inert rows; a fresh checkout click creates a new order. No cleanup job in this milestone.

## 3. Payment gateway boundary

```php
namespace App\Payments;

interface PaymentGateway
{
    /** Create a hosted checkout session for the order; returns the redirect URL. */
    public function checkoutUrl(Order $order, string $successUrl, string $cancelUrl): string;

    /** Verify and parse an incoming webhook request; null when signature/payload invalid. */
    public function webhookEvent(Request $request): ?WebhookEvent;

    /** Refund the order's payment in full. */
    public function refund(Order $order): void;
}
```

- `WebhookEvent` is a small DTO: `type` (e.g. `checkout.session.completed`), `orderId` (from session metadata), `paymentIntentId`.
- **StripeGateway** implements it with Cashier/Stripe SDK: Checkout session created with `metadata.order_id`, webhook verified with Stripe's signing secret.
- **FakePaymentGateway** is bound in tests: records `checkoutUrl`/`refund` calls, returns a stub URL, and lets tests hand-craft `WebhookEvent`s. Container binding in a service provider; tests swap via `app->instance()`.

## 4. Purchase flow

1. **PIR detail page:** the **Buy this report — £25** button shows unless the user holds an active entitlement for the report's **current issue**. A **Download** link shows for each issue the user holds an active entitlement on — so a user entitled to an older issue sees both their Download and the Buy button (no upsell copy in this milestone; that's M3). Price rendered from `Pricing::for('pir', 'single')`.
2. **`POST /reports/{report:slug}/checkout`** (`auth` + `verified`): aborts unless the report is a PIR with a current issue; rejects (redirect back with notice) when the user already holds an active entitlement for the current issue. Creates a `pending` Order frozen to the current issue, then redirects to `PaymentGateway::checkoutUrl()`.
3. **Webhook `POST /webhooks/stripe`** (no CSRF, no auth — signature is the auth): `PaymentGateway::webhookEvent()` verifies; unknown/invalid → 400. On `checkout.session.completed`, `FulfilOrder` runs in one DB transaction:
   - Load the order by `orderId`; if already `fulfilled` or `refunded`, no-op (idempotent — duplicate deliveries are safe).
   - Set `stripe_payment_intent_id`, status `paid` → create the Entitlement (`expires_at` = now + 12 months) → status `fulfilled`.
   - Access is **never** granted on client-side success; the webhook is the only fulfilment path.
4. **Success page `GET /checkout/success/{order}`** (auth, owner-only): reads live order status — `fulfilled` → "Payment confirmed — the report is in My reports"; `pending` → "Payment processing — check back shortly". No side effects.
5. **Cancel URL** → back to the report detail page.

## 5. Download access

`AccessService::canAccess()` (the seam M1 left) becomes:

- `Teaser` → any authenticated user (unchanged).
- `ReportPdf` / `Dataset` → user has an **active** entitlement for the asset's issue.

`DownloadController` and the signed-S3-URL mechanics are untouched.

## 6. Dashboard — My reports

Livewire page at `/my-reports` (`auth` + `verified`), nav link when authenticated. Lists the user's entitlements (newest first): report name, issue label, purchase date, status chip, and a download button while active.

Status chips: **Active** (>30 days left), **Expiring soon** (≤30 days left), **Expired** (past `expires_at` or revoked). Revoked entitlements display as Expired — no separate customer-facing "refunded" state.

## 7. Refunds (admin)

Filament **Orders** resource, group "Shop": read-only table (buyer, report/issue, amount, status, created). A **Refund** header action on `fulfilled` orders: confirm modal → `PaymentGateway::refund()` → order `refunded`, entitlement `revoked_at = now()`. Downloads stop immediately. Errors from the gateway surface as a danger notification; nothing is revoked when the refund call throws.

## 8. Testing

Pest feature tests, `FakePaymentGateway` bound, `Storage::fake('s3')` where downloads are exercised; no Stripe keys anywhere.

- Checkout: creates pending order frozen to current issue; redirects to gateway URL; guests/unverified bounced; double-purchase of an active entitlement rejected; non-PIR/`404` guard.
- Webhook: valid event fulfils exactly once (duplicate delivery → one entitlement); invalid signature → 400 and nothing written; unknown order → no-op 200.
- Success page: owner-only; reflects status; grants nothing.
- Access: ReportPdf allowed with active entitlement, denied without, denied after expiry (boundary: 12 months + 1 day) and after revocation; teaser behaviour unchanged.
- Dashboard: lists entitlements with correct chips (boundary: 30 days).
- Refund: revokes entitlement, marks order, calls gateway; gateway failure leaves everything intact.
- Full suite green throughout.

## 9. Open questions (not blockers)

1. VAT treatment and whether Stripe Tax is needed — client question; MVP charges the configured gross price.
2. Real PIR single price — 2500p is a dummy pending client confirmation (carried from v1 Appendix A).
3. Receipt emails beyond Stripe's default — revisit when a Stripe account exists.

## 10. Milestone map (revised)

| Milestone | Contents |
|---|---|
| **M2a (this spec)** | PIR singles: checkout, webhook fulfilment, entitlements, dashboard, refunds |
| **M2b (awaiting FAR sign-off)** | Professional accounts, quotes, FAR checkout |
| **M3** | PIR packs + annual subscriptions, live claims, upsell, renewal reminders |
