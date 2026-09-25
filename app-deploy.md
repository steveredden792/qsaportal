# QS Analysis Portal – Deployment Guide

How to get changes from your laptop (WAMP, VS Code) onto the live 20i Laravel Managed Cloud Server.

```
VS Code (build + commit + sync)  →  GitHub (steveredden792/qsaportal, main)  →  20i Git deploy  →  Command Executor
```

> **Command Executor runs one command at a time.** Don't chain commands with `&&`, and don't use quote marks (`"`). Run each line below on its own and wait for it to finish before you start the next. It can report "ran successfully" even when the command failed, so always read the output.

---

## 0. One-time setup (first deploy only)

Skip this section if the site is already live.

1. **Hosting package:** Managed Cloud Server → Hosting Packages → Add Hosting Package.
2. **Connect GitHub:** Git Version Control → Sign in with GitHub → Install & Authorize. Pick the `qsaportal` repository and click **Create**.
3. **Web root:** the site must serve from Laravel's `/public` folder, not the project root.
4. **Server `.env`:** create it with File Manager or Command Executor. It is never in Git. At a minimum:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://<live domain>`
   - `APP_KEY=` (generate it once with `php artisan key:generate --force`)
   - `DB_*`: the 20i MariaDB database, username and password
   - `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`: **live** keys
   - `AWS_*`, `AWS_BUCKET=qs-analysis-store`
   - `IMPORT_VALIDATE_FILES=true`
   - `DEMO_INSTANT_FULFIL=false`. **Never set this to true on live:** it marks orders as paid without charging through Stripe.
   - `QUEUE_CONNECTION=database`
5. **SSL:** SSL/TLS Certificates → Enable.
6. **Admin user (first deploy only):** `php artisan db:seed --class=AdminUserSeeder --force`
   - Don't run a plain `db:seed` on live. It also runs `CatalogueDemoSeeder`, which adds the demo/test reports.

---

## 1. Build and sync in VS Code (on your laptop)

The server's npm is too old to build the front end, so build on your laptop. `public/build` is committed to Git and deploys with the code.

1. Open `C:\wamp\www\qs-analysis-portal` in VS Code.
2. In the VS Code terminal, run:
   ```
   npm run build
   ```
   - Do this every time you change Blade views, CSS or JS. If you don't, the live site uses old CSS and new Tailwind classes won't show.
3. Open the **Source Control** panel (Ctrl+Shift+G) and check the changed files:
   - Include everything in `public/build/`, including deleted files. The old hashed files have to be removed from Git too.
   - Don't include `.env`, `.claude/settings.local.json` or `*.bak` files.
4. Enter a commit message (e.g. `feat: …` / `fix: …`) and click **Commit**.
5. Click **Sync Changes**, which pulls and then pushes.
6. Check it worked: the status bar should show no ↑/↓ counts. On GitHub, the latest commit on `main` should match your laptop.

---

## 2. Deploy from GitHub on 20i

1. Log in to the 20i control panel and open the site's hosting package.
2. Go to **Git Version Control**.
3. Select the `qsaportal` repository from the drop-down list.
4. Click the **Options** link and select **History**.
5. Click the **Deployment** tab.
6. Check the latest commit listed matches the one you just pushed, then click **Deploy**.

---

## 3. Command Executor – run these one at a time

Open the Laravel **Command Executor** for the hosting package. Paste each command on its own and wait for it to finish.

**3.1 Maintenance mode on**
```
php artisan down
```

**3.2 PHP dependencies** (only needed when `composer.json` / `composer.lock` changed; always run it on the first deploy)
```
composer install --no-dev --optimize-autoloader
```
- This also republishes the Filament admin assets automatically (`filament:upgrade`).
- If Command Executor doesn't accept `composer`, run it over SSH instead.
- If you skipped Composer this time, run `php artisan filament:assets` instead.

**3.3 Database migrations**
```
php artisan migrate --force
```

**3.4 Clear the old caches**
```
php artisan optimize:clear
```

**3.5 Rebuild the caches**
```
php artisan config:cache
```
```
php artisan route:cache
```
```
php artisan view:cache
```

**3.6 Restart queue workers** so they pick up the new code
```
php artisan queue:restart
```

**3.7 Maintenance mode off**
```
php artisan up
```

**3.8 Smoke test the live site**
- Home page and PIR catalogue load with the right styling.
- A report detail page opens.
- Add to basket → checkout reaches Stripe. Don't finish the payment unless you're testing with a real card.
- `/admin` loads and you can log in.

> Changed `.env` on the server? Run `php artisan config:cache` again. Laravel won't see the change until you do.

---

## 4. Publishing PIR data to live (catalogue sync)

The catalogue data isn't in Git. It comes from importing the PIR index file. Do this on first go-live and whenever you publish a new index.

**Before you start**
- The PDFs must already be in S3 at `pir/<YYYY-MM>/<CC ref>.pdf`, for example `qs-analysis-store/pir/2026-06/1084866.pdf`.
- The import checks every PDF exists in S3. If any are missing, **nothing is imported** and you get the row numbers that failed.

**4.1 Upload the index file.** Using 20i **File Manager**, upload the index CSV (e.g. `2026-06-pir-index.csv`) into the project's `storage/app/private/imports/` folder. This folder is git-ignored, so the file won't arrive with a deploy.

**4.2 Import it**, one command. The label ("June 2026") and S3 folder (`2026-06`) are taken from the `YYYY-MM` at the start of the filename, so name index files that way. Command Executor doesn't accept quote marks, so don't type a label with spaces.
```
php artisan import:pir-index storage/app/private/imports/2026-06-pir-index.csv
```
You can also use **Admin → Import PIR Index** in the browser. The command line is better for large files, because it won't hit the web upload size or time limits.

**4.3 Preview the demo/stale charities to remove** (dry run, deletes nothing)
```
php artisan catalogue:purge-unindexed storage/app/private/imports/2026-06-pir-index.csv
```

**4.4 Remove them**
```
php artisan catalogue:purge-unindexed storage/app/private/imports/2026-06-pir-index.csv --force
```
- If it refuses because test orders reference the demo reports, re-run it with `--with-orders`. **Only do this if those orders are test orders.**
- `--with-orders` also deletes the matching order items and entitlements, plus any orders left empty.

**4.5 Clear the cache**
```
php artisan cache:clear
```

---

## 5. Troubleshooting

| Symptom | Fix |
|---|---|
| Site unstyled / old styling | Run `npm run build` locally, commit `public/build`, sync, deploy again |
| Admin panel unstyled | `php artisan filament:assets` |
| 500 error after deploy | Set `APP_DEBUG=true` briefly, then `php artisan config:cache`, and read `storage/logs/laravel.log`. Set it back to `false` and run `config:cache` again straight after |
| `.env` change not picked up | `php artisan config:cache` |
| "Route not defined" / old routes | `php artisan route:cache` |
| Import says "File not found on S3" | Check the PDF path is `pir/<folder>/<CC ref>.pdf` and the folder argument matches |
| Import times out | Try again over SSH, or check the S3 credentials in `.env` (every row is checked against S3) |
| Stuck on the maintenance page | `php artisan up` |
| Only demo/test reports show | Follow section 4. Never run a plain `php artisan db:seed` on live |
