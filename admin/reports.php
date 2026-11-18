<?php
// admin/reports.php — sales report with date range (FR sales reports; export later phase).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

admin_require_permission($ADMIN, 'reports.view');

$from = (string)($_GET['from'] ?? gmdate('Y-m-01'));
$to = (string)($_GET['to'] ?? gmdate('Y-m-d'));
$fromOk = DateTimeImmutable::createFromFormat('Y-m-d', $from) !== false;
$toOk = DateTimeImmutable::createFromFormat('Y-m-d', $to) !== false;
if (!$fromOk || !$toOk) { $from = gmdate('Y-m-01'); $to = gmdate('Y-m-d'); }

$st = db()->prepare(
    "SELECT DATE(placed_at) AS day, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS total
     FROM orders
     WHERE status != 'cancelled' AND placed_at BETWEEN :from AND :to + INTERVAL 1 DAY
     GROUP BY DATE(placed_at) ORDER BY day ASC"
);
$st->execute([':from' => $from, ':to' => $to]);
$daily = $st->fetchAll();

$st2 = db()->prepare(
    "SELECT payment_method, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS total
     FROM orders WHERE status != 'cancelled' AND placed_at BETWEEN :from AND :to + INTERVAL 1 DAY
     GROUP BY payment_method"
);
$st2->execute([':from' => $from, ':to' => $to]);
$byMethod = $st2->fetchAll();

$grandTotal = array_sum(array_map(static fn($r) => (float)$r['total'], $daily));
$orderCount = array_sum(array_map(static fn($r) => (int)$r['orders'], $daily));

// ---- Financial section (Phase 6): discounts, coupon usage, COGS estimate ----
$stf = db()->prepare(
    "SELECT COALESCE(SUM(subtotal),0) AS gross, COALESCE(SUM(discount_total),0) AS discounts,
            COALESCE(SUM(shipping_total),0) AS shipping, COALESCE(SUM(grand_total),0) AS net,
            COUNT(*) AS orders
     FROM orders WHERE status != 'cancelled' AND placed_at BETWEEN :from AND :to + INTERVAL 1 DAY"
);
$stf->execute([':from' => $from, ':to' => $to]);
$fin = $stf->fetch();

$couponRows = db()->prepare(
    "SELECT coupon_code, COUNT(*) AS uses, COALESCE(SUM(discount_total),0) AS given
     FROM orders WHERE status != 'cancelled' AND coupon_code IS NOT NULL
       AND placed_at BETWEEN :from AND :to + INTERVAL 1 DAY
     GROUP BY coupon_code ORDER BY given DESC LIMIT 10"
);
$couponRows->execute([':from' => $from, ':to' => $to]);
$couponUsage = $couponRows->fetchAll();

// COGS estimate: latest received-PO unit cost for each variant sold in the window.
$cogsSt = db()->prepare(
    "SELECT COALESCE(SUM(oi.quantity * COALESCE((
        SELECT poi.unit_cost FROM purchase_order_items poi
        JOIN purchase_orders po ON po.id = poi.po_id
        WHERE poi.variant_id = oi.variant_id AND po.status = 'received'
        ORDER BY po.received_at DESC LIMIT 1
     ), 0)), 0) AS cogs
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status != 'cancelled' AND o.placed_at BETWEEN :from AND :to + INTERVAL 1 DAY"
);
$cogsSt->execute([':from' => $from, ':to' => $to]);
$cogs = (float)($cogsSt->fetch()['cogs'] ?? 0);
$gross = (float)$fin['gross'];
$discounts = (float)$fin['discounts'];
$net = (float)$fin['net'];
$margin = $net > 0 ? round(($net - $cogs) / $net * 100) : null;

admin_layout('Reports', function () use ($from, $to, $daily, $byMethod, $grandTotal, $orderCount, $gross, $discounts, $net, $couponUsage, $cogs, $margin) {
    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Sales report</h1>
    <p class="mt-1 text-sm text-muted-ink">Completed (non-cancelled) orders · UTC days · totals include unpaid orders.</p>

    <form method="get" action="/admin/reports" class="mt-4 flex flex-wrap items-end gap-3">
        <div><label for="from" class="block text-xs font-semibold text-muted-ink">From</label>
            <input id="from" name="from" type="date" value="<?= e($from) ?>" class="mt-1 h-11 rounded-xl border border-line bg-surface px-3 text-sm"></div>
        <div><label for="to" class="block text-xs font-semibold text-muted-ink">To</label>
            <input id="to" name="to" type="date" value="<?= e($to) ?>" class="mt-1 h-11 rounded-xl border border-line bg-surface px-3 text-sm"></div>
        <button class="h-11 rounded-xl bg-ink px-5 text-sm font-semibold text-white">Apply</button>
    </form>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Total sales</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$grandTotal)) ?></p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Orders</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= (int)$orderCount ?></p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-4">
            <p class="text-xs font-medium text-muted-ink">Avg order value</p>
            <p class="mt-2 text-xl font-bold tabular-nums"><?= $orderCount > 0 ? money_fmt(money_to_cents((string)($grandTotal / $orderCount))) : '—' ?></p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-surface p-5">
            <h2 class="font-bold">Daily sales</h2>
            <?php if (!$daily): ?>
                <p class="mt-3 text-sm text-muted-ink">No data for this range.</p>
            <?php else: ?>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-xs text-muted-ink"><th class="pb-2">Day (UTC)</th><th class="pb-2 text-right">Orders</th><th class="pb-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-line">
                    <?php foreach ($daily as $d): ?>
                    <tr><td class="py-2 tabular-nums"><?= e($d['day']) ?></td><td class="py-2 text-right tabular-nums"><?= (int)$d['orders'] ?></td><td class="py-2 text-right font-semibold tabular-nums"><?= money_fmt(money_to_cents((string)$d['total'])) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">
            <h2 class="font-bold">By payment method</h2>
            <?php if (!$byMethod): ?>
                <p class="mt-3 text-sm text-muted-ink">No data.</p>
            <?php else: ?>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-xs text-muted-ink"><th class="pb-2">Method</th><th class="pb-2 text-right">Orders</th><th class="pb-2 text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-line">
                    <?php foreach ($byMethod as $m): ?>
                    <tr><td class="py-2 capitalize"><?= e(str_replace('_', ' ', $m['payment_method'])) ?></td><td class="py-2 text-right tabular-nums"><?= (int)$m['orders'] ?></td><td class="py-2 text-right font-semibold tabular-nums"><?= money_fmt(money_to_cents((string)$m['total'])) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>

    <!-- Financial section (Phase 6) -->
    <section class="mt-6 rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <h2 class="font-bold">Financial summary</h2>
        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <div class="rounded-xl border border-line bg-canvas/50 p-3">
                <p class="text-xs text-muted-ink">Gross subtotal</p>
                <p class="mt-1 font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$gross)) ?></p>
            </div>
            <div class="rounded-xl border border-line bg-canvas/50 p-3">
                <p class="text-xs text-muted-ink">Discounts given</p>
                <p class="mt-1 font-bold tabular-nums text-danger">&minus;<?= money_fmt(money_to_cents((string)$discounts)) ?></p>
            </div>
            <div class="rounded-xl border border-line bg-canvas/50 p-3">
                <p class="text-xs text-muted-ink">Net revenue</p>
                <p class="mt-1 font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$net)) ?></p>
            </div>
            <div class="rounded-xl border border-line bg-canvas/50 p-3">
                <p class="text-xs text-muted-ink">COGS estimate</p>
                <p class="mt-1 font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$cogs)) ?></p>
            </div>
            <div class="rounded-xl border border-line bg-canvas/50 p-3">
                <p class="text-xs text-muted-ink">Gross margin</p>
                <p class="mt-1 font-bold tabular-nums"><?= $margin !== null ? $margin . '%' : '—' ?></p>
            </div>
        </div>
        <p class="mt-2 text-xs text-muted-ink">COGS uses the latest received purchase-order unit cost per variant; variants without a PO show zero cost.</p>

        <h3 class="mt-6 text-sm font-bold">Coupon usage in range</h3>
        <?php if (!$couponUsage): ?>
            <p class="mt-2 text-sm text-muted-ink">No coupons applied.</p>
        <?php else: ?>
        <table class="mt-2 w-full max-w-xl text-sm">
            <thead><tr class="text-left text-xs text-muted-ink"><th class="pb-2">Code</th><th class="pb-2 text-right">Uses</th><th class="pb-2 text-right">Discount given</th></tr></thead>
            <tbody class="divide-y divide-line">
                <?php foreach ($couponUsage as $c): ?>
                <tr><td class="py-2 font-mono font-semibold"><?= e($c['coupon_code']) ?></td><td class="py-2 text-right tabular-nums"><?= (int)$c['uses'] ?></td><td class="py-2 text-right tabular-nums">&minus;<?= money_fmt(money_to_cents((string)$c['given'])) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
<?php });
