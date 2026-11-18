<?php
// admin/purchasing.php — purchase orders; receiving increments stock atomically (FR-INV).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';
require_once BASE_PATH . '/modules/inventory/service.php';

function po_next_number(PDO $pdo): string
{
    $year = gmdate('Y');
    $st = $pdo->prepare('SELECT LPAD(COALESCE(MAX(CAST(SUBSTRING(po_number, 9) AS UNSIGNED)), 0) + 1, 6, :pad) AS next
                         FROM purchase_orders WHERE po_number LIKE :prefix');
    $st->execute([':pad' => '0', ':prefix' => "PO-$year-%"]);
    $row = $st->fetch();
    return sprintf('PO-%s-%s', $year, $row ? $row['next'] : '000001');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'purchasing.manage');

    $do = (string)($_POST['do'] ?? '');
    $view = (string)($_GET['view'] ?? 'list');
    $poId = int_in($_GET['id'] ?? null);

    if ($do === 'create') {
        $supplierId = int_in($_POST['supplier_id'] ?? null);
        // Lines arrive as parallel arrays: variant_id[], quantity[], unit_cost[].
        $variants = $_POST['variant_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $costs = $_POST['unit_cost'] ?? [];
        $notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500);

        $lines = [];
        if (is_array($variants)) {
            foreach ($variants as $i => $vid) {
                $vid2 = int_in($vid, 1, 2147483647);
                $qty = int_in($quantities[$i] ?? null, 1, 100000);
                $cost = (string)($costs[$i] ?? '');
                if ($vid2 !== null && $qty !== null && preg_match('/^\d{1,10}(\.\d{1,2})?$/', $cost) && (float)$cost >= 0) {
                    $lines[] = [$vid2, $qty, $cost, round($qty * (float)$cost, 2)];
                }
            }
        }

        if (!$supplierId || !$lines) {
            $_SESSION['flash_error'] = 'Pick a supplier and at least one valid line.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $total = array_sum(array_map(static fn($l) => $l[3], $lines));
                $num = po_next_number($pdo);
                $ins = $pdo->prepare(
                    'INSERT INTO purchase_orders (po_number, supplier_id, status, notes, total_cost, created_by)
                     VALUES (:n, :s, :st, :no, :t, :u)'
                );
                $ins->execute([':n' => $num, ':s' => $supplierId, ':st' => 'draft', ':no' => $notes ?: null, ':t' => number_format($total, 2, '.', ''), ':u' => (int)$ADMIN['id']]);
                $poIdNew = (int)$pdo->lastInsertId();
                $insLine = $pdo->prepare(
                    'INSERT INTO purchase_order_items (po_id, variant_id, quantity, unit_cost, line_cost)
                     VALUES (:po, :v, :q, :c, :l)'
                );
                foreach ($lines as [$vid2, $qty, $cost, $lineCost]) {
                    $insLine->execute([':po' => $poIdNew, ':v' => $vid2, ':q' => $qty, ':c' => $cost, ':l' => number_format($lineCost, 2, '.', '')]);
                }
                $pdo->commit();
                audit('po_created', 'purchase_order', $poIdNew, null, ['number' => $num, 'total' => $total]);
                $_SESSION['flash_success'] = "Purchase order $num created.";
            } catch (Throwable $e) {
                $pdo->rollBack();
                app_log('error', 'po create failed: ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Could not create the purchase order.';
            }
        }
        safe_redirect('/admin/purchasing');
    }

    if ($do === 'order' || $do === 'receive' || $do === 'cancel') {
        if ($poId) {
            $st = db()->prepare('SELECT * FROM purchase_orders WHERE id = :id');
            $st->execute([':id' => $poId]);
            $po = $st->fetch();
            $allowedFlow = ['draft' => ['order', 'cancel'], 'ordered' => ['receive', 'cancel']];
            if ($po && in_array($do, $allowedFlow[(string)$po['status']] ?? [], true)) {
                $pdo = db();
                try {
                    $pdo->beginTransaction();
                    if ($do === 'order') {
                        $pdo->prepare("UPDATE purchase_orders SET status = 'ordered', ordered_at = UTC_TIMESTAMP() WHERE id = :id")
                            ->execute([':id' => $poId]);
                    } elseif ($do === 'cancel') {
                        $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = :id")
                            ->execute([':id' => $poId]);
                    } else { // receive: add stock with row locks + movement trail
                        $items = $pdo->prepare('SELECT variant_id, quantity, unit_cost FROM purchase_order_items WHERE po_id = :po');
                        $items->execute([':po' => $poId]);
                        foreach ($items->fetchAll() as $line) {
                            $lock = $pdo->prepare('SELECT quantity_on_hand FROM inventory WHERE variant_id = :v FOR UPDATE');
                            $lock->execute([':v' => $line['variant_id']]);
                            $row = $lock->fetch();
                            if ($row) {
                                $pdo->prepare('UPDATE inventory SET quantity_on_hand = quantity_on_hand + :q WHERE variant_id = :v')
                                    ->execute([':q' => $line['quantity'], ':v' => $line['variant_id']]);
                            } else {
                                $pdo->prepare('INSERT INTO inventory (variant_id, quantity_on_hand) VALUES (:v, :q)')
                                    ->execute([':v' => $line['variant_id'], ':q' => $line['quantity']]);
                            }
                            $pdo->prepare(
                                'INSERT INTO stock_movements (variant_id, change_qty, reason, ref_type, ref_id, user_id, note, created_at)
                                 VALUES (:v, :q, :reason, :ref, :rid, :u, :note, UTC_TIMESTAMP())'
                            )->execute([':v' => $line['variant_id'], ':q' => $line['quantity'], ':reason' => 'purchase',
                                        ':ref' => 'purchase_order', ':rid' => $poId, ':u' => (int)$ADMIN['id'],
                                        ':note' => 'Received PO ' . $po['po_number']]);
                        }
                        $pdo->prepare("UPDATE purchase_orders SET status = 'received', received_at = UTC_TIMESTAMP() WHERE id = :id")
                            ->execute([':id' => $poId]);
                    }
                    $pdo->commit();
                    audit('po_' . $do, 'purchase_order', $poId);
                    $_SESSION['flash_success'] = 'Purchase order ' . ($do === 'receive' ? 'received — stock added.' : "$do done.");
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    app_log('error', 'po update failed: ' . $e->getMessage());
                    $_SESSION['flash_error'] = 'Could not update the purchase order.';
                }
            } else {
                $_SESSION['flash_error'] = 'That action is not allowed for this purchase order.';
            }
        }
        safe_redirect('/admin/purchasing' . ($poId ? '?view=detail&id=' . $poId : ''));
    }
    safe_redirect('/admin/purchasing');
}

admin_layout('Purchasing', function () use ($ADMIN) {
    $canManage = admin_user_can($ADMIN, 'purchasing.manage');
    $view = (string)($_GET['view'] ?? 'list');
    $poId = int_in($_GET['id'] ?? null);

    admin_flash();

    if ($view === 'detail' && $poId) {
        $st = db()->prepare('SELECT po.*, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = :id');
        $st->execute([':id' => $poId]);
        $po = $st->fetch();
        if (!$po) { abort(404); }
        $items = db()->prepare(
            'SELECT poi.*, v.sku, p.name AS product_name FROM purchase_order_items poi
             JOIN product_variants v ON v.id = poi.variant_id
             JOIN products p ON p.id = v.product_id
             WHERE poi.po_id = :po'
        );
        $items->execute([':po' => $poId]);
        $items = $items->fetchAll();
        ?>
        <a href="/admin/purchasing" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">&larr; All purchase orders</a>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-bold tracking-tight tabular-nums sm:text-2xl"><?= e($po['po_number']) ?></h1>
            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= ['draft' => 'bg-line text-muted-ink', 'ordered' => 'bg-warning/10 text-warning', 'received' => 'bg-success/10 text-success', 'cancelled' => 'bg-danger/10 text-danger'][$po['status']] ?>"><?= e($po['status']) ?></span>
            <span class="text-sm text-muted-ink"><?= e($po['supplier_name']) ?></span>
        </div>
        <div class="mt-6 max-w-3xl overflow-hidden rounded-2xl border border-line bg-surface">
            <div class="divide-y divide-line">
                <?php foreach ($items as $it): ?>
                <div class="flex items-center gap-3 p-4 text-sm">
                    <span class="min-w-0 flex-1"><span class="block font-medium"><?= e($it['product_name']) ?></span><span class="font-mono text-xs text-muted-ink"><?= e($it['sku']) ?></span></span>
                    <span class="tabular-nums">&times; <?= (int)$it['quantity'] ?></span>
                    <span class="w-24 text-right tabular-nums">@ Rs <?= e(number_format((float)$it['unit_cost'], 0)) ?></span>
                    <span class="w-28 text-right font-semibold tabular-nums">Rs <?= e(number_format((float)$it['line_cost'], 0)) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-between border-t border-line p-4 text-sm font-bold"><dt>Total</dt><dd class="tabular-nums">Rs <?= e(number_format((float)$po['total_cost'], 0)) ?></dd></div>
        </div>
        <?php if ($canManage): ?>
        <div class="mt-4 flex gap-3">
            <?php if ($po['status'] === 'draft'): ?>
                <form method="post" action="/admin/purchasing?view=detail&id=<?= (int)$poId ?>"><?= csrf_field() ?><input type="hidden" name="do" value="order">
                    <button class="inline-flex h-11 items-center rounded-xl bg-accent px-5 text-sm font-semibold text-white">Mark ordered</button></form>
                <form method="post" action="/admin/purchasing?view=detail&id=<?= (int)$poId ?>" data-confirm><?= csrf_field() ?><input type="hidden" name="do" value="cancel">
                    <button class="inline-flex h-11 items-center rounded-xl border border-danger/40 px-5 text-sm font-semibold text-danger">Cancel</button></form>
            <?php elseif ($po['status'] === 'ordered'): ?>
                <form method="post" action="/admin/purchasing?view=detail&id=<?= (int)$poId ?>"><?= csrf_field() ?><input type="hidden" name="do" value="receive">
                    <button class="inline-flex h-11 items-center rounded-xl bg-success px-5 text-sm font-semibold text-white">Receive — add to stock</button></form>
                <form method="post" action="/admin/purchasing?view=detail&id=<?= (int)$poId ?>" data-confirm><?= csrf_field() ?><input type="hidden" name="do" value="cancel">
                    <button class="inline-flex h-11 items-center rounded-xl border border-danger/40 px-5 text-sm font-semibold text-danger">Cancel</button></form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php
        return;
    }
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Purchasing</h1>
    <p class="mt-1 text-sm text-muted-ink">Purchase orders: draft → ordered → received. Receiving adds stock with a movement trail.</p>

    <div class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <div class="divide-y divide-line">
            <?php
            $rows = db()->query(
                'SELECT po.id, po.po_number, po.status, po.total_cost, po.created_at, s.name AS supplier_name
                 FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id ORDER BY po.id DESC LIMIT 50'
            )->fetchAll();
            foreach ($rows as $p): ?>
            <a href="/admin/purchasing?view=detail&id=<?= (int)$p['id'] ?>" class="flex flex-wrap items-center gap-3 p-4 hover:bg-canvas">
                <span class="font-semibold tabular-nums"><?= e($p['po_number']) ?></span>
                <span class="text-sm text-muted-ink"><?= e($p['supplier_name']) ?></span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= ['draft' => 'bg-line text-muted-ink', 'ordered' => 'bg-warning/10 text-warning', 'received' => 'bg-success/10 text-success', 'cancelled' => 'bg-danger/10 text-danger'][$p['status']] ?>"><?= e($p['status']) ?></span>
                <span class="ml-auto font-bold tabular-nums">Rs <?= e(number_format((float)$p['total_cost'], 0)) ?></span>
            </a>
            <?php endforeach; ?>
            <?php if (!$rows): ?><p class="p-8 text-center text-sm text-muted-ink">No purchase orders yet.</p><?php endif; ?>
        </div>
    </div>

    <?php if ($canManage): ?>
    <?php
    $suppliers = db()->query('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name')->fetchAll();
    $variants = db()->query(
        'SELECT v.id, v.sku, p.name AS product_name FROM product_variants v
         JOIN products p ON p.id = v.product_id
         WHERE v.is_active = 1 AND v.deleted_at IS NULL AND p.deleted_at IS NULL
         ORDER BY p.name LIMIT 300'
    )->fetchAll();
    ?>
    <form method="post" class="mt-6 max-w-3xl rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <input type="hidden" name="do" value="create">
        <?= csrf_field() ?>
        <h2 class="font-bold">New purchase order</h2>
        <label for="supplier_id" class="mt-3 block text-sm font-semibold">Supplier</label>
        <select id="supplier_id" name="supplier_id" required class="mt-1.5 h-12 w-full max-w-sm rounded-xl border border-line bg-canvas/60 px-3">
            <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>

        <p class="mt-4 text-sm font-semibold">Lines</p>
        <div id="po-lines" class="mt-2 space-y-3">
            <div class="grid grid-cols-[1fr_100px_120px] gap-2">
                <select name="variant_id[]" required class="h-11 rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                    <?php foreach ($variants as $v): ?><option value="<?= (int)$v['id'] ?>"><?= e($v['product_name']) ?> — <?= e($v['sku']) ?></option><?php endforeach; ?>
                </select>
                <input name="quantity[]" type="number" min="1" max="100000" placeholder="Qty" required class="h-11 rounded-xl border border-line bg-canvas/60 px-3 text-sm tabular-nums" aria-label="Quantity">
                <input name="unit_cost[]" inputmode="decimal" placeholder="Unit cost" required class="h-11 rounded-xl border border-line bg-canvas/60 px-3 text-sm tabular-nums" aria-label="Unit cost">
            </div>
        </div>
        <button type="button" id="po-add-line" class="mt-3 inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">+ Add line</button>
        <label for="po-notes" class="mt-4 block text-sm font-semibold">Notes</label>
        <input id="po-notes" name="notes" maxlength="500" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
        <button type="submit" class="mt-5 inline-flex h-12 items-center rounded-full bg-accent px-7 font-semibold text-white hover:bg-accent/90">Create draft</button>
    </form>
    <?php endif; ?>
<?php });
