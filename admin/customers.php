<?php
// admin/customers.php — customer list, block/unblock (FR-CUST-001..004).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'customers.block');

    $do = (string)($_POST['do'] ?? '');
    $cid = int_in($_POST['customer_id'] ?? null);
    if ($cid && in_array($do, ['block', 'unblock'], true)) {
        $newStatus = $do === 'block' ? 'blocked' : 'active';
        $st = db()->prepare('UPDATE customers SET status = :s WHERE id = :id');
        $st->execute([':s' => $newStatus, ':id' => $cid]);
        audit('customer_' . $do, 'customer', $cid);
        $_SESSION['flash_success'] = 'Customer ' . $do . 'ed.';
    }
    safe_redirect('/admin/customers');
}

admin_layout('Customers', function () use ($ADMIN) {
    $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
    $where = 'c.deleted_at IS NULL';
    $params = [];
    if ($q !== '') {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $where .= ' AND (c.name LIKE :q_name OR c.email LIKE :q_email OR c.phone LIKE :q_phone)';
        $params[':q_name']  = "%$like%";
        $params[':q_email'] = "%$like%";
        $params[':q_phone'] = "%$like%";
    }

    $st = db()->prepare(
        "SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
                (SELECT COALESCE(SUM(o.grand_total),0) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled') AS lifetime_value
         FROM customers c WHERE $where ORDER BY c.id DESC LIMIT 50"
    );
    $st->execute($params);
    $rows = $st->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Customers</h1>

    <form method="get" action="/admin/customers" class="mt-4 flex gap-3">
        <label class="sr-only" for="cq">Search</label>
        <input id="cq" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone&hellip;" maxlength="80" class="h-11 w-full max-w-xs rounded-xl border border-line bg-surface px-4 text-sm">
        <button class="h-11 rounded-xl bg-ink px-5 text-sm font-semibold text-white">Search</button>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <?php if (!$rows): ?>
            <p class="p-8 text-center text-sm text-muted-ink">No customers found.</p>
        <?php else: ?>
        <div class="divide-y divide-line">
            <?php foreach ($rows as $c): ?>
            <div class="flex flex-wrap items-center gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold"><?= e($c['name']) ?></p>
                    <p class="truncate text-xs text-muted-ink"><?= e($c['email']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?></p>
                </div>
                <span class="text-xs text-muted-ink tabular-nums"><?= (int)$c['order_count'] ?> orders · <?= money_fmt(money_to_cents((string)$c['lifetime_value'])) ?></span>
                <?php if (admin_user_can($ADMIN, 'customers.block')): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                    <button name="do" value="<?= $c['status'] === 'blocked' ? 'unblock' : 'block' ?>"
                            class="inline-flex h-10 items-center rounded-xl border px-4 text-sm font-semibold <?= $c['status'] === 'blocked' ? 'border-success/40 text-success hover:bg-success/10' : 'border-danger/40 text-danger hover:bg-danger/10' ?>">
                        <?= $c['status'] === 'blocked' ? 'Unblock' : 'Block' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
<?php });
