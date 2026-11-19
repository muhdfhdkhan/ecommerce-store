<?php
// database/seed.php — one-time seeder: roles, permissions, Super Admin account (CLI only).
// Usage: php database/seed.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once __DIR__ . '/seed_core.php';

$email = strtolower(trim((string)env('ADMIN_EMAIL', '')));
$pass  = (string)env('ADMIN_PASSWORD', '');
$name  = (string)env('ADMIN_NAME', 'Administrator');

if ($email === '' || $pass === '') {
    fwrite(STDERR, "Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.\n");
    exit(1);
}

$pdo = db();
$pdo->beginTransaction();
try {
    seed_database($pdo, $email, $pass, $name);
    $pdo->commit();
    echo "Seed complete. Super Admin: {$email}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed (details in storage/logs): ' . $e->getMessage() . "\n");
    app_log('error', 'seed failed: ' . $e->getMessage());
    exit(1);
}
