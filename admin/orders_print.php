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
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Dispatch sheets - Patches&amp;Motifs</title>
<style>
    @page { size: A4 landscape; margin: 10mm; }
    * { box-sizing: border-box; }
    body { font: 12px/1.45 "Segoe UI", Arial, sans-serif; color: #1a1a1a; margin: 0; background: #f0f0f0; }
    .toolbar { background: #30351B; color: #fff; padding: 10px 16px; position: sticky; top: 0; display: flex; gap: 12px; align-items: center; }
    .toolbar button { background: #fff; color: #30351B; border: 0; border-radius: 8px; padding: 8px 18px; font-weight: 700; font-size: 13px; cursor: pointer; }
    .toolbar .count { font-size: 13px; opacity: .85; }
    .sheet { background: #fff; margin: 14px auto; max-width: 277mm; padding: 10mm 12mm; box-shadow: 0 2px 8px rgba(0,0,0,.15); page-break-after: always; }
    .sheet-head { display: flex; justify-content: space-between; border-bottom: 2px solid #30351B; padding-bottom: 8px; }
    .brand { font-size: 18px; font-weight: 800; letter-spacing: -.3px; }
    .brand small { display: block; font-size: 10px; font-weight: 400; color: #555; }
    .order-no { text-align: right; }
    .order-no strong { font-size: 16px; }
    .cols { display: flex; gap: 10mm; margin-top: 10px; }
    .col { flex: 1; }
    .col h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: #666; margin: 0 0 4px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.items th, table.items td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; font-size: 11.5px; }
    table.items th { background: #f2f2f2; font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px; }
    table.items td.num, table.items th.num { text-align: right; }
    .totals { display: flex; justify-content: flex-end; margin-top: 8px; }
    .totals dl { min-width: 220px; font-size: 12px; }
    .totals div { display: flex; justify-content: space-between; padding: 2px 0; }
    .totals .grand { border-top: 1.5px solid #30351B; font-weight: 800; font-size: 14px; padding-top: 4px; }
    .cod-badge { display: inline-block; background: #30351B; color: #fff; font-weight: 800; padding: 4px 12px; border-radius: 6px; font-size: 13px; margin-top: 6px; }
    .footer-note { margin-top: 10px; font-size: 10px; color: #777; border-top: 1px solid #ddd; padding-top: 6px; }
    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .sheet { box-shadow: none; margin: 0; max-width: none; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <button type="button" data-print-trigger>Print sheets</button>
    <span class="count"><?= count($orders) ?> order(s) &middot; A4 landscape &middot; one sheet per order</span>
    <a href="/admin/orders" style="color:#d7d9c8;font-size:13px;">&larr; Back to orders</a>
</div>

<?php foreach ($orders as $o):
    $address = json_decode((string)$o['shipping_address'], true) ?: []; ?>
<div class="sheet">
    <div class="sheet-head">
        <div class="brand">Patches&amp;Motifs<small>Embroidered patches &amp; motifs &middot; patchesandmotifs.test</small></div>
        <div class="order-no">
            <strong><?= htmlspecialchars((string)$o['order_number']) ?></strong>
            <div style="font-size:11px;color:#666;">Placed: <?= htmlspecialchars(substr((string)$o['placed_at'], 0, 16)) ?> UTC</div>
            <div style="font-size:11px;color:#666;">Status: <?= htmlspecialchars(str_replace('_', ' ', (string)$o['status'])) ?> &middot; <?= htmlspecialchars(strtoupper((string)$o['payment_method'])) ?></div>
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
    <span class="cod-badge" style="background:#26734A;"><?= strtoupper((string)$o['payment_status']) ?> &mdash; Prepaid</span>
    <?php endif; ?>

    <p class="footer-note">Thank you for choosing Patches&amp;Motifs. Check contents before sealing; report mismatches within 48 hours.</p>
</div>
<?php endforeach; ?>

<?php if (!$orders): ?>
<div class="sheet"><p style="text-align:center;padding:40px 0;color:#888;">No orders selected.</p></div>
<?php endif; ?>

<script src="/assets/js/print.js"></script>
</body>
</html>
