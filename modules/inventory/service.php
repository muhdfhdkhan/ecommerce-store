<?php
// modules/inventory/service.php — stock logic. All changes atomic inside transactions
// with row locks (SECURITY.md §5.4, FR-INV-001..010).
declare(strict_types=1);

/** Available = on hand − reserved, never negative. */
function inventory_available(int $variantId): int
{
    $st = db()->prepare(
        'SELECT quantity_on_hand - quantity_reserved AS available FROM inventory WHERE variant_id = :id'
    );
    $st->execute([':id' => $variantId]);
    $row = $st->fetch();
    return $row === false ? 0 : max(0, (int)$row['available']);
}

/**
 * Reserve stock for an order line inside an existing transaction.
 * Caller MUST have an open transaction and re-check availability.
 * Throws RuntimeException when stock is insufficient (rolls back the whole order).
 */
function inventory_reserve(int $variantId, int $qty, int $orderId, int $userId): void
{
    $pdo = db();
    // Row-level lock prevents overselling under concurrency (FR-INV-009).
    $st = $pdo->prepare(
        'SELECT quantity_on_hand, quantity_reserved FROM inventory
         WHERE variant_id = :id FOR UPDATE'
    );
    $st->execute([':id' => $variantId]);
    $row = $st->fetch();

    if ($row === false || ($row['quantity_on_hand'] - $row['quantity_reserved']) < $qty) {
        throw new RuntimeException('Item unavailable.');
    }

    $upd = $pdo->prepare(
        'UPDATE inventory SET quantity_reserved = quantity_reserved + :qty WHERE variant_id = :id'
    );
    $upd->execute([':qty' => $qty, ':id' => $variantId]);

    $mv = $pdo->prepare(
        'INSERT INTO stock_movements (variant_id, change_qty, reason, ref_type, ref_id, user_id, created_at)
         VALUES (:v, :qty, :reason, :ref_type, :ref_id, :u, UTC_TIMESTAMP())'
    );
    $mv->execute([
        ':v' => $variantId, ':qty' => -$qty, ':reason' => 'reservation',
        ':ref_type' => 'order', ':ref_id' => $orderId, ':u' => $userId > 0 ? $userId : null,
    ]);
}

/** Commit reserved units at confirmation (deduct on-hand, reduce reservation). */
function inventory_commit(int $variantId, int $qty, int $orderId, int $userId): void
{
    $pdo = db();
    $upd = $pdo->prepare(
        'UPDATE inventory
         SET quantity_on_hand = quantity_on_hand - :qty,
             quantity_reserved = quantity_reserved - :qty_res
         WHERE variant_id = :id AND quantity_reserved >= :qty_need'
    );
    $upd->execute([':qty' => $qty, ':qty_res' => $qty, ':qty_need' => $qty, ':id' => $variantId]);

    $mv = $pdo->prepare(
        'INSERT INTO stock_movements (variant_id, change_qty, reason, ref_type, ref_id, user_id, created_at)
         VALUES (:v, 0, :reason, :ref_type, :ref_id, :u, UTC_TIMESTAMP())'
    );
    $mv->execute([
        ':v' => $variantId, ':reason' => 'commit', ':ref_type' => 'order',
        ':ref_id' => $orderId, ':u' => $userId > 0 ? $userId : null,
    ]);
}

/** Release reserved units back (cancellation / expiry). */
function inventory_release(int $variantId, int $qty, ?int $orderId, int $userId): void
{
    $pdo = db();
    $upd = $pdo->prepare(
        'UPDATE inventory SET quantity_reserved = GREATEST(0, quantity_reserved - :qty) WHERE variant_id = :id'
    );
    $upd->execute([':qty' => $qty, ':id' => $variantId]);

    $mv = $pdo->prepare(
        'INSERT INTO stock_movements (variant_id, change_qty, reason, ref_type, ref_id, user_id, created_at)
         VALUES (:v, :qty, :reason, :ref_type, :ref_id, :u, UTC_TIMESTAMP())'
    );
    $mv->execute([
        ':v' => $variantId, ':qty' => $qty, ':reason' => 'release',
        ':ref_type' => 'order', ':ref_id' => $orderId, ':u' => $userId > 0 ? $userId : null,
    ]);
}
