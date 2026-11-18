<?php
// admin/suppliers.php — supplier directory (Phase 6: suppliers & purchasing).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'suppliers.manage');

    $do = (string)($_POST['do'] ?? '');
    $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 120));

    if ($do === 'create' && $name !== '') {
        db()->prepare(
            'INSERT INTO suppliers (name, contact_name, phone, email, address, notes)
             VALUES (:n, :cn, :p, :e, :a, :no)'
        )->execute([
            ':n' => $name,
            ':cn' => mb_substr(trim((string)($_POST['contact_name'] ?? '')), 0, 120) ?: null,
            ':p' => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 24) ?: null,
            ':e' => filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? strtolower(trim((string)$_POST['email'])) : null,
            ':a' => mb_substr(trim((string)($_POST['address'] ?? '')), 0, 500) ?: null,
            ':no' => mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500) ?: null,
        ]);
        audit('supplier_created', 'supplier', (int)db()->lastInsertId(), null, ['name' => $name]);
        $_SESSION['flash_success'] = 'Supplier added.';
    }

    if ($do === 'toggle') {
        $sid = int_in($_POST['supplier_id'] ?? null);
        if ($sid) {
            db()->prepare('UPDATE suppliers SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $sid]);
            audit('supplier_toggled', 'supplier', $sid);
            $_SESSION['flash_success'] = 'Supplier updated.';
        }
    }
    safe_redirect('/admin/suppliers');
}

admin_layout('Suppliers', function () use ($ADMIN) {
    $canManage = admin_user_can($ADMIN, 'suppliers.manage');
    $rows = db()->query(
        'SELECT s.*, (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS po_count
         FROM suppliers s ORDER BY s.id DESC LIMIT 100'
    )->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Suppliers</h1>
    <p class="mt-1 text-sm text-muted-ink">Thread, backing and packaging vendors. Purchase orders live under Purchasing.</p>

    <div class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <div class="divide-y divide-line">
            <?php foreach ($rows as $s): ?>
            <div class="flex flex-wrap items-center gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold"><?= e($s['name']) ?></p>
                    <p class="truncate text-xs text-muted-ink">
                        <?= e($s['contact_name'] ?: '') ?><?= $s['phone'] ? ' · ' . e($s['phone']) : '' ?><?= $s['email'] ? ' · ' . e($s['email']) : '' ?>
                    </p>
                </div>
                <span class="text-xs text-muted-ink tabular-nums"><?= (int)$s['po_count'] ?> POs</span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $s['is_active'] ? 'bg-success/10 text-success' : 'bg-line text-muted-ink' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span>
                <?php if ($canManage): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="toggle">
                    <input type="hidden" name="supplier_id" value="<?= (int)$s['id'] ?>">
                    <button class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas"><?= $s['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$rows): ?><p class="p-8 text-center text-sm text-muted-ink">No suppliers yet.</p><?php endif; ?>
        </div>
    </div>

    <?php if ($canManage): ?>
    <form method="post" class="mt-6 max-w-2xl rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <input type="hidden" name="do" value="create">
        <?= csrf_field() ?>
        <h2 class="font-bold">New supplier</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="sname" class="block text-sm font-semibold">Business name</label>
                <input id="sname" name="name" required maxlength="120" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div>
                <label for="scontact" class="block text-sm font-semibold">Contact person</label>
                <input id="scontact" name="contact_name" maxlength="120" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div>
                <label for="sphone" class="block text-sm font-semibold">Phone</label>
                <input id="sphone" name="phone" maxlength="24" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div>
                <label for="semail" class="block text-sm font-semibold">Email</label>
                <input id="semail" name="email" type="email" maxlength="254" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div>
                <label for="snote" class="block text-sm font-semibold">Notes</label>
                <input id="snote" name="notes" maxlength="500" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
        </div>
        <button type="submit" class="mt-5 inline-flex h-12 items-center rounded-full bg-accent px-7 font-semibold text-white hover:bg-accent/90">Add supplier</button>
    </form>
    <?php endif; ?>
<?php });
