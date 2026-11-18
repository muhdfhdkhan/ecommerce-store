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

admin_layout('Reports', function () use ($from, $to, $daily, $byMethod, $grandTotal, $orderCount) {
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
<?php });
