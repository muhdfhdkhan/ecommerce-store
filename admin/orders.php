<?php
// admin/orders.php — order list + detail with status transitions (FR-ORD-001..012).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';
require_once BASE_PATH . '/includes/mailer.php';
require_once BASE_PATH . '/modules/inventory/service.php';

$ALLOWED_TRANSITIONS = [
    'pending_confirmation' => ['confirmed', 'cancelled', 'on_hold'],
    'confirmed'            => ['processing', 'cancelled', 'on_hold'],
    'processing'           => ['dispatched', 'cancelled'],
    'dispatched'           => ['delivered'],
    'delivered'            => [],
    'cancelled'            => [],
    'on_hold'              => ['confirmed', 'cancelled'],
];

$orderId = int_in($_GET['id'] ?? null);
$view = (string)($_GET['view'] ?? 'list');

// ---------- POST: status / payment / dispatch actions ----------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    if (!$orderId) { abort(400); }

    $st = db()->prepare('SELECT * FROM orders WHERE id = :id');
    $st->execute([':id' => $orderId]);
    $order = $st->fetch();
    if (!$order) { abort(404); }

    $do = (string)($_POST['do'] ?? '');

    if ($do === 'status' && admin_user_can($ADMIN, 'orders.update_status')) {
        $to = (string)($_POST['to_status'] ?? '');
        $from = (string)$order['status'];
        $allowed = $ALLOWED_TRANSITIONS[$from] ?? [];
        // Every status value must be a known status — the super_admin bypass
        // widens transitions, never allows unknown/empty values (SECURITY.md §19.2).
        if (in_array($to, array_keys($ALLOWED_TRANSITIONS), true)
            && (in_array($to, $allowed, true) || $ADMIN['role'] === 'super_admin')) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                // Stock policies on cancel: release reserved / restock committed lines.
                if ($to === 'cancelled' && !in_array($from, ['cancelled'], true)) {
                    $it = $pdo->prepare('SELECT variant_id, quantity FROM order_items WHERE order_id = :o');
                    $it->execute([':o' => $orderId]);
                    foreach ($it->fetchAll() as $line) {
                        if ($line['variant_id']) {
                            inventory_release((int)$line['variant_id'], (int)$line['quantity'], $orderId, (int)$ADMIN['id']);
                        }
                    }
                }
                if ($to === 'confirmed' && $from === 'pending_confirmation') {
                    // Commit reservation: deduct on-hand.
                    $it = $pdo->prepare('SELECT variant_id, quantity FROM order_items WHERE order_id = :o');
                    $it->execute([':o' => $orderId]);
                    foreach ($it->fetchAll() as $line) {
                        if ($line['variant_id']) {
                            inventory_commit((int)$line['variant_id'], (int)$line['quantity'], $orderId, (int)$ADMIN['id']);
                        }
                    }
                }

                $upd = $pdo->prepare('UPDATE orders SET status = :s, cancelled_at = ' . ($to === 'cancelled' ? 'UTC_TIMESTAMP(), cancel_reason = :cr' : 'cancelled_at') . ' WHERE id = :id');
                $params = [':s' => $to, ':id' => $orderId];
                if ($to === 'cancelled') { $params[':cr'] = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255) ?: null; }
                $upd->execute($params);

                $hist = $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, user_id, created_at) VALUES (:o, :f, :t, :u, UTC_TIMESTAMP())');
                $hist->execute([':o' => $orderId, ':f' => $from, ':t' => $to, ':u' => (int)$ADMIN['id']]);

                $pdo->commit();
                audit('order_status_changed', 'order', $orderId, ['status' => $from], ['status' => $to]);
                // Best-effort customer notification (SECURITY.md §3.3: never break the flow).
                try {
                    mailer_order_status((string)$order['email'], (string)$order['order_number'], $orderId, $to);
                } catch (Throwable $e) {
                    app_log('error', 'order status email failed: ' . $e->getMessage());
                }
                $_SESSION['flash_success'] = "Order marked $to.";
            } catch (Throwable $e) {
                $pdo->rollBack();
                app_log('error', 'order status update failed: ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Could not update the order.';
            }
        } else {
            $_SESSION['flash_error'] = 'That status change is not allowed.';
        }
        safe_redirect('/admin/orders?view=detail&id=' . $orderId);
    }

    if ($do === 'mark_paid' && admin_user_can($ADMIN, 'payments.verify')) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare("UPDATE payments SET status = 'paid', verified_by = :u, verified_at = UTC_TIMESTAMP() WHERE order_id = :o AND status != 'paid'");
            $up->execute([':u' => (int)$ADMIN['id'], ':o' => $orderId]);
            $uo = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = :id");
            $uo->execute([':id' => $orderId]);
            $pdo->commit();
            audit('payment_marked_paid', 'order', $orderId);
            $_SESSION['flash_success'] = 'Payment marked as paid.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not update payment.';
        }
        safe_redirect('/admin/orders?view=detail&id=' . $orderId);
    }

    if ($do === 'dispatch' && admin_user_can($ADMIN, 'orders.update_status')) {
        $courier = trim(mb_substr((string)($_POST['courier'] ?? ''), 0, 64));
        $tracking = trim(mb_substr((string)($_POST['tracking'] ?? ''), 0, 64));
        if ($courier === '' || $courier === null || $tracking === '') {
            $_SESSION['flash_error'] = 'Courier and tracking number are required.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $ins = $pdo->prepare('INSERT INTO shipments (order_id, courier_name, tracking_number, dispatched_at) VALUES (:o, :c, :t, UTC_TIMESTAMP())');
                $ins->execute([':o' => $orderId, ':c' => $courier, ':t' => $tracking]);
                $upd = $pdo->prepare("UPDATE orders SET status = 'dispatched', courier_name = :c, tracking_number = :t WHERE id = :id");
                $upd->execute([':c' => $courier, ':t' => $tracking, ':id' => $orderId]);
                $hist = $pdo->prepare("INSERT INTO order_status_history (order_id, from_status, to_status, user_id, created_at) SELECT :o, status, 'dispatched', :u, UTC_TIMESTAMP() FROM orders WHERE id = :o2");
                $hist->execute([':o' => $orderId, ':u' => (int)$ADMIN['id'], ':o2' => $orderId]);
                $pdo->commit();
                audit('order_dispatched', 'order', $orderId, null, ['courier' => $courier, 'tracking' => $tracking]);
                try {
                    mailer_order_status((string)$order['email'], (string)$order['order_number'], $orderId, 'dispatched');
                } catch (Throwable $e) {
                    app_log('error', 'order dispatch email failed: ' . $e->getMessage());
                }
                $_SESSION['flash_success'] = 'Order dispatched.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'Could not record shipment.';
            }
        }
        safe_redirect('/admin/orders?view=detail&id=' . $orderId);
    }

    safe_redirect('/admin/orders?view=detail&id=' . $orderId);
}

admin_layout('Orders', function () use ($ADMIN, $view, $orderId, $ALLOWED_TRANSITIONS) {
    // ---------- Detail ----------
    if ($view === 'detail' && $orderId) {
        $st = db()->prepare('SELECT * FROM orders WHERE id = :id');
        $st->execute([':id' => $orderId]);
        $order = $st->fetch();
        if (!$order) { abort(404); }

        $items = db()->prepare('SELECT * FROM order_items WHERE order_id = :o');
        $items->execute([':o' => $orderId]);
        $items = $items->fetchAll();

        $hist = db()->prepare(
            'SELECT h.*, u.name AS user_name FROM order_status_history h
             LEFT JOIN users u ON u.id = h.user_id WHERE h.order_id = :o ORDER BY h.id DESC'
        );
        $hist->execute([':o' => $orderId]);
        $history = $hist->fetchAll();

        $address = json_decode((string)$order['shipping_address'], true) ?: [];
        $allowed = $ALLOWED_TRANSITIONS[(string)$order['status']] ?? [];
        $canStatus = admin_user_can($ADMIN, 'orders.update_status');

        admin_flash();
        ?>
        <a href="/admin/orders" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">&larr; All orders</a>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-bold tracking-tight tabular-nums sm:text-2xl"><?= e($order['order_number']) ?></h1>
            <?php $badge = ['pending_confirmation' => 'bg-warning/10 text-warning', 'confirmed' => 'bg-accent-soft text-accent', 'processing' => 'bg-accent-soft text-accent', 'dispatched' => 'bg-accent-soft text-accent', 'delivered' => 'bg-success/10 text-success', 'cancelled' => 'bg-danger/10 text-danger', 'on_hold' => 'bg-warning/10 text-warning'][(string)$order['status']] ?? 'bg-line'; ?>
            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $badge ?>"><?= e(str_replace('_', ' ', (string)$order['status'])) ?></span>
            <span class="rounded-full bg-canvas px-3 py-1 text-xs font-semibold text-muted-ink"><?= e(ucfirst((string)$order['payment_status'])) ?> · <?= e(strtoupper((string)$order['payment_method'])) ?></span>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-line bg-surface p-5">
                    <h2 class="font-bold">Items</h2>
                    <ul class="mt-3 divide-y divide-line">
                        <?php foreach ($items as $it): ?>
                        <li class="flex items-center gap-3 py-3 text-sm">
                            <span class="min-w-0 flex-1"><span class="block font-medium"><?= e($it['product_name']) ?></span>
                                <span class="text-muted-ink"><?= e($it['variant_label'] ?: '') ?><?= e($it['sku']) ?></span></span>
                            <span class="tabular-nums text-muted-ink">&times; <?= (int)$it['quantity'] ?></span>
                            <span class="w-24 shrink-0 text-right font-semibold tabular-nums"><?= money_fmt(money_to_cents((string)$it['line_total'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <dl class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
                        <div class="flex justify-between"><dt class="text-muted-ink">Subtotal</dt><dd class="tabular-nums"><?= money_fmt(money_to_cents((string)$order['subtotal'])) ?></dd></div>
                        <div class="flex justify-between"><dt class="text-muted-ink">Shipping</dt><dd class="tabular-nums"><?= money_fmt(money_to_cents((string)$order['shipping_total'])) ?></dd></div>
                        <div class="flex justify-between font-bold"><dt>Total</dt><dd class="tabular-nums"><?= e($order['currency']) ?> <?= money_fmt(money_to_cents((string)$order['grand_total'])) ?></dd></div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-line bg-surface p-5">
                    <h2 class="font-bold">Customer</h2>
                    <p class="mt-2 text-sm"><?= e($order['full_name']) ?> · <a class="text-accent underline-offset-2 hover:underline" href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></p>
                    <p class="text-sm text-muted-ink"><?= e($order['email']) ?></p>
                    <p class="mt-3 text-sm text-muted-ink">
                        <?= e($address['line1'] ?? '') ?><?= !empty($address['line2']) ? ', ' . e($address['line2']) : '' ?>,
                        <?= e($address['city'] ?? '') ?><?= !empty($address['province']) ? ', ' . e($address['province']) : '' ?>
                        <?= !empty($address['postal']) ? ' ' . e($address['postal']) : '' ?>
                    </p>
                    <?php if (!empty($order['customer_note'])): ?>
                        <p class="mt-3 rounded-xl bg-canvas p-3 text-sm"><strong>Note:</strong> <?= e((string)$order['customer_note']) ?></p>
                    <?php endif; ?>
                </section>

                <section class="rounded-2xl border border-line bg-surface p-5">
                    <h2 class="font-bold">Activity timeline</h2>
                    <ol class="mt-3 space-y-3 border-l-2 border-line pl-4 text-sm">
                        <?php foreach ($history as $h): ?>
                        <li class="relative">
                            <span class="absolute -left-[22px] top-1.5 h-3 w-3 rounded-full border-2 border-surface bg-ink"></span>
                            <p class="font-medium">→ <?= e(str_replace('_', ' ', (string)$h['to_status'])) ?></p>
                            <p class="text-xs text-muted-ink"><?= e($h['user_name'] ?? 'System') ?> · UTC <?= e(substr((string)$h['created_at'], 0, 16)) ?></p>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            </div>

            <aside class="space-y-4">
                <?php if ($canStatus && $allowed): ?>
                <section class="rounded-2xl border border-line bg-surface p-5">
                    <h2 class="font-bold">Update status</h2>
                    <form method="post" class="mt-3 space-y-3" data-confirm>
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="status">
                        <select name="to_status" class="h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                            <?php foreach ($allowed as $s): ?>
                                <option value="<?= e($s) ?>"><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input name="reason" placeholder="Reason (for cancellations)" maxlength="255" class="h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                        <button class="h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90">Apply</button>
                    </form>
                </section>
                <?php endif; ?>

                <?php if ($canStatus && in_array($order['status'], ['confirmed', 'processing'], true)): ?>
                <section class="rounded-2xl border border-line bg-surface p-5">
                    <h2 class="font-bold">Dispatch</h2>
                    <form method="post" class="mt-3 space-y-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="dispatch">
                        <input name="courier" placeholder="Courier (TCS, Leopards…)" required maxlength="64" class="h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                        <input name="tracking" placeholder="Tracking number" required maxlength="64" class="h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm font-mono">
                        <button class="h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90">Mark dispatched</button>
                    </form>
                </section>
                <?php endif; ?>

                <?php if (admin_user_can($ADMIN, 'payments.verify') && $order['payment_status'] !== 'paid'): ?>
                <section class="rounded-2xl border border-line bg-surface p-5">
                    <h2 class="font-bold">Payment</h2>
                    <p class="mt-1 text-sm text-muted-ink">Currently: <?= e(ucfirst((string)$order['payment_status'])) ?></p>
                    <form method="post" class="mt-3" data-confirm>
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="mark_paid">
                        <button class="h-11 w-full rounded-xl border border-success/40 font-semibold text-success hover:bg-success/10">Mark as paid</button>
                    </form>
                </section>
                <?php endif; ?>
            </aside>
        </div>
        <?php
        return;
    }

    // ---------- List ----------
    $statusFilter = (string)($_GET['status'] ?? '');
    $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
    $page = max(1, int_in($_GET['page'] ?? 1, 1, 10000) ?? 1);
    $limit = 15;
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $params = [];
    if (in_array($statusFilter, ['pending_confirmation', 'confirmed', 'processing', 'dispatched', 'delivered', 'cancelled', 'on_hold'], true)) {
        $where[] = 'status = :st';
        $params[':st'] = $statusFilter;
    }
    if ($q !== '') {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $where[] = '(order_number LIKE :q OR full_name LIKE :q OR phone LIKE :q OR email LIKE :q)';
        $params[':q'] = "%$like%";
    }
    $whereSql = implode(' AND ', $where);

    $st = db()->prepare("SELECT id, order_number, full_name, phone, status, payment_status, payment_method, grand_total, placed_at FROM orders WHERE $whereSql ORDER BY id DESC LIMIT :l OFFSET :o");
    foreach ($params as $k => $v) { $st->bindValue($k, $v, PDO::PARAM_STR); }
    $st->bindValue(':l', $limit, PDO::PARAM_INT);
    $st->bindValue(':o', $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();

    $cst = db()->prepare("SELECT COUNT(*) FROM orders WHERE $whereSql");
    $cst->execute($params);
    $total = (int)$cst->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $limit));

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Orders</h1>

    <form method="get" action="/admin/orders" class="mt-4 flex flex-wrap gap-3">
        <input type="hidden" name="view" value="list">
        <input name="q" value="<?= e($q) ?>" placeholder="Order #, name, phone&hellip;" maxlength="80" class="h-11 w-full max-w-xs rounded-xl border border-line bg-surface px-4 text-sm">
        <select name="status" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
            <option value="">All statuses</option>
            <?php foreach (['pending_confirmation', 'confirmed', 'processing', 'dispatched', 'delivered', 'cancelled', 'on_hold'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="h-11 rounded-xl bg-ink px-5 text-sm font-semibold text-white">Filter</button>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <?php if (!$rows): ?>
            <p class="p-8 text-center text-sm text-muted-ink">No orders found.</p>
        <?php else: ?>
        <div class="divide-y divide-line">
            <?php foreach ($rows as $o): $badge = ['pending_confirmation' => 'bg-warning/10 text-warning', 'delivered' => 'bg-success/10 text-success', 'cancelled' => 'bg-danger/10 text-danger', 'on_hold' => 'bg-warning/10 text-warning'][(string)$o['status']] ?? 'bg-accent-soft text-accent'; ?>
            <a href="/admin/orders?view=detail&id=<?= (int)$o['id'] ?>" class="flex flex-wrap items-center gap-3 p-4 transition hover:bg-canvas sm:flex-nowrap">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold tabular-nums"><?= e($o['order_number']) ?></p>
                    <p class="truncate text-xs text-muted-ink"><?= e($o['full_name']) ?> · <?= e($o['phone']) ?> · UTC <?= e(substr((string)$o['placed_at'], 0, 16)) ?></p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $badge ?>"><?= e(str_replace('_', ' ', (string)$o['status'])) ?></span>
                <span class="w-28 shrink-0 text-right font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$o['grand_total'])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-6 flex items-center justify-center gap-3 text-sm" aria-label="Pagination">
        <?php if ($page > 1): ?><a class="rounded-xl border border-line px-4 py-2 hover:bg-canvas" href="/admin/orders?view=list&page=<?= $page - 1 ?>&status=<?= e($statusFilter) ?>&q=<?= e($q) ?>">&larr; Prev</a><?php endif; ?>
        <span class="text-muted-ink tabular-nums">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
        <?php if ($page < $totalPages): ?><a class="rounded-xl border border-line px-4 py-2 hover:bg-canvas" href="/admin/orders?view=list&page=<?= $page + 1 ?>&status=<?= e($statusFilter) ?>&q=<?= e($q) ?>">Next &rarr;</a><?php endif; ?>
    </nav>
    <?php endif; ?>
<?php });
