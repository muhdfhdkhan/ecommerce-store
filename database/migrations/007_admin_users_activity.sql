-- Patches&Motifs - 007: admin user management, activity log, extra roles.
--
-- 1) Permissions referenced by the admin navigation were missing from the seed
--    list (suppliers / purchasing / SEO). Add them idempotently.
-- 2) Add the two roles that the permission map already knew about.
-- 3) Populate role_permissions so the database matches the role map used by
--    includes/admin_guard.php (super_admin keeps the wildcard).

INSERT IGNORE INTO permissions (name) VALUES
    ('suppliers.manage'),
    ('purchasing.manage'),
    ('seo.manage');

INSERT IGNORE INTO roles (name, description) VALUES
    ('product_manager', 'Catalog, inventory, suppliers and purchasing'),
    ('accountant',      'Payments, reports and order visibility');

-- super_admin holds every permission (the app treats it as a wildcard).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
 WHERE r.name = 'super_admin';

-- store_manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
 WHERE r.name = 'store_manager' AND p.name IN (
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
    'cms.manage', 'seo.manage'
 );

-- order_manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
 WHERE r.name = 'order_manager' AND p.name IN (
    'dashboard.view',
    'orders.view', 'orders.update_status',
    'payments.view', 'shipping.manage'
 );

-- product_manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
 WHERE r.name = 'product_manager' AND p.name IN (
    'dashboard.view',
    'products.view', 'products.edit',
    'categories.view', 'categories.edit',
    'collections.view', 'collections.edit',
    'inventory.view', 'inventory.adjust',
    'suppliers.manage', 'purchasing.manage'
 );

-- accountant
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
 WHERE r.name = 'accountant' AND p.name IN (
    'dashboard.view',
    'payments.view', 'payments.verify',
    'orders.view',
    'reports.view', 'reports.export'
 );
