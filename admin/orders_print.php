<?php
// admin/orders/print.php - bulk dispatch sheets, A4 landscape (Phase: orders printing).
// Standalone page (no admin chrome) so the browser prints exactly what it shows.
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';
admin_require_permission($ADMIN, 'orders.view');

// ids: comma list of order ids, or "recent" for the latest 10 non-cancelled orders.
$rawIds = (string)($_GET['ids'] ?? '');
$ids = [];
if ($rawIds === 'recent') {
    $rows = db()->query(
        "SELECT id FROM orders WHERE status NOT IN ('cancelled') ORDER BY id DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_COLUMN);
    $ids = array_map('intval', $rows);
} else {
    foreach (explode(',', $rawIds) as $piece) {
        $id = int_in($piece, 1, 2147483647);
        if ($id !== null) { $ids[] = $id; }
    }
    $ids = array_slice(array_values(array_unique($ids)), 0, 50);
}

$orders = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM orders WHERE id IN ($placeholders) ORDER BY id");
    $st->execute($ids);
    $orders = $st->fetchAll();

    $ist = db()->prepare('SELECT product_name, variant_label, sku, quantity, line_total FROM order_items WHERE order_id = :o ORDER BY id');
    foreach ($orders as $i => $o) {
        $ist->execute([':o' => $o['id']]);
        $orders[$i]['items'] = $ist->fetchAll();
    }
}

// One order gets an A4 portrait sheet; bulk printing stays A4 landscape.
$single = count($orders) === 1;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Dispatch sheets - Patches&amp;Motifs</title>
<link rel="stylesheet" href="<?= asset_url('/assets/css/print.css') ?>">
<?php if ($single): ?>
<link rel="stylesheet" href="<?= asset_url('/assets/css/print-single.css') ?>">
<?php endif; ?>
</head>
<body>
<div class="toolbar">
    <button type="button" data-print-trigger>Print sheet<?= $single ? '' : 's' ?></button>
    <span class="count"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> &middot; A4 <?= $single ? 'portrait' : 'landscape' ?> &middot; <?= $single ? 'single order' : 'one sheet per order' ?></span>
    <a href="/admin/orders" class="toolbar-back">&larr; Back to orders</a>
</div>

<?php foreach ($orders as $o):
    $address = json_decode((string)$o['shipping_address'], true) ?: []; ?>
<div class="sheet<?= $single ? ' sheet--single' : '' ?>">
    <div class="sheet-head">
        <div class="brand">Patches&amp;Motifs<small>Embroidered patches &amp; motifs &middot; patchesandmotifs.test</small></div>
        <div class="order-no">
            <strong><?= htmlspecialchars((string)$o['order_number']) ?></strong>
            <div class="meta">Placed: <?= htmlspecialchars(substr((string)$o['placed_at'], 0, 16)) ?> UTC</div>
            <div class="meta">Status: <?= htmlspecialchars(str_replace('_', ' ', (string)$o['status'])) ?> &middot; <?= htmlspecialchars(strtoupper((string)$o['payment_method'])) ?></div>
        </div>
    </div>

    <div class="cols">
        <div class="col">
            <h3>Ship to</h3>
            <strong><?= htmlspecialchars((string)$o['full_name']) ?></strong><br>
            <?= htmlspecialchars((string)($address['line1'] ?? '')) ?><br>
            <?php if (!empty($address['line2'])): ?><?= htmlspecialchars((string)$address['line2']) ?><br><?php endif; ?>
            <?= htmlspecialchars((string)($address['city'] ?? '')) ?><?= !empty($address['province']) ? ', ' . htmlspecialchars((string)$address['province']) : '' ?> <?= htmlspecialchars((string)($address['postal'] ?? '')) ?><br>
            Phone: <strong><?= htmlspecialchars((string)$o['phone']) ?></strong>
        </div>
        <div class="col">
            <h3>Parcel</h3>
            Courier: <?= htmlspecialchars((string)($o['courier_name'] ?: 'PostEx')) ?><br>
            <?php if (!empty($o['tracking_number'])): ?>Tracking: <strong><?= htmlspecialchars((string)$o['tracking_number']) ?></strong><br><?php endif; ?>
            <?php if ((float)$o['discount_total'] > 0): ?>Discount<?= $o['coupon_code'] ? ' (' . htmlspecialchars((string)$o['coupon_code']) . ')' : '' ?>: -Rs <?= number_format((float)$o['discount_total'], 0) ?><br><?php endif; ?>
            <?php if (!empty($o['customer_note'])): ?>Note: <?= htmlspecialchars((string)$o['customer_note']) ?><?php endif; ?>
        </div>
    </div>

    <table class="items">
        <thead><tr><th>#</th><th>Product</th><th>Variant</th><th>SKU</th><th class="num">Qty</th><th class="num">Line total</th></tr></thead>
        <tbody>
        <?php foreach ($o['items'] as $n => $it): ?>
        <tr>
            <td><?= $n + 1 ?></td>
            <td><?= htmlspecialchars((string)$it['product_name']) ?></td>
            <td><?= htmlspecialchars((string)$it['variant_label']) ?></td>
            <td><?= htmlspecialchars((string)$it['sku']) ?></td>
            <td class="num"><?= (int)$it['quantity'] ?></td>
            <td class="num">Rs <?= number_format((float)$it['line_total'], 0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <dl>
            <div><dt>Subtotal</dt><dd>Rs <?= number_format((float)$o['subtotal'], 0) ?></dd></div>
            <div><dt>Shipping</dt><dd>Rs <?= number_format((float)$o['shipping_total'], 0) ?></dd></div>
            <div class="grand"><dt>Total</dt><dd>Rs <?= number_format((float)$o['grand_total'], 0) ?></dd></div>
        </dl>
    </div>
    <?php if ($o['payment_method'] === 'cod'): ?>
    <span class="cod-badge">COD &mdash; Collect Rs <?= number_format((float)$o['grand_total'], 0) ?></span>
    <?php else: ?>
    <span class="cod-badge cod-badge--paid"><?= strtoupper((string)$o['payment_status']) ?> &mdash; Prepaid</span>
    <?php endif; ?>

    <p class="footer-note">Thank you for choosing Patches&amp;Motifs. Check contents before sealing; report mismatches within 48 hours.</p>
</div>
<?php endforeach; ?>

<?php if (!$orders): ?>
<div class="sheet sheet--single"><p class="empty-note">No orders selected.</p></div>
<?php endif; ?>

<script src="/assets/js/print.js"></script>
</body>
</html>
