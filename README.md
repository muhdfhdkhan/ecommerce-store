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
  (`php tests\smoke.php` — 20 assertions), perf indexes + load-tested
  (523–677 req/s local), backup/restore scripts (`scripts/backup.ps1`,
  `scripts/restore.ps1`, round-trip verified)

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
# Applied so far: schema.sql, 002 (seed), 003 (phases 6-8), 004 (SEO + seed)

# Automated smoke tests (20 assertions; safe to re-run)
php tests\smoke.php

# Backup / restore (Phase 8)
powershell -File scripts\backup.ps1 -Keep 14
powershell -File scripts\restore.ps1 -DumpFile storage\backups\db_XXXX.sql

# Security audit report: docs/SECURITY-AUDIT.md

# Regenerate demo imagery (hero slides, product photos, collection cover)
php database/make_placeholders.php
```

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
