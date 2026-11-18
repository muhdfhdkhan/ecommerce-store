<?php
// admin/inventory.php — stock overview + manual adjustments with reason (FR-INV-005).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'inventory.adjust');

    $variantId = int_in($_POST['variant_id'] ?? null);
    $delta = int_in($_POST['delta'] ?? null, -100000, 100000);
    $reason = trim(mb_substr((string)($_POST['reason'] ?? ''), 0, 255));

    if ($variantId && $delta !== null && $delta !== 0 && $reason !== '') {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT quantity_on_hand FROM inventory WHERE variant_id = :v FOR UPDATE');
            $st->execute([':v' => $variantId]);
            $row = $st->fetch();
            if (!$row) { throw new RuntimeException('no row'); }
            $newQty = max(0, (int)$row['quantity_on_hand'] + $delta);
            $upd = $pdo->prepare('UPDATE inventory SET quantity_on_hand = :q WHERE variant_id = :v');
            $upd->execute([':q' => $newQty, ':v' => $variantId]);
            $mv = $pdo->prepare(
                'INSERT INTO stock_movements (variant_id, change_qty, reason, ref_type, user_id, note, created_at)
                 VALUES (:v, :d, :r, :ref, :u, :note, UTC_TIMESTAMP())'
            );
            $mv->execute([':v' => $variantId, ':d' => $delta, ':r' => 'adjustment', ':ref' => 'manual',
                          ':u' => (int)$ADMIN['id'], ':note' => $reason]);
            $pdo->commit();
            audit('stock_adjusted', 'variant', $variantId, ['qty' => (int)$row['quantity_on_hand']], ['qty' => $newQty, 'reason' => $reason]);
            $_SESSION['flash_success'] = 'Stock adjusted.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not adjust stock.';
        }
    } else {
        $_SESSION['flash_error'] = 'A reason and a non-zero quantity are required.';
    }
    safe_redirect('/admin/inventory');
}

admin_layout('Inventory', function () use ($ADMIN) {
    $canAdjust = admin_user_can($ADMIN, 'inventory.adjust');

    $rows = db()->query(
        "SELECT v.id AS variant_id, v.sku, v.size_label, v.color_label, p.name AS product_name,
                i.quantity_on_hand, i.quantity_reserved, (i.quantity_on_hand - i.quantity_reserved) AS available, i.low_stock_threshold
         FROM product_variants v
         JOIN products p ON p.id = v.product_id AND p.deleted_at IS NULL
         JOIN inventory i ON i.variant_id = v.id
         WHERE v.is_active = 1 AND v.deleted_at IS NULL
         ORDER BY (i.quantity_on_hand - i.quantity_reserved) ASC, p.name ASC
         LIMIT 100"
    )->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Inventory</h1>
    <p class="mt-1 text-sm text-muted-ink">Available = on hand − reserved. Lowest stock first.</p>

    <div class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <div class="divide-y divide-line">
            <?php foreach ($rows as $r):
                $low = (int)$r['available'] <= (int)$r['low_stock_threshold'];
                $out = (int)$r['available'] <= 0;
                $label = trim(($r['size_label'] ?? '') . ' ' . ($r['color_label'] ?? '')); ?>
            <div class="flex flex-wrap items-center gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold"><?= e($r['product_name']) ?> <?= $label !== '' ? '· ' . e($label) : '' ?></p>
                    <p class="font-mono text-xs text-muted-ink"><?= e($r['sku']) ?></p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold tabular-nums <?= $out ? 'bg-danger/10 text-danger' : ($low ? 'bg-warning/10 text-warning' : 'bg-success/10 text-success') ?>">
                    <?= (int)$r['available'] ?> available
                </span>
                <?php if ($canAdjust): ?>
                <form method="post" class="flex items-center gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="variant_id" value="<?= (int)$r['variant_id'] ?>">
                    <input name="delta" type="number" min="-100000" max="100000" placeholder="±qty" required
                           class="h-10 w-24 rounded-xl border border-line bg-canvas px-2 text-sm tabular-nums" aria-label="Quantity change for <?= e($r['sku']) ?>">
                    <input name="reason" placeholder="Reason" required maxlength="255"
                           class="h-10 w-36 rounded-xl border border-line bg-canvas px-2 text-sm" aria-label="Reason for adjustment">
                    <button class="h-10 rounded-xl bg-ink px-4 text-sm font-semibold text-white">Apply</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php });
