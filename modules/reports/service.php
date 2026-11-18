<?php
// modules/reports/service.php - shared report queries for admin/reports.php and
// the CSV exporter. Every query is parameterised; date ranges are validated by callers.
declare(strict_types=1);

/** Known report types with labels. */
function report_types(): array
{
    return [
        'sales'      => 'Daily sales',
        'payments'   => 'Payment methods',
        'products'   => 'Top products',
        'categories' => 'Sales by category',
        'customers'  => 'Customers',
        'inventory'  => 'Inventory levels',
        'financial'  => 'Financial summary',
    ];
}

/**
 * Fetch one report.
 * Returns ['title', 'note', 'columns' => [[label, key, money?]], 'rows' => assoc arrays].
 */
function report_fetch(string $type, string $from, string $to): array
{
    $pdo = db();
    $range = "placed_at BETWEEN :from AND :to + INTERVAL 1 DAY AND status != 'cancelled'";
    // Fully-qualified variant for queries joining orders with product tables
    // ('status' exists on both orders and products — must be o.status).
    $rangeO = "o.placed_at BETWEEN :from AND :to + INTERVAL 1 DAY AND o.status != 'cancelled'";

    switch ($type) {
        case 'payments':
            $rows = $pdo->prepare(
                "SELECT payment_method, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS total
                 FROM orders WHERE $range GROUP BY payment_method ORDER BY total DESC"
            );
            $rows->execute([':from' => $from, ':to' => $to]);
            return ['title' => 'Payment methods', 'note' => 'Non-cancelled orders in range.',
                    'columns' => [['Method', 'payment_method'], ['Orders', 'orders'], ['Total', 'total', true]],
                    'rows' => $rows->fetchAll()];

        case 'products':
            $rows = $pdo->prepare(
                "SELECT p.name AS product, SUM(oi.quantity) AS units, COALESCE(SUM(oi.line_total),0) AS revenue
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 JOIN product_variants v ON v.id = oi.variant_id
                 JOIN products p ON p.id = v.product_id
                 WHERE $rangeO
                 GROUP BY p.id, p.name ORDER BY revenue DESC LIMIT 50"
            );
            $rows->execute([':from' => $from, ':to' => $to]);
            return ['title' => 'Top products', 'note' => 'Units and revenue per product in range (top 50).',
                    'columns' => [['Product', 'product'], ['Units', 'units'], ['Revenue', 'revenue', true]],
                    'rows' => $rows->fetchAll()];

        case 'categories':
            $rows = $pdo->prepare(
                "SELECT COALESCE(c.name, 'Uncategorised') AS category, SUM(oi.quantity) AS units,
                        COALESCE(SUM(oi.line_total),0) AS revenue
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 JOIN product_variants v ON v.id = oi.variant_id
                 JOIN products p ON p.id = v.product_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE $rangeO
                 GROUP BY c.id, c.name ORDER BY revenue DESC"
            );
            $rows->execute([':from' => $from, ':to' => $to]);
            return ['title' => 'Sales by category', 'note' => 'Revenue grouped by product category in range.',
                    'columns' => [['Category', 'category'], ['Units', 'units'], ['Revenue', 'revenue', true]],
                    'rows' => $rows->fetchAll()];

        case 'customers':
            $rows = $pdo->prepare(
                "SELECT full_name, email, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS spend,
                        MAX(placed_at) AS last_order
                 FROM orders WHERE $range
                 GROUP BY email, full_name ORDER BY spend DESC LIMIT 100"
            );
            $rows->execute([':from' => $from, ':to' => $to]);
            return ['title' => 'Customers', 'note' => 'Order count and spend per customer email in range (top 100).',
                    'columns' => [['Name', 'full_name'], ['Email', 'email'], ['Orders', 'orders'], ['Spend', 'spend', true], ['Last order', 'last_order']],
                    'rows' => $rows->fetchAll()];

        case 'inventory':
            $rows = $pdo->query(
                "SELECT p.name AS product, v.sku, v.size_label, v.length_label, v.color_label,
                        inv.quantity_on_hand, inv.quantity_reserved,
                        (inv.quantity_on_hand - inv.quantity_reserved) AS available
                 FROM inventory inv
                 JOIN product_variants v ON v.id = inv.variant_id
                 JOIN products p ON p.id = v.product_id
                 WHERE v.deleted_at IS NULL AND p.deleted_at IS NULL
                 ORDER BY available ASC, p.name LIMIT 500"
            );
            return ['title' => 'Inventory levels', 'note' => 'All active variants, lowest availability first (not date-filtered).',
                    'columns' => [['Product', 'product'], ['SKU', 'sku'], ['Size', 'size_label'], ['Length', 'length_label'],
                                  ['Color', 'color_label'], ['On hand', 'quantity_on_hand'], ['Reserved', 'quantity_reserved'],
                                  ['Available', 'available']],
                    'rows' => $rows->fetchAll()];

        case 'financial':
            $f = $pdo->prepare(
                "SELECT COALESCE(SUM(subtotal),0) AS gross, COALESCE(SUM(discount_total),0) AS discounts,
                        COALESCE(SUM(shipping_total),0) AS shipping, COALESCE(SUM(grand_total),0) AS net,
                        COUNT(*) AS orders
                 FROM orders WHERE $range"
            );
            $f->execute([':from' => $from, ':to' => $to]);
            $fin = $f->fetch();
            $cogsSt = $pdo->prepare(
                "SELECT COALESCE(SUM(oi.quantity * COALESCE((
                    SELECT poi.unit_cost FROM purchase_order_items poi
                    JOIN purchase_orders po ON po.id = poi.po_id
                    WHERE poi.variant_id = oi.variant_id AND po.status = 'received'
                    ORDER BY po.received_at DESC LIMIT 1
                 ), 0)), 0) AS cogs
                 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE $rangeO"
            );
            $cogsSt->execute([':from' => $from, ':to' => $to]);
            $cogs = (float)($cogsSt->fetch()['cogs'] ?? 0);
            $rows = [
                ['metric' => 'Orders', 'value' => (string)(int)$fin['orders']],
                ['metric' => 'Gross subtotal', 'value' => 'Rs ' . number_format((float)$fin['gross'], 0)],
                ['metric' => 'Discounts given', 'value' => '-Rs ' . number_format((float)$fin['discounts'], 0)],
                ['metric' => 'Shipping collected', 'value' => 'Rs ' . number_format((float)$fin['shipping'], 0)],
                ['metric' => 'Net revenue', 'value' => 'Rs ' . number_format((float)$fin['net'], 0)],
                ['metric' => 'COGS estimate', 'value' => 'Rs ' . number_format($cogs, 0)],
                ['metric' => 'Gross margin', 'value' => (float)$fin['net'] > 0
                    ? round(((float)$fin['net'] - $cogs) / (float)$fin['net'] * 100, 1) . '%' : '-'],
            ];
            return ['title' => 'Financial summary', 'note' => 'COGS uses the latest received purchase-order cost per variant.',
                    'columns' => [['Metric', 'metric'], ['Value', 'value']],
                    'rows' => $rows];

        default: // sales
            $rows = $pdo->prepare(
                "SELECT DATE(placed_at) AS day, COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS total
                 FROM orders WHERE $range GROUP BY DATE(placed_at) ORDER BY day ASC"
            );
            $rows->execute([':from' => $from, ':to' => $to]);
            return ['title' => 'Daily sales', 'note' => 'Completed (non-cancelled) orders per UTC day.',
                    'columns' => [['Day (UTC)', 'day'], ['Orders', 'orders'], ['Total', 'total', true]],
                    'rows' => $rows->fetchAll()];
    }
}
