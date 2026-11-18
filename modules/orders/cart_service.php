<?php
// modules/orders/cart_service.php — cart persistence (PHP session holds cart id only;
// MySQL holds the lines, per stack choice "PHP Sessions + MySQL").
declare(strict_types=1);

require_once BASE_PATH . '/modules/products/service.php';
require_once BASE_PATH . '/modules/inventory/service.php';

/** Get or create the cart bound to this browser session. */
function cart_current(bool $create = true): ?array
{
    $sid = session_id();
    if ($sid === '' || $sid === false) {
        return null;
    }
    $st = db()->prepare('SELECT id, customer_id FROM carts WHERE session_id = :sid LIMIT 1');
    $st->execute([':sid' => $sid]);
    $cart = $st->fetch();
    if ($cart || !$create) {
        return $cart ?: null;
    }
    $ins = db()->prepare('INSERT INTO carts (session_id) VALUES (:sid)');
    $ins->execute([':sid' => $sid]);
    $st->execute([':sid' => $sid]);
    return $st->fetch() ?: null;
}

/**
 * Add a variant with server-validated quantity and stock (FR-CART-005).
 * Returns [ok, message].
 */
function cart_add_item(int $variantId, int $qty): array
{
    $qty = max(1, min(99, $qty));

    $info = variant_effective_price($variantId); // validates product + variant status server-side
    if ($info === null) {
        return [false, 'This item is not available.'];
    }

    // Made-to-order products have unlimited supply — skip the stock check (FR-PROD-014).
    $isMto = (bool)($info['variant']['made_to_order'] ?? 0);
    if (!$isMto) {
        $available = inventory_available($variantId);
        if ($available < $qty) {
            return [false, $available > 0 ? "Only $available left in stock." : 'Out of stock.'];
        }
    }

    $cart = cart_current();
    if ($cart === null) {
        return [false, 'Could not create cart.'];
    }

    // Upsert the line (unique cart_id+variant_id).
    $st = db()->prepare(
        'INSERT INTO cart_items (cart_id, variant_id, quantity)
         VALUES (:cart, :variant, :qty)
         ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + :qty2, 99)'
    );
    $st->execute([':cart' => (int)$cart['id'], ':variant' => $variantId, ':qty' => $qty, ':qty2' => $qty]);

    return [true, 'Added to cart.'];
}

/** Full cart view with server-computed prices (never from client). */
function cart_view(): array
{
    $cart = cart_current(false);
    if (!$cart) {
        return ['items' => [], 'subtotal_cents' => 0, 'count' => 0];
    }

    $st = db()->prepare(
        'SELECT ci.id AS cart_item_id, ci.quantity,
                v.id AS variant_id, v.sku, v.size_label, v.color_label,
                p.id AS product_id, p.name AS product_name, p.slug, p.made_to_order,
                v.price, v.sale_price,
                (inv.quantity_on_hand - inv.quantity_reserved) AS available
         FROM cart_items ci
         JOIN product_variants v ON v.id = ci.variant_id
         JOIN products p ON p.id = v.product_id
         LEFT JOIN inventory inv ON inv.variant_id = v.id
         WHERE ci.cart_id = :cart AND v.is_active = 1 AND p.status = :status AND p.deleted_at IS NULL
         ORDER BY ci.id ASC'
    );
    $st->execute([':cart' => (int)$cart['id'], ':status' => 'active']);

    $subtotal = 0;
    $count = 0;
    $items = [];
    foreach ($st->fetchAll() as $row) {
        $price = ($row['sale_price'] !== null && (float)$row['sale_price'] < (float)$row['price'])
            ? (string)$row['sale_price'] : (string)$row['price'];
        $unit  = money_to_cents($price);
        $line  = $unit * (int)$row['quantity'];
        $subtotal += $line;
        $count += (int)$row['quantity'];
        $items[] = [
            'cart_item_id'   => (int)$row['cart_item_id'],
            'variant_id'     => (int)$row['variant_id'],
            'product_id'     => (int)$row['product_id'],
            'product_name'   => (string)$row['product_name'],
            'slug'           => (string)$row['slug'],
            'sku'            => (string)$row['sku'],
            'size_label'     => $row['size_label'],
            'color_label'    => $row['color_label'],
            'quantity'       => (int)$row['quantity'],
            'available'      => $row['available'] === null ? null : (int)$row['available'],
            'unit_price_cents' => $unit,
            'line_total_cents' => $line,
        ];
    }
    return ['items' => $items, 'subtotal_cents' => $subtotal, 'count' => $count];
}

function cart_update_quantity(int $cartItemId, int $qty): array
{
    $cart = cart_current(false);
    if (!$cart) {
        return [false, 'Cart not found.'];
    }
    $qty = max(1, min(99, $qty));

    $st = db()->prepare(
        'SELECT ci.variant_id FROM cart_items ci WHERE ci.id = :id AND ci.cart_id = :cart'
    );
    $st->execute([':id' => $cartItemId, ':cart' => (int)$cart['id']]);
    $row = $st->fetch();
    if (!$row) {
        return [false, 'Item not found.'];
    }

    $available = inventory_available((int)$row['variant_id']);
    if ($available < $qty) {
        return [false, "Only $available available."];
    }

    $upd = db()->prepare('UPDATE cart_items SET quantity = :qty WHERE id = :id AND cart_id = :cart');
    $upd->execute([':qty' => $qty, ':id' => $cartItemId, ':cart' => (int)$cart['id']]);
    return [true, 'Updated.'];
}

function cart_remove_item(int $cartItemId): void
{
    $cart = cart_current(false);
    if (!$cart) {
        return;
    }
    $st = db()->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :cart');
    $st->execute([':id' => $cartItemId, ':cart' => (int)$cart['id']]);
}

/** Merge a guest cart into the customer's cart after login (FR-CART-008). */
function cart_merge_into_customer(int $customerId): void
{
    $guest = cart_current(false);
    if (!$guest) {
        return;
    }
    // Find or create the customer's persistent cart keyed by session too — simplest:
    // attach guest cart to the customer and keep using it.
    $st = db()->prepare('UPDATE carts SET customer_id = :cid WHERE id = :id AND customer_id IS NULL');
    $st->execute([':cid' => $customerId, ':id' => (int)$guest['id']]);
}
