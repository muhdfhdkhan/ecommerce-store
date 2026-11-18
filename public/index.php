<?php
// public/index.php â€” the ONLY web entry point. Everything else is loaded from
// outside the web root (SECURITY.md Â§3.1). Routing is a strict allow-list â€”
// user input is never used to build an include path (SECURITY.md Â§13.2).
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

/** @var string $CSP_NONCE set by bootstrap */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// ---------------- Route table (allow-list) ----------------
// value: [file relative to BASE_PATH, visibility]
// visibility 'public' | 'private' (private pages get noindex + no-cache headers)

$routes = [
    // Storefront
    '/'              => ['modules/store/pages/home.php',        'public'],
    '/shop'          => ['modules/store/pages/shop.php',        'public'],
    '/cart'          => ['modules/store/pages/cart.php',        'private'], // non-JS fallback for POST /cart/add too
    '/cart/add'      => ['modules/store/pages/cart.php',        'private'],
    '/checkout'      => ['modules/store/pages/checkout.php',    'private'],
    '/account'       => ['modules/store/pages/account.php',     'private'],
    '/collections'   => ['modules/store/pages/collections.php', 'public'],
    '/api/cart'      => ['modules/store/api/cart.php',          'private'],

    // Admin (each page enforces its own auth via includes/admin_guard.php)
    '/admin'               => ['admin/login.php',      'private'],
    '/admin/login'         => ['admin/login.php',      'private'],
    '/admin/logout'        => ['admin/logout.php',     'private'],
    '/admin/dashboard'     => ['admin/dashboard.php',  'private'],
    '/admin/products'      => ['admin/products.php',   'private'],
    '/admin/orders'        => ['admin/orders.php',     'private'],
    '/admin/categories'    => ['admin/categories.php', 'private'],
    '/admin/collections'   => ['admin/collections.php','private'],
    '/admin/inventory'     => ['admin/inventory.php',  'private'],
    '/admin/customers'     => ['admin/customers.php',  'private'],
    '/admin/payments'      => ['admin/payments.php',   'private'],
    '/admin/reports'       => ['admin/reports.php',    'private'],
    '/admin/settings'      => ['admin/settings.php',   'private'],
    '/admin/shipping'      => ['admin/shipping.php',   'private'],
    // Phase 6 — business management
    '/admin/analytics'     => ['admin/analytics.php',  'private'],
    '/admin/promotions'    => ['admin/promotions.php', 'private'],
    '/admin/suppliers'     => ['admin/suppliers.php',  'private'],
    '/admin/purchasing'    => ['admin/purchasing.php', 'private'],
    // Phase 7 — website management
    '/admin/banners'       => ['admin/banners.php',    'private'],
    '/admin/cms'           => ['admin/cms.php',        'private'],
    '/admin/seo'           => ['admin/seo.php',        'private'],
];

$route = $routes[$path] ?? null;

// ---- Parameterised routes (slug extracted server-side, strict charset) ----
if ($route === null) {
    if (preg_match('~^/api/cart/(add|update|remove)$~', $path, $m) === 1) {
        $_GET['action'] = $m[1];
        $route = ['modules/store/api/cart.php', 'private'];
    } elseif (preg_match('~^/product/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $path, $m) === 1) {
        $_GET['slug'] = $m[1];
        $route = ['modules/store/pages/product.php', 'public'];
    } elseif (preg_match('~^/page/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $path, $m) === 1) {
        $_GET['cms'] = $m[1];
        $route = ['modules/store/pages/info.php', 'public'];
    } elseif ($path === '/about') {
        $_GET['which'] = 'about';
        $route = ['modules/store/pages/info.php', 'public'];
    } elseif ($path === '/contact') {
        $_GET['which'] = 'contact';
        $route = ['modules/store/pages/info.php', 'public'];
    }
}

if ($route === null) {
    abort(404);
}

[$file, $visibility] = $route;

if ($visibility === 'private') {
    header('X-Robots-Tag: noindex, nofollow');
    send_no_cache_headers();
}

require BASE_PATH . '/' . $file;
