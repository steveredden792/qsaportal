# Branding wrapper deployment — verification checklist

## Context

A Cowork session deployed a rebrand of the shared header/hero/footer wrapper
and related shared components into this project, based on the design at
qsanalysis.co.uk. It edited files directly on disk but had **no way to run
`php artisan`, `npm`, or a browser** — so nothing here has actually been
booted or visually checked yet. That's the job for this session.

Brand tokens used throughout: navy `#002842`, teal `#00c7c3`, light grey
`#dcdfe1`, headings in Figtree, body text in Albert Sans.

## Step 1 — Build assets

The colour/font changes live in `tailwind.config.js` and won't appear until
the CSS is rebuilt:

```
npm install   # only if node_modules is stale/missing
npm run dev   # or: npm run build
```

Leave `php artisan serve` running (or confirm it's already running) and keep
`npm run dev` running alongside it if you want Vite HMR while you check
things over.

## Step 2 — Files that changed (for context, not to redo)

Wrapper / shared:
- `resources/views/components/public.blade.php` — main public-page wrapper. Added a reduced-height hero band (`$title` / optional `$subtitle` props), swapped the hand-drawn logo for `<x-application-logo>`, fixed header bg to `bg-qsa-grey`, fixed a bug where the account icon always linked to `login` even when signed in.
- `resources/views/components/application-logo.blade.php` — now renders `public/images/logo-header.png` instead of the generic Breeze diamond SVG.
- `resources/views/layouts/guest.blade.php` — now wraps Volt auth pages (login/register/forgot-password/reset-password/confirm-password/verify-email) in `<x-public>` instead of a bare grey Breeze card, so auth pages share the site header/hero/footer.
- `resources/views/layouts/app.blade.php` — light touch only: added Albert Sans to the font link, body bg to `bg-qsa-grey`.
- `resources/views/welcome.blade.php` — homepage kept its own header/footer markup (it has a richer in-page anchor nav that the shared wrapper doesn't), but got the real logo, local hero image (`public/images/hero-network.jpg` — previously hotlinked to the live qsanalysis.co.uk site), brand-navy gradient, grey sections, same signed-in-icon bugfix.
- `resources/views/checkout/success.blade.php`, `resources/views/reports/pir-detail.blade.php` — removed now-redundant in-page `<h1>` since the hero band already shows `$title`; `checkout/success` now passes `subtitle="Order complete"` to the hero.
- `resources/views/components/nav-link.blade.php`, `responsive-nav-link.blade.php`, `text-input.blade.php`, `primary-button.blade.php`, `secondary-button.blade.php` — Breeze's default indigo focus/active states swapped for brand navy/teal.
- `tailwind.config.js` — `colors.brand.DEFAULT` = navy, `colors.brand.light` = teal (this token already existed and is used across 8+ files — repurposed rather than renamed), added `colors.qsa.grey`, split `fontFamily.sans` (Albert Sans) / added `fontFamily.heading` (Figtree).
- `public/images/logo-header.png`, `public/images/hero-network.jpg` — new asset files.

Deliberately **not** touched: PIR catalogue table, basket page, dashboard
cards, profile forms — page content, out of scope for a wrapper pass.

## Step 3 — Things to actually check in a browser

Go through each and look for: the real Q Score Analysis logo (not a Laravel
diamond), navy header text / teal accents (not indigo/purple), Figtree on
headings vs Albert Sans on body text, the hero band with the mesh-network
background image loading (not a broken image icon), and no 500 errors.

1. `/` — homepage. Check the hero image loads (was previously hotlinked to
   an external URL, now local), nav anchors still scroll to sections, the
   account icon in the header goes to `login` when signed out.
2. `/login`, `/register`, `/forgot-password` — should now show the full
   site header + a hero band (title defaults to "My Account") + footer
   around the auth card, not a bare grey box like before.
3. Log in, then check `/dashboard` and `/profile` — should have the grey
   body background and updated logo in the top nav; content itself is
   unchanged.
4. `/catalogue/pir` — should render inside the `<x-public>` wrapper with
   the new hero. **This route has `ensure-search-access` middleware** —
   confirm it's reachable with your test user, and check the table/filter
   UI itself still works (untouched, but worth confirming nothing broke).
5. Open a PIR detail page (`/reports/{slug}`) — hero should show the
   charity name as the title and "Public Information Report" as the
   subtitle; the duplicate heading that used to repeat the charity name in
   the body should be gone.
6. Run a test purchase through to `/checkout/success/{order}` if you have
   a way to trigger it — hero should read "Checkout" / "Order complete".
7. Log out and confirm the account icon on every page above goes to
   `login`; log in and confirm it goes to `profile` (this was a pre-existing
   bug — the icon used to hardcode `route('login')` regardless of auth
   state — fixed as part of this pass, worth double-checking it actually
   works with real session state).

## Step 4 — Known risk areas / likely failure points

Since none of this was actually executed before landing:

- Watch for a Blade error if `route('profile')` isn't resolvable in some
  edge case — it's used in `public.blade.php` and `welcome.blade.php`'s
  account icon.
- `layouts/guest.blade.php` now depends on `$slot` flowing correctly
  through a view that consists of a single `<x-public>` component call —
  confirm Volt's `#[Layout('layouts.guest')]` mechanism still renders
  correctly (should be fine, but it's the one structural change here vs.
  everything else being a styling edit).
- Bunny Fonts (`fonts.bunny.net`) needs outbound network access to load
  Figtree/Albert Sans — if this dev box is offline, headings/body text
  will silently fall back to system fonts (not a bug, just a local-dev
  quirk).
- If Tailwind's JIT purge doesn't pick up `bg-qsa-grey` / `text-brand-light`
  etc., it's almost always because `npm run dev`/`build` wasn't re-run
  after the config change — check that first before assuming a markup bug.

## Step 5 — Report back

Once checked, let the user know: what works, what's broken (with the exact
error/screenshot), and anything in "known risk areas" above that turned out
to be a real problem vs. a non-issue.
