<?php
// config/app.php — application configuration (SECURITY.md §3.2).
// Lives OUTSIDE the web root. Secrets come from the .env file only.
declare(strict_types=1);

return [
    'env'      => env('APP_ENV') ?? 'production',
    'debug'    => (env('APP_ENV') ?? 'production') === 'local' && (env('APP_DEBUG') ?? 'false') === 'true',
    'url'      => env('APP_URL') ?? 'http://localhost',
    'timezone' => env('APP_TIMEZONE') ?? 'Asia/Karachi',
    'app_key'  => env('APP_KEY') ?? '',

    'db' => [
        'host' => env('DB_HOST') ?? '127.0.0.1',
        'port' => env('DB_PORT') ?? '3306',
        'name' => env('DB_NAME') ?? '',
        'user' => env('DB_USER') ?? '',
        'pass' => env('DB_PASS') ?? '',
    ],

    'session' => [
        // '__Host-' prefix requires Secure cookie; on local HTTP XAMPP we use a plain name.
        'name'          => (env('SESSION_SECURE_COOKIE') ?? '0') === '1' ? '__Host-PMID' : 'PMID',
        'secure_cookie' => (env('SESSION_SECURE_COOKIE') ?? '0') === '1',
        'admin_idle'    => 1800,   // 30 minutes inactivity (SECURITY.md §8.3)
        'admin_absolut' => 8 * 3600, // 8 hours absolute cap
    ],
];
