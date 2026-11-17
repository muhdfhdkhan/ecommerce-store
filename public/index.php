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
    '/'                => ['modules/store/pages/home.php',    'public'],
    '/admin'           => ['admin/login.php',                 'private'],
    '/admin/login'     => ['admin/login.php',                 'private'],
    '/admin/dashboard' => ['admin/dashboard.php',             'private'],
    '/admin/logout'    => ['admin/logout.php',                'private'],
];

$route = $routes[$path] ?? null;

if ($route === null) {
    abort(404);
}

[$file, $visibility] = $route;

if ($visibility === 'private') {
    header('X-Robots-Tag: noindex, nofollow');
    send_no_cache_headers();
}

require BASE_PATH . '/' . $file;
