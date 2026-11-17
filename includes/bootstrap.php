<?php
// includes/bootstrap.php — runs on every request (SECURITY.md §3.3)
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CONFIG_PATH', BASE_PATH . '/config');

error_reporting(E_ALL);
ini_set('display_errors', '0');          // never show errors to visitors
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

require BASE_PATH . '/includes/env.php';
env_load(BASE_PATH . '/.env');

date_default_timezone_set('UTC');        // store UTC, display in Asia/Karachi

// Generic failure page — no details, no stack traces (SECURITY.md §3.3, §4).
set_exception_handler(function (Throwable $e) {
    app_log('error', sprintf(
        'Uncaught %s: %s @ %s:%d',
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
        . '<p style="font-family:sans-serif;padding:2rem">Something went wrong. Please try again later.</p>';
    exit;
});

require BASE_PATH . '/includes/helpers.php';
require BASE_PATH . '/includes/security.php';
require BASE_PATH . '/includes/csrf.php';
require BASE_PATH . '/includes/database.php';
require BASE_PATH . '/includes/ratelimit.php';
require BASE_PATH . '/includes/auth.php';

start_secure_session();

// Per-request CSP nonce (available to templates as $CSP_NONCE).
$CSP_NONCE = base64_encode(random_bytes(16));
send_security_headers($CSP_NONCE);
