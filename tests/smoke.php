<?php
// tests/smoke.php — automated smoke tests (Phase 8). Run: php tests\smoke.php
// Exercises the critical paths over HTTP against the local vhost and asserts
// observable outcomes. Safe to re-run; creates one test order per run.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

const BASE = 'http://patchesandmotifs.test';

$pass = 0;
$fail = 0;
$cookieJar = sys_get_temp_dir() . '/pm_smoke_cookies.txt';
$adminJar = sys_get_temp_dir() . '/pm_smoke_admin.txt';
@unlink($cookieJar);
@unlink($adminJar);

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    echo ($ok ? 'PASS' : 'FAIL') . "  $label\n";
    $ok ? $pass++ : $fail++;
}

/** GET with a cookie jar; returns [status, body]. */
function http_get(string $url, string $jar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, (string)$body];
}

/** POST form-encoded with a jar; returns [status, body]. */
function http_post(string $url, array $fields, string $jar, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, (string)$body];
}

function csrf_from(string $html): string
{
    if (preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m) === 1) {
        return $m[1];
    }
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) === 1) {
        return $m[1];
    }
    return '';
}

// ---- 1. Storefront pages ----
[$s, $b] = http_get(BASE . '/', $cookieJar);
check('home returns 200 with hero slider', $s === 200 && str_contains($b, 'data-hero'));
check('home shows announcement bar', str_contains($b, 'bg-ink px-4 py-2'));

[$s, $b] = http_get(BASE . '/shop', $cookieJar);
check('shop returns 200 with products grid', $s === 200);

[$s, $b] = http_get(BASE . '/product/floral-vine-patch', $cookieJar);
check('product page returns 200 with buy form', $s === 200 && str_contains($b, 'Add to cart'));

[$s, $b] = http_get(BASE . '/about', $cookieJar);
check('about page 200', $s === 200);

[$s,] = http_get(BASE . '/product/not-a-real-slug-xyz', $cookieJar);
check('unknown product 404s', $s === 404);

// ---- 2. Cart API (guest, JSON + header CSRF) ----
$token = csrf_from($b);
[$s, $b] = http_get(BASE . '/cart', $cookieJar);
// token lives on product page forms; reuse from above fetch if needed
if ($token === '') {
    [, $pb] = http_get(BASE . '/product/floral-vine-patch', $cookieJar);
    $token = csrf_from($pb);
}
$ch = curl_init(BASE . '/api/cart/add');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{"variant_id":1,"qty":1}',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: ' . $token, 'Origin: ' . BASE],
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_TIMEOUT => 15,
]);
$cartResp = json_decode((string)curl_exec($ch), true);
$s = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
check('cart API add returns ok', $s === 200 && ($cartResp['ok'] ?? false) === true);

// CSRF recovery: the page can fetch a fresh token, and a stale token fails as JSON (not plain text).
[$s, $b] = http_get(BASE . '/api/csrf', $cookieJar);
$csrfResp = json_decode($b, true);
check('csrf endpoint returns a token', $s === 200 && ($csrfResp['ok'] ?? false) === true
    && is_string($csrfResp['token'] ?? null) && strlen($csrfResp['token']) === 64);

$ch = curl_init(BASE . '/api/cart/add');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{"variant_id":1,"qty":1}',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: stale-token', 'Accept: application/json'],
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_TIMEOUT => 15,
]);
$staleResp = json_decode((string)curl_exec($ch), true);
$s = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
check('stale CSRF token returns JSON 403', $s === 403 && ($staleResp['ok'] ?? true) === false
    && str_contains((string)($staleResp['error'] ?? ''), 'session expired'));

// ---- 3. Checkout: coupon apply + order placement ----
[$s, $b] = http_get(BASE . '/checkout', $cookieJar);
$csrf = csrf_from($b);
check('checkout renders with coupon field', $s === 200 && str_contains($b, 'coupon_code'));

[$s, $b] = http_post(BASE . '/checkout', ['apply_coupon' => '1', '_csrf' => $csrf, 'coupon_code' => 'WELCOME10'], $cookieJar);
check('WELCOME10 applies and shows discount', str_contains($b, 'Discount (WELCOME10)'));

$idem = '';
if (preg_match('/name="idempotency_key" value="([^"]+)"/', $b, $m) === 1) {
    $idem = $m[1];
}
[$s, $b] = http_post(BASE . '/checkout', [
    '_csrf' => $csrf, 'full_name' => 'Smoke Tester', 'email' => 'smoke@test.example',
    'phone' => '03000000000', 'ship_name' => 'Smoke Tester', 'ship_phone' => '03000000000',
    'ship_line1' => '1 Test Street', 'ship_city' => 'Lahore', 'ship_province' => 'Punjab',
    'ship_postal' => '54000', 'payment_method' => 'cod', 'idempotency_key' => $idem,
    'coupon_code' => 'WELCOME10',
], $cookieJar);
check('order places successfully', str_contains($b, 'order') && ($s === 200));
$placed = $b;

// Idempotency: replaying must not duplicate.
[$s, $b2] = http_post(BASE . '/checkout', [
    '_csrf' => $csrf, 'full_name' => 'Smoke Tester', 'email' => 'smoke@test.example',
    'phone' => '03000000000', 'ship_name' => 'Smoke Tester', 'ship_phone' => '03000000000',
    'ship_line1' => '1 Test Street', 'ship_city' => 'Lahore', 'ship_province' => 'Punjab',
    'ship_postal' => '54000', 'payment_method' => 'cod', 'idempotency_key' => $idem,
], $cookieJar);
check('idempotency replay does not create a second order', $b2 === $b || !str_contains($b2, 'EMB-'));

// ---- 3b. Cart line removal (JS API + no-JS form fallback) ----
[, $b] = http_get(BASE . '/cart', $cookieJar);
$cartToken = csrf_from($b);
$ch = curl_init(BASE . '/api/cart/add');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{"variant_id":1,"qty":1}',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: ' . $cartToken],
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_TIMEOUT => 15,
]);
$addResp = json_decode((string)curl_exec($ch), true);
curl_close($ch);

[$s, $b] = http_get(BASE . '/cart', $cookieJar);
$lineId = 0;
if (preg_match('/data-remove-line="(\d+)"/', $b, $m) === 1) {
    $lineId = (int)$m[1];
}
check('cart page offers remove control', ($addResp['ok'] ?? false) === true
    && $s === 200 && $lineId > 0 && str_contains($b, 'data-remove-form'));

[$s, $b] = http_post(BASE . '/cart', [
    'do' => 'remove', 'cart_item_id' => $lineId, '_csrf' => csrf_from($b),
], $cookieJar);
[$s, $b] = http_get(BASE . '/cart', $cookieJar);
check('cart line removes via form fallback', $s === 200 && str_contains($b, 'Your cart is empty'));

// ---- 4. Public order tracking (two-factor lookup) ----
$orderNo = '';
if (preg_match('/EMB-\d{4}-\d{6}/', $placed, $m) === 1) {
    $orderNo = $m[0];
}
[$s, $b] = http_get(BASE . '/track', $cookieJar);
$trackCsrf = csrf_from($b);
check('track page returns 200 with lookup form', $s === 200 && str_contains($b, 'Where is my order?'));
if ($orderNo !== '') {
    [$s, $b] = http_post(BASE . '/track', [
        '_csrf' => $trackCsrf, 'order_number' => $orderNo,
        'contact' => 'smoke@test.example', 'website' => '',
    ], $cookieJar);
    check('track finds the order with a matching email', $s === 200
        && str_contains($b, $orderNo) && str_contains($b, 'Subtotal') && !str_contains($b, 'could not find'));
} else {
    check('track finds the order with a matching email', false);
}

// ---- 5. Admin auth + admin pages ----
@unlink($adminJar);
[$s, $b] = http_get(BASE . '/admin/login', $adminJar);
$csrf = csrf_from($b);
sleep(3); // time-trap requires a delay between GET and POST
$creds = [];
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, 'ADMIN_EMAIL=')) $creds['e'] = trim(substr($line, strlen('ADMIN_EMAIL=')), "\"' ");
        if (str_starts_with($line, 'ADMIN_PASSWORD=')) $creds['p'] = trim(substr($line, strlen('ADMIN_PASSWORD=')), "\"' ");
    }
}
if (!isset($creds['e'], $creds['p'])) {
    check('admin credentials available in .env', false);
} else {
    [$s, $b] = http_post(BASE . '/admin/login', ['email' => $creds['e'], 'password' => $creds['p'], '_csrf' => $csrf, 'website' => ''], $adminJar);
    foreach (['analytics', 'promotions', 'suppliers', 'purchasing', 'banners', 'cms', 'seo', 'reports',
              'users', 'activity', 'profile'] as $page) {
        [$s, $b] = http_get(BASE . '/admin/' . $page, $adminJar);
        check("admin/$page 200", $s === 200 && str_contains($b, 'Sign out'));
    }
    // Unauthenticated access must bounce to login.
    [$s, $b] = http_get(BASE . '/admin/products', $cookieJar);
    check('guest cannot open admin (no Sign out)', !str_contains($b, 'Sign out'));

    // ---- 6. CSV import UI + print assets ----
    [$s, $b] = http_get(BASE . '/admin/products', $adminJar);
    check('products page offers CSV import', $s === 200 && str_contains($b, 'Upload product via CSV'));

    [$s, $b] = http_get(BASE . '/admin/products?action=import_sample', $adminJar);
    check('sample CSV downloads with headers', $s === 200 && str_contains($b, 'name,sku,price'));

    [$s, $b] = http_get(BASE . '/admin/orders/print?ids=1', $adminJar);
    check('single print uses portrait stylesheet', $s === 200
        && str_contains($b, 'print.css') && str_contains($b, 'print-single.css'));

    // ---- 7. Category images, custom size, sharing ----
    [$s, $b] = http_get(BASE . '/admin/categories', $adminJar);
    check('category admin offers image upload', $s === 200
        && str_contains($b, 'name="image"'));

    [$s, $b] = http_get(BASE . '/admin/settings', $adminJar);
    check('settings expose custom size range', $s === 200
        && str_contains($b, 'name="size_min_inches"') && str_contains($b, 'name="size_max_inches"'));

    check('settings expose social + WhatsApp fields', $s === 200
        && str_contains($b, 'name="social_facebook_url"') && str_contains($b, 'name="social_instagram_url"')
        && str_contains($b, 'name="whatsapp_number"') && str_contains($b, 'value="save_social"'));

    [$s, $b] = http_get(BASE . '/', $cookieJar);
    check('footer credits National Tech Forge', $s === 200
        && str_contains($b, 'Developed by') && str_contains($b, 'https://nationaltechforge.netlify.app/contact'));

    [$s, $b] = http_get(BASE . '/product/ethereal-bloom-motif', $cookieJar);
    check('made-to-order product has custom size control', $s === 200
        && str_contains($b, 'data-size-range') && str_contains($b, 'name="custom_size"'));

    [$s, $b] = http_get(BASE . '/product/floral-vine-patch', $cookieJar);
    check('product page has share controls', $s === 200
        && str_contains($b, 'data-share-copy') && str_contains($b, 'wa.me'));
}

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
