# Patches&Motifs — Embroidery E-Commerce & Management System

Store: **Patches&Motifs** · Stack: Core PHP 8.x (XAMPP ships 8.5), MySQL/MariaDB, Tailwind CSS, Vanilla JS, PDO.

Docs that govern this codebase: `SECURITY.md` (mandatory on every feature), `DESIGN.md`, `PROJECT_MVP.md`, `PROJECT_BRIEF.md`, `COMPLETE_PROJECT_BRIEF.md`.

## Status

Phase 1 (Foundation) started:

- Folder structure per `PROJECT_BRIEF.md` §25 — only `public/` is web-exposed
- Secure admin authentication: CSRF, rate limiting, timing-safe password verify, session hardening, audit log (`SECURITY.md` §7–§11, §29)
- Admin dashboard shell with "Welcome, Administrator"
- Tailwind design tokens from `DESIGN.md` §3–§5, built & self-hosted (no CDN)

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
