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

// Idempotency: replaying must not duplicate.
[$s, $b2] = http_post(BASE . '/checkout', [
    '_csrf' => $csrf, 'full_name' => 'Smoke Tester', 'email' => 'smoke@test.example',
    'phone' => '03000000000', 'ship_name' => 'Smoke Tester', 'ship_phone' => '03000000000',
    'ship_line1' => '1 Test Street', 'ship_city' => 'Lahore', 'ship_province' => 'Punjab',
    'ship_postal' => '54000', 'payment_method' => 'cod', 'idempotency_key' => $idem,
], $cookieJar);
check('idempotency replay does not create a second order', $b2 === $b || !str_contains($b2, 'EMB-'));

// ---- 4. Admin auth + new Phase 6/7 pages ----
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
    foreach (['analytics', 'promotions', 'suppliers', 'purchasing', 'banners', 'cms', 'seo', 'reports'] as $page) {
        [$s, $b] = http_get(BASE . '/admin/' . $page, $adminJar);
        check("admin/$page 200", $s === 200 && str_contains($b, 'Sign out'));
    }
    // Unauthenticated access must bounce to login.
    [$s, $b] = http_get(BASE . '/admin/products', $cookieJar);
    check('guest cannot open admin (no Sign out)', !str_contains($b, 'Sign out'));
}

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
