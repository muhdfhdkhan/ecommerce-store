<?php
// database/seed_core.php — shared seeding used by database/seed.php and public/install.php.
// Keeps roles, permissions, the Super Admin account and core settings in one place.
declare(strict_types=1);

if (!defined('SUPER_ADMIN_ROLE')) {
    define('SUPER_ADMIN_ROLE', 'super_admin');
}

/** Permissions granted to the Super Admin role. */
function seed_permission_list(): array
{
    return [
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
}

/**
 * Seed roles, permissions, the Super Admin user and store defaults.
 * Idempotent: safe to run against an existing database. Caller owns the transaction.
 */
function seed_database(PDO $pdo, string $adminEmail, string $adminPassword, string $adminName = 'Administrator'): void
{
    $adminEmail = strtolower(trim($adminEmail));
    if ($adminEmail === '' || $adminPassword === '') {
        throw new RuntimeException('Admin email and password are required.');
    }
    if (strlen($adminPassword) < 12) {
        throw new RuntimeException('Admin password must be at least 12 characters.');
    }

    // --- roles ---
    $st = $pdo->prepare('INSERT IGNORE INTO roles (name, description) VALUES (:name, :d)');
    $st->execute([':name' => SUPER_ADMIN_ROLE, ':d' => 'Full system access']);
    $st->execute([':name' => 'store_manager', ':d' => 'Products, orders, inventory, customers']);
    $st->execute([':name' => 'order_manager', ':d' => 'Orders, printing, shipping']);

    // --- permissions ---
    $st = $pdo->prepare('INSERT IGNORE INTO permissions (name) VALUES (:name)');
    foreach (seed_permission_list() as $perm) {
        $st->execute([':name' => $perm]);
    }

    // --- super_admin holds every permission ---
    $st = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id)
                         SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = :role');
    $st->execute([':role' => SUPER_ADMIN_ROLE]);

    // --- Super Admin account ---
    $hash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $st = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, status)
         VALUES (:name, :email, :hash, :status)
         ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), status = VALUES(status), deleted_at = NULL'
    );
    $st->execute([':name' => $adminName, ':email' => $adminEmail, ':hash' => $hash, ':status' => 'active']);

    $st = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (user_id, role_id)
         SELECT u.id, r.id FROM users u, roles r WHERE u.email = :email AND r.name = :role'
    );
    $st->execute([':email' => $adminEmail, ':role' => SUPER_ADMIN_ROLE]);

    // --- store identity ---
    $st = $pdo->prepare(
        'INSERT INTO store_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $st->execute([':k' => 'store_name', ':v' => 'Patches&Motifs']);
    $st->execute([':k' => 'currency', ':v' => 'PKR']);
    $st->execute([':k' => 'timezone', ':v' => 'Asia/Karachi']);
}
