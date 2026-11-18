<?php
// admin/analytics.php — dashboard analytics (Phase 6): KPIs, 14-day sales chart,
// top products, low stock, order-status funnel.
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

admin_require_permission($ADMIN, 'dashboard.view');

$pdo = db();

// KPIs — last 30 days vs previous 30 (non-cancelled orders).
$kpi = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN placed_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY THEN grand_total END), 0) AS rev30,
        COALESCE(SUM(CASE WHEN placed_at < UTC_TIMESTAMP() - INTERVAL 30 DAY THEN grand_total END), 0) AS rev_prev30,
        COUNT(CASE WHEN placed_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY THEN 1 END) AS ord30,
        COUNT(CASE WHEN placed_at < UTC_TIMESTAMP() - INTERVAL 30 DAY THEN 1 END) AS ord_prev30,
        COUNT(CASE WHEN placed_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY THEN 1 END) AS ord7
     FROM orders WHERE status != 'cancelled'"
)->fetch();
$rev30 = (float)$kpi['rev30'];
$revPrev = (float)$kpi['rev_prev30'];
$revDelta = $revPrev > 0 ? round(($rev30 - $revPrev) / $revPrev * 100) : null;

// 14-day daily series for the bar chart.
$daily = $pdo->query(
    "SELECT DATE(placed_at) AS day, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS total
     FROM orders WHERE status != 'cancelled' AND placed_at >= UTC_TIMESTAMP() - INTERVAL 14 DAY
     GROUP BY DATE(placed_at) ORDER BY day"
)->fetchAll(PDO::FETCH_ASSOC);
$maxDay = max(1, ...array_map(static fn($d) => (float)$d['total'], $daily ?: [['total' => 1]]));

// Top sellers by revenue in last 30 days.
$topProducts = $pdo->query(
    "SELECT p.name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN product_variants v ON v.id = oi.variant_id
     JOIN products p ON p.id = v.product_id
     WHERE o.status != 'cancelled' AND o.placed_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY
     GROUP BY p.id, p.name ORDER BY revenue DESC LIMIT 5"
)->fetchAll();

// Low stock (≤ 5 available, active variants).
$lowStock = $pdo->query(
    "SELECT p.name, v.sku, (inv.quantity_on_hand - inv.quantity_reserved) AS available
     FROM inventory inv
     JOIN product_variants v ON v.id = inv.variant_id
     JOIN products p ON p.id = v.product_id
     WHERE v.is_active = 1 AND v.deleted_at IS NULL AND p.deleted_at IS NULL
       AND (inv.quantity_on_hand - inv.quantity_reserved) <= 5
     ORDER BY available ASC LIMIT 8"
)->fetchAll();

// Order pipeline.
$funnel = $pdo->query(
    "SELECT status, COUNT(*) AS n FROM orders GROUP BY status ORDER BY FIELD(status,
     'pending_confirmation','confirmed','processing','dispatched','delivered','on_hold','cancelled')"
)->fetchAll();

// Purchase spend last 30 days (COGS proxy).
$poSpend = $pdo->query(
    "SELECT COALESCE(SUM(total_cost),0) AS spend FROM purchase_orders
     WHERE status = 'received' AND received_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY"
)->fetchColumn();

admin_layout('Analytics', function () use ($rev30, $revPrev, $revDelta, $daily, $maxDay, $topProducts, $lowStock, $funnel, $poSpend, $kpi) {
    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Analytics</h1>
    <p class="mt-1 text-sm text-muted-ink">Rolling 30-day view · UTC · non-cancelled orders.</p>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Revenue (30d)</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$rev30)) ?></p>
            <?php if ($revDelta !== null): ?>
            <p class="mt-1 text-xs font-semibold <?= $revDelta >= 0 ? 'text-success' : 'text-danger' ?>"><?= $revDelta >= 0 ? '▲' : '▼' ?> <?= abs($revDelta) ?>% vs prior 30d</p>
            <?php endif; ?>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Orders (30d)</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= (int)$kpi['ord30'] ?></p>
            <p class="mt-1 text-xs text-muted-ink"><?= (int)$kpi['ord7'] ?> in last 7 days</p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Avg order value (30d)</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= (int)$kpi['ord30'] > 0 ? money_fmt(money_to_cents((string)($rev30 / (int)$kpi['ord30']))) : '—' ?></p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Purchase spend (30d)</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$poSpend)) ?></p>
            <p class="mt-1 text-xs text-muted-ink">Received POs</p>
        </div>
    </div>

    <section class="mt-6 rounded-2xl border border-line bg-surface p-5">
        <h2 class="font-bold">Revenue — last 14 days</h2>
        <?php if (!$daily): ?>
            <p class="mt-3 text-sm text-muted-ink">No orders yet — the chart fills as orders arrive.</p>
        <?php else: ?>
        <div class="mt-5 flex h-40 items-end gap-1.5 sm:gap-2.5" role="img" aria-label="Daily revenue for the last 14 days">
            <?php foreach ($daily as $d):
                $pct = max(3, (int)round((float)$d['total'] / $maxDay * 100)); ?>
            <div class="group relative flex-1">
                <div class="rounded-t-lg bg-accent/80 transition group-hover:bg-accent" style="height: <?= $pct ?>%"></div>
                <span class="pointer-events-none absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-ink px-2 py-1 text-[11px] font-semibold text-white group-hover:block">
                    <?= e(substr((string)$d['day'], 5)) ?> · <?= money_fmt(money_to_cents((string)$d['total'])) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-2 flex gap-1.5 text-[10px] text-muted-ink sm:gap-2.5">
            <?php foreach ($daily as $d): ?><span class="flex-1 text-center tabular-nums"><?= e(substr((string)$d['day'], 8)) ?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-surface p-5">
            <h2 class="font-bold">Top products (30d revenue)</h2>
            <?php if (!$topProducts): ?>
                <p class="mt-3 text-sm text-muted-ink">No sales in this window.</p>
            <?php else: ?>
            <ul class="mt-3 divide-y divide-line text-sm">
                <?php foreach ($topProducts as $t): ?>
                <li class="flex items-center gap-3 py-2.5">
                    <span class="min-w-0 flex-1 truncate font-medium"><?= e($t['name']) ?></span>
                    <span class="text-xs text-muted-ink tabular-nums"><?= (int)$t['units'] ?> units</span>
                    <span class="font-semibold tabular-nums"><?= money_fmt(money_to_cents((string)$t['revenue'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">
            <h2 class="font-bold">Low stock</h2>
            <?php if (!$lowStock): ?>
                <p class="mt-3 text-sm text-muted-ink">All variants above the low-stock threshold.</p>
            <?php else: ?>
            <ul class="mt-3 divide-y divide-line text-sm">
                <?php foreach ($lowStock as $l): ?>
                <li class="flex items-center gap-3 py-2.5">
                    <span class="min-w-0 flex-1 truncate"><?= e($l['name']) ?><span class="block font-mono text-xs text-muted-ink"><?= e($l['sku']) ?></span></span>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold tabular-nums <?= (int)$l['available'] <= 2 ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning' ?>"><?= (int)$l['available'] ?> left</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-line bg-surface p-5">
        <h2 class="font-bold">Order pipeline</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            <?php foreach ($funnel as $f): ?>
            <div class="rounded-2xl border border-line bg-canvas/50 px-4 py-3">
                <p class="text-lg font-bold tabular-nums"><?= (int)$f['n'] ?></p>
                <p class="text-xs capitalize text-muted-ink"><?= e(str_replace('_', ' ', $f['status'])) ?></p>
            </div>
            <?php endforeach; ?>
            <?php if (!$funnel): ?><p class="text-sm text-muted-ink">No orders yet.</p><?php endif; ?>
        </div>
    </section>
<?php });
