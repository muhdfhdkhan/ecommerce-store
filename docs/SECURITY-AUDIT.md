# Security Audit — Patches&Motifs (Phase 8)

**Date:** 2026-11-18 · **Scope:** Full stack (Core PHP 8.x, Apache 2.4, MariaDB) · **Baseline:** SECURITY.md

## 1. Automated test suite

`php tests\smoke.php` — 36 assertions covering storefront pages, order tracking, cart API (CSRF header
+ stale-token recovery), cart line removal, checkout with coupon, order idempotency, admin auth gate,
all Phase 6/7 admin pages, the CSV import UI/sample download, the single-order print stylesheet,
category image upload, custom size settings, made-to-order size controls, and product share buttons.
**Result: 36 passed, 0 failed** (latest run 2026-11-23).

## 2. Entry point & routing

| Check | Result |
| --- | --- |
| Single public entry point (`public/index.php`); app code outside web root | ✅ |
| Route table is a strict allow-list; slug params validated with regex `^[a-z0-9-]+$` | ✅ |
| `/includes/bootstrap.php`, `/config/app.php` unreachable (404) | ✅ |
| `/.env` blocked (403) by Apache config | ✅ |
| No user input ever builds an include path | ✅ |

## 3. Headers (verified live on `/`)

`Content-Security-Policy` (default-src 'self', no inline script; nonces for JSON islands),
`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`,
`Permissions-Policy`, COOP/CORP, `HttpOnly + SameSite=Lax` session cookie. Private pages
send `X-Robots-Tag: noindex` + no-cache. ✅ all present.

## 4. Input handling & injection

- **SQL:** 100% PDO prepared statements; no string concatenation into SQL (grep-verified).
- **XSS:** `e()` (htmlspecialchars) on every echoed value; CMS content stored as plain
  text and escaped per-paragraph — no raw HTML from DB.
- **CSRF:** every POST verified (`csrf_verify`); JSON endpoints use
  `csrf_verify_header_only` + Origin check. Failures return **403** (419 broken on this
  Apache/PHP 8.5 stack — do not reintroduce).
- **Uploads:** finfo real-mime check (JPEG/PNG/WebP ≤ 5 MB), GD re-encode to JPEG
  (strips payloads/EXIF), random 24-hex filename, 10–6000 px dimension cap,
  max 8 files per request, `.htaccess` denies script execution under `public/uploads`.
- **Path traversal:** `image_upload_delete()` guards with `realpath` prefix check.

## 5. Auth & sessions

- Admin login: rate-limited (login_attempts), honeypot field, time-trap, CSRF, session
  rotation on privilege change, permissions via role map (`admin_require_permission`).
- Password hashing: `password_hash` bcrypt/argon2id (PHP default).
- Every state-changing admin action is audited (`audit()` → activity_logs).

## 6. Business logic

- Totals computed **server-side only**; client-supplied prices never trusted.
- `place_order`: single transaction, `FOR UPDATE` row locks, stock re-verification,
  coupon validation + `used_count` increment inside the transaction, idempotency key
  (verified by smoke test), cart cleared post-commit.
- PO receiving: row-locked inventory increment + `stock_movements` trail.
- Order-number sequence fixed this phase: `SUBSTRING(order_number, 10)` + `CAST AS
  UNSIGNED` (was starting at the hyphen, producing `000000`).

## 7. Performance (Phase 8)

- Indexes added in `003_phases_6_8.sql`: `order_items(variant_id)`, `cart_items(variant_id)`,
  `order_status_history(to_status)` plus existing catalog/order indexes.
- Load test (Apache `ab`, local XAMPP): **523 req/s** on `/` (500 req, c=25, 0 failures),
  **677 req/s** on `/shop` (300 req, c=20, 0 failures).
- Tailwind CSS built & minified; images lazy-loaded below the fold; hero first frame
  `fetchpriority="high"`.

## 8. Backup & recovery (Phase 8)

- `scripts/backup.ps1`: `mysqldump --single-transaction` + uploads + logs zipped to
  `storage/backups/backup_YYYY-MM-DD_HHmmss.zip`, retention default 14.
- `scripts/restore.ps1`: replays a dump into the DB.
- Round-trip verified 2026-11-18: restored dump into scratch DB `pm_restore_test`,
  row counts matched (orders=3, banners=3, purchase_order_items=2), scratch DB dropped.

## 9. Recommendations (pre-production)

1. Move DB credentials out of `.env` root-readable defaults; use a dedicated dump user
   with SELECT/LOCK TABLES only.
2. Replace `@mail()` fallback with SMTP (phpmailer) once a relay exists; `email_log`
   already records every attempt for replay.
3. Schedule `scripts/backup.ps1` via Windows Task Scheduler (daily 03:00) and copy
   artifacts off-machine.
4. Enable `opcache` in php.ini production template (`opcache.enable=1`,
   `opcache.validate_timestamps=0` with deploy-time restart).
5. Serve uploads from a CDN/different origin when moving to production; keep the
   script-deny `.htaccess` regardless.
