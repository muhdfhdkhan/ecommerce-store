<?php
// admin/dashboard.php — KPI dashboard (FR-ADM-001..007, DESIGN.md §11). Permission-aware.
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

// KPI definitions (documented, per FR-ADM-007):
// Sales = grand_total of non-cancelled orders placed in period.
// Orders = all orders in period. Pending = pending_confirmation count.
$canSee = admin_user_can($ADMIN, 'dashboard.view');

$todaysSales = 0; $ordersToday = 0; $pending = 0; $processing = 0; $delivered = 0; $lowStock = 0;
$recentOrders = [];

if ($canSee) {
    $r = db()->query(
        "SELECT COALESCE(SUM(grand_total),0) AS s, COUNT(*) AS c FROM orders
         WHERE status != 'cancelled' AND placed_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY"
    )->fetch();
    $todaysSales = (int)round(((float)$r['s']) * 100);
    $ordersToday = (int)$r['c'];

    $pending    = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending_confirmation'")->fetchColumn();
    $processing = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
    $delivered  = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();

    $lowStock = (int)db()->query(
        "SELECT COUNT(*) FROM inventory i
         JOIN product_variants v ON v.id = i.variant_id AND v.is_active = 1 AND v.deleted_at IS NULL
         JOIN products p ON p.id = v.product_id AND p.status = 'active' AND p.deleted_at IS NULL
         WHERE (i.quantity_on_hand - i.quantity_reserved) <= i.low_stock_threshold"
    )->fetchColumn();

    $st = db()->prepare(
        'SELECT id, order_number, full_name, status, grand_total, placed_at FROM orders
         ORDER BY id DESC LIMIT 6'
    );
    $st->execute();
    $recentOrders = $st->fetchAll();
}

admin_layout('Dashboard', function () use ($ADMIN, $canSee, $todaysSales, $ordersToday, $pending, $processing, $delivered, $lowStock, $recentOrders) {
    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Welcome, <?= e($ADMIN['name']) ?> 👋</h1>
    <p class="mt-1 text-sm text-muted-ink">Patches&amp;Motifs admin · times shown in UTC (display: Asia/Karachi)</p>

    <?php if ($canSee): ?>
    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Sales (24h)</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= money_fmt($todaysSales) ?></p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Orders (24h)</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= $ordersToday ?></p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Pending confirmation</p>
            <p class="mt-2 text-xl font-bold tabular-nums <?= $pending > 0 ? 'text-warning' : '' ?>"><?= $pending ?></p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Low stock alerts</p>
            <p class="mt-2 text-xl font-bold tabular-nums <?= $lowStock > 0 ? 'text-warning' : '' ?>"><?= $lowStock ?></p>
        </div>
    </div>

    <section class="mt-6 rounded-2xl border border-line bg-surface">
        <div class="flex items-center justify-between p-5">
            <h2 class="font-bold">Recent orders</h2>
            <a href="/admin/orders" class="text-sm font-semibold text-accent underline-offset-4 hover:underline">View all</a>
        </div>
        <?php if (!$recentOrders): ?>
            <p class="border-t border-line p-6 text-center text-sm text-muted-ink">No orders yet.</p>
        <?php else: ?>
        <div class="divide-y divide-line border-t border-line">
            <?php foreach ($recentOrders as $o): ?>
            <a href="/admin/orders?view=detail&id=<?= (int)$o['id'] ?>" class="flex flex-wrap items-center gap-3 p-4 hover:bg-canvas sm:flex-nowrap">
                <p class="w-36 shrink-0 font-semibold tabular-nums text-sm"><?= e($o['order_number']) ?></p>
                <p class="min-w-0 flex-1 truncate text-sm"><?= e($o['full_name']) ?></p>
                <span class="rounded-full bg-canvas px-3 py-1 text-xs font-semibold text-muted-ink"><?= e(str_replace('_', ' ', (string)$o['status'])) ?></span>
                <p class="w-24 shrink-0 text-right text-sm font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$o['grand_total'])) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
<?php });
