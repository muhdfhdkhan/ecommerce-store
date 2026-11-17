<?php
// database/seed.php â€” one-time seeder: roles, permissions, Super Admin account (CLI only).
// Usage: php database/seed.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$permissions = [
    'dashboard.view',
    'products.view', 'products.create', 'products.edit', 'products.delete',
    'categories.view', 'categories.edit',
    'collections.view', 'collections.edit',
    'inventory.view', 'inventory.adjust',
    'orders.view', 'orders.update_status', 'orders.refund',
    'payments.view', 'payments.verify',
    'customers.view', 'customers.edit', 'customers.block', 'customers.export',
    'shipping.manage',
    'promotions.manage',
    'cms.manage',
    'reports.view', 'reports.export',
    'users.manage',
    'settings.edit',
    'logs.view',
];

$pdo = db();
$pdo->beginTransaction();
try {
    // --- roles ---
    $st = $pdo->prepare('INSERT IGNORE INTO roles (name, description) VALUES (:name, :d)');
    $st->execute([':name' => SUPER_ADMIN_ROLE, ':d' => 'Full system access']);
    $st->execute([':name' => 'store_manager', ':d' => 'Products, orders, inventory, customers']);
    $st->execute([':name' => 'order_manager', ':d' => 'Orders, printing, shipping']);

    // --- permissions ---
    $st = $pdo->prepare('INSERT IGNORE INTO permissions (name) VALUES (:name)');
    foreach ($permissions as $perm) {
        $st->execute([':name' => $perm]);
    }

    // --- super_admin holds every permission ---
    $st = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id)
                         SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = :role');
    $st->execute([':role' => SUPER_ADMIN_ROLE]);

    // --- Super Admin account (password from .env, hashed with password_hash) ---
    $email = strtolower(trim((string)env('ADMIN_EMAIL', '')));
    $pass  = (string)env('ADMIN_PASSWORD', '');
    $name  = (string)env('ADMIN_NAME', 'Administrator');

    if ($email === '' || $pass === '') {
        fwrite(STDERR, "Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.\n");
        exit(1);
    }
    if (strlen($pass) < 12) {
        fwrite(STDERR, "ADMIN_PASSWORD must be at least 12 characters.\n");
        exit(1);
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $st = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, status)
         VALUES (:name, :email, :hash, :status)
         ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), status = VALUES(status), deleted_at = NULL'
    );
    $st->execute([':name' => $name, ':email' => $email, ':hash' => $hash, ':status' => 'active']);

    $st = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (user_id, role_id)
         SELECT u.id, r.id FROM users u, roles r WHERE u.email = :email AND r.name = :role'
    );
    $st->execute([':email' => $email, ':role' => SUPER_ADMIN_ROLE]);

    // --- store identity ---
    $st = $pdo->prepare(
        'INSERT INTO store_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $st->execute([':k' => 'store_name', ':v' => 'Patches&Motifs']);
    $st->execute([':k' => 'currency', ':v' => 'PKR']);
    $st->execute([':k' => 'timezone', ':v' => 'Asia/Karachi']);

    $pdo->commit();
    echo "Seed complete. Super Admin: {$email}\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed (details in storage/logs): ' . $e->getMessage() . "\n");
    app_log('error', 'seed failed: ' . $e->getMessage());
    exit(1);
}
