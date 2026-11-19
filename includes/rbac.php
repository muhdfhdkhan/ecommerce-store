<?php
// includes/rbac.php — built-in role definitions (single source of truth).
// Used by includes/admin_guard.php at request time and database/seed_core.php at
// install time. super_admin is a wildcard and is handled by the caller.
declare(strict_types=1);

/** role name => list of granted permissions. */
function role_permission_map(): array
{
    return [
        'super_admin' => ['*'],
        'store_manager' => [
            'dashboard.view',
            'products.view', 'products.edit',
            'categories.view', 'categories.edit',
            'collections.view', 'collections.edit',
            'inventory.view', 'inventory.adjust',
            'orders.view', 'orders.update_status',
            'payments.view',
            'customers.view', 'customers.edit', 'customers.block',
            'reports.view', 'logs.view',
            'promotions.manage', 'suppliers.manage', 'purchasing.manage',
            'cms.manage', 'seo.manage',
        ],
        'order_manager' => [
            'dashboard.view',
            'orders.view', 'orders.update_status',
            'payments.view', 'shipping.manage',
        ],
        'product_manager' => [
            'dashboard.view',
            'products.view', 'products.edit',
            'categories.view', 'categories.edit',
            'collections.view', 'collections.edit',
            'inventory.view', 'inventory.adjust',
            'suppliers.manage', 'purchasing.manage',
        ],
        'accountant' => [
            'dashboard.view',
            'payments.view', 'payments.verify',
            'orders.view',
            'reports.view', 'reports.export',
        ],
    ];
}

/** Human labels for role pickers. */
function role_label(string $role): string
{
    return [
        'super_admin'     => 'Super Admin',
        'store_manager'   => 'Store Manager',
        'order_manager'   => 'Order Manager',
        'product_manager' => 'Product Manager',
        'accountant'      => 'Accountant',
    ][$role] ?? ucfirst(str_replace('_', ' ', $role));
}
