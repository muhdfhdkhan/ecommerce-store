# Patches&Motifs — Embroidery E-Commerce & Management System

Store: **Patches&Motifs** · Stack: Core PHP 8.x (XAMPP ships 8.5), MySQL/MariaDB, Tailwind CSS, Vanilla JS, PDO.

Docs that govern this codebase: `SECURITY.md` (mandatory on every feature), `DESIGN.md`, `PROJECT_MVP.md`, `PROJECT_BRIEF.md`, `COMPLETE_PROJECT_BRIEF.md`.

## Status

**Phases 1–8 complete** (storefront, catalog, cart/checkout, orders & admin ops,
business management, website management, production readiness):

- Phase 1–2: Foundation, catalog (products/categories/collections with multi-image
  upload, variant size/length/color for patches & motifs)
- Phase 3–4: Storefront (hero slider from admin-managed banners with patch/motif
  titles, shop, cart, checkout with coupons, account area)
- Phase 5: Order lifecycle, payments, customers, inventory, shipping, audit logs
- Phase 6 (business): `admin/analytics.php` (KPIs, 14-day revenue chart, top products,
  low stock, pipeline), `admin/promotions.php` (coupons), `admin/customers.php`,
  `admin/suppliers.php` + `admin/purchasing.php` (PO draft → ordered → received with
  stock trail), financial section in `admin/reports.php` (discounts, coupon usage, COGS
  estimate, margin)
- Phase 7 (website): `admin/banners.php` (hero slides with image upload + announcement
  bar + email log), `admin/cms.php` (pages CRUD at `/page/{slug}`), `admin/seo.php`
  (meta title/description per product/category/page, consumed by the storefront),
  transactional email via `includes/mailer.php` logged to `email_log`
- Phase 8: `docs/SECURITY-AUDIT.md` (live checks + findings), automated smoke tests
  (`php tests\smoke.php` — 36 assertions), perf indexes + load-tested
  (523–677 req/s local), backup/restore scripts (`scripts/backup.ps1`,
  `scripts/restore.ps1`, round-trip verified)
- Admin & CX refresh: self-service admin profile (`/admin/profile`), admin user
  management (`/admin/users`), full activity log (`/admin/activity`), public order
  tracking (`/track`), champagne-gold luxury storefront theme, shared RBAC map
  (`includes/rbac.php`) and two extra roles (Product Manager, Accountant)

## Run locally (XAMPP)

- URL (storefront): http://patchesandmotifs.test
- Admin login: http://patchesandmotifs.test/admin

Seed credentials (local dev only — rotate before any real deployment, `SECURITY.md` §36):
see `.env` keys `ADMIN_EMAIL` / `ADMIN_PASSWORD`. The seeded account is
`admin@patchesandmotifs.test`.

## Useful commands

```bash
# Rebuild Tailwind after editing resources/tailwind/input.css or any template classes
npm run build:css        # production (minified)
npm run watch:css        # during development

# Apply schema (migration user = root; app user pm_app has no DDL rights, SECURITY.md §28)
C:\xampp\mysql\bin\mysql.exe -u root patches_motifs -e "SOURCE database/schema.sql;"

# Seed roles/permissions/Super Admin
php database/seed.php

# New migration placeholder files go in database/migrations/
# Applied so far: schema.sql, 002 (seed), 003 (phases 6-8), 004 (SEO + seed),
#                  005 (returns + PostEx), 006 (Google login), 007 (admin roles),
#                  008 (custom made-to-order sizes)

# Automated smoke tests (36 assertions; safe to re-run)
php tests\smoke.php

# Backup / restore (Phase 8)
powershell -File scripts\backup.ps1 -Keep 14
powershell -File scripts\restore.ps1 -DumpFile storage\backups\db_XXXX.sql

# Security audit report: docs/SECURITY-AUDIT.md

# Regenerate demo imagery (hero slides, product photos, collection cover)
php database/make_placeholders.php
```

## Deploy with the web installer

Upload the project, point the web root at `public/`, then open:

```
https://your-domain.example/install.php
```

The installer checks PHP 8.1+, required extensions (`pdo_mysql`, `mbstring`, `fileinfo`,
`gd`, `curl`, `openssl`) and folder permissions. It then:

1. creates the database if needed (or reuses an empty one),
2. imports `database/schema.sql` and every `database/migrations/*.sql` in order,
3. seeds roles, permissions and your Super Admin account,
4. applies the store identity — name, contact email/phone, address and timezone — to
   `store_settings` (the storefront header/footer/titles and outgoing email branding
   read the name from here),
5. writes `.env` with the public origin (no subfolder — the app uses root-relative
   links), database credentials, timezone, session cookie policy and admin seed values.
   An existing `.env` is backed up as `.env.bak-<timestamp>`; `APP_KEY` and any keys
   the installer does not manage are preserved,
6. creates `storage/installed.lock`.

### Reconfigure a live site

Open `/install.php` again. The form is pre-filled from the current `.env` and database,
and submitting it changes everything in one pass: the public URL (Google redirects are
derived from it), database host/port/name/user/password, store identity, timezone and
Super Admin email/name/password. `SESSION_SECURE_COOKIE` follows the URL scheme.

- Reconfiguration requires a **current Super Admin email and password**, verified
  against the existing database before anything is modified (5 failures pauses attempts
  for 15 minutes).
- Leave the **database password blank** to keep the current one — it is never printed
  back into the page. Leave the **admin password blank** to keep the existing hash.
- **Apply this to the current primary Super Admin** renames the oldest active Super
  Admin when the email changes instead of creating a second account.
- Existing tables and data are kept by default. **Reset** is a separate, destructive
  opt-in that drops only the tables created by Patches&Motifs.

If the current database is unreachable (for example its MySQL password changed), delete
`storage/installed.lock` on the server, reload the page, enter the new credentials and
tick **Keep existing Patches&Motifs data**. The tables are not touched; `.env` and the
store settings are updated.

After a successful install or reconfiguration, **delete `public/install.php`** and keep
`storage/installed.lock` in place.

## Customer Google sign-in

1. In the [Google Cloud console](https://console.cloud.google.com/apis/credentials) create
   an **OAuth client ID → Web application**.
2. Add the authorised redirect URI: `{APP_URL}/account/google/callback`
   (for local dev: `http://patchesandmotifs.test/account/google/callback`).
3. In **Admin → Settings → Google Sign-In**, paste the Client ID and Client secret and save.
4. The "Continue with Google" button appears on the storefront login and register pages.
   Accounts are linked by verified email; Google-only accounts cannot use password login
   until they set one.

## Admin team, activity log & order tracking

- **My account** (`/admin/profile`) — every admin can update their own name/email and
  change their password. Changing a password requires the current one and signs the
  session in again with a fresh ID and CSRF token.
- **Users** (`/admin/users`, permission `users.manage`) — create admin accounts, set their
  role, reset passwords, disable or remove accounts. You cannot disable/delete yourself,
  and the last active Super Admin is protected. Roles come from `includes/rbac.php`:
  Super Admin, Store Manager, Product Manager, Order Manager, Accountant.
- **Activity log** (`/admin/activity`, permission `logs.view`) — tabbed audit trail of
  everything done in the admin panel (with before/after JSON, admin, IP, time) plus every
  admin sign-in attempt. Visits to the admin sign-in screen are recorded once per session
  per 10 minutes as `login_page_view`.
- **Track order** (`/track`) — customers enter their order number **and** the email or phone
  used at checkout, so no order details leak to someone who only guesses a number. Lookups
  are rate limited (10 failures per 15 minutes per IP) and failures are audited.

The storefront is styled with a champagne-gold accent (`gold`, `gold-deep`, `gold-soft`,
`champagne` tokens in `resources/tailwind/input.css`). The admin panel keeps the olive accent.

## CSV product import

**Admin → Products → Upload product via CSV** parses the file server-side and opens an
editable preview before anything is saved.

- One product per row; saving creates the product plus its first variant
  (size / length / color / price) and initial stock.
- Required columns: `name`, `sku`, `price`. Optional columns: `sale_price`,
  `category`, `status`, `size`, `length`, `color`, `stock`, `variant_sku`,
  `short_description`, `description`, `alt_text`. Header aliases such as `product_name`,
  `base_price`, `qty` and `colour` are accepted.
- Comma, semicolon and tab delimiters work; a UTF-8 BOM is ignored. Limits: 100 rows,
  2 MB.
- Status defaults to `draft` when blank. Categories are matched by name (or numeric ID);
  an unknown category imports the product without a category and shows a warning.
- Duplicate product or variant SKUs are flagged in the preview and skipped. Rows with
  errors stay in the preview so they can be corrected and saved again.
- Add one or more images per row (JPG/PNG/WebP, re-encoded, max 5 MB each); the first
  becomes the primary image. PHP's `max_file_uploads` (default 20) limits total files per
  save, so split very large image batches.
- **Sample CSV** downloads a ready-to-edit template.

## Category images, product sharing and custom sizes

- **Category images:** **Admin → Categories → Edit** accepts a JPG/PNG/WebP image
  (stored through the hardened upload pipeline). It appears on the homepage category
  tiles and as a banner on `/shop?category=…`. Replace or remove it from the same form.
- **Share buttons:** every product page offers native share / copy link plus WhatsApp,
  Facebook and X links. Product pages also emit Open Graph tags so shared links show a
  title, description and image preview.
- **Custom sizes:** made-to-order products show a size slider and a typed size. The
  slider moves in 0.5″ steps; typing accepts decimals. The allowed range is set in
  **Admin → Settings → Patch & motif sizing** (default 1–12 in, max > min). The chosen
  size is stored on the cart line and copied to the order line label; the selected
  preset still determines the colour and price. Every size is re-validated server-side.
- **Cart editing:** each cart line has a quantity box (saved when changed) and a Remove
  button. Both use the cart API with the usual CSRF protection, and the Remove button
  also works as a normal form POST when JavaScript is unavailable.

## Order printing

Open a single order's dispatch sheet from **Orders → Print**, or select several orders
and use **Print (A4)**. A single order prints on **A4 portrait**; bulk printing uses
**A4 landscape** with one sheet per order and no trailing blank page. Print styles are
external (`public/assets/css/print.css`, `print-single.css`) because the Content Security
Policy blocks inline styles.

## Web server

Apache vhost added at the end of `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
(web root = `public/` only; PHP execution disabled in `public/uploads/`).
`patchesandmotifs.test` resolves via the Windows hosts file.

phpMyAdmin stays available at http://localhost/phpmyadmin (a `localhost` vhost
was added for that).

## Layout

```
public/        web root: index.php (only entry), assets/, uploads/ (no PHP execution)
admin/         admin portal (login.php, dashboard.php, logout.php)
account/       customer portal (later phase)
modules/       business logic, incl. modules/store/pages home.php
includes/      bootstrap, env, config loader, security, csrf, database, ratelimit, auth
api/           JSON endpoints (later)
cron/          scheduled jobs (later)
storage/       logs, cache, private files — outside web root
config/        app.php — secrets via .env (never committed)
database/      schema.sql, seed.php, migrations/
resources/     tailwind sources, email/pdf templates
tests/         PHPUnit (later)
```

## Security ground rules (from SECURITY.md — enforced in every feature)

1. `declare(strict_types=1);` everywhere; never display errors to users.
2. PDO prepared statements only; whitelists for ORDER BY/columns; `(int)` LIMIT/OFFSET.
3. Escape all output with `e()`; JSON via `js()`.
4. CSRF token on every state-changing request; never change state on GET.
5. Validate every input server-side (type, length, range, allow-list).
6. Server computes all prices/stock/shipping — never trust the browser.
7. Ownership checks on every object ID (no IDOR).
8. `rand`/`mt_rand`/`uniqid` forbidden — `random_bytes()` only.
9. Uploads: MIME-by-content check, re-encode, random names, no-exec directory.
10. Log security events, never secrets.
