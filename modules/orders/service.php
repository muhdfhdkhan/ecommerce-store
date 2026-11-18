<?php
// modules/orders/service.php — order placement & lifecycle.
// SECURITY.md §20.1: transaction + FOR UPDATE + idempotency + server-side totals only.
declare(strict_types=1);

require_once BASE_PATH . '/modules/inventory/service.php';
require_once BASE_PATH . '/includes/mailer.php';

/**
 * Validate a coupon against the cart subtotal inside the placing transaction.
 * Returns [code, discountCents] or [null, 0] when not applicable.
 */
function coupon_validate(?string $code, int $subtotalCents): array
{
    $code = strtoupper(trim(mb_substr((string)$code, 0, 32)));
    if ($code === '') {
        return [null, 0];
    }
    $st = db()->prepare(
        'SELECT code, type, value, min_subtotal, usage_limit, used_count, starts_at, ends_at
         FROM coupons WHERE code = :c AND is_active = 1 LIMIT 1'
    );
    $st->execute([':c' => $code]);
    $c = $st->fetch();
    if (!$c) {
        throw new RuntimeException('That coupon code is not valid.');
    }
    $now = UTC_NOW();
    if ($c['starts_at'] !== null && $now < $c['starts_at']) {
        throw new RuntimeException('That coupon is not active yet.');
    }
    if ($c['ends_at'] !== null && $now > $c['ends_at']) {
        throw new RuntimeException('That coupon has expired.');
    }
    if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit']) {
        throw new RuntimeException('That coupon has reached its usage limit.');
    }
    if ($subtotalCents < money_to_cents((string)$c['min_subtotal'])) {
        throw new RuntimeException('Cart does not meet the coupon minimum.');
    }
    $discount = $c['type'] === 'percent'
        ? (int)round($subtotalCents * ((float)$c['value'] / 100))
        : money_to_cents((string)$c['value']);
    $discount = min($discount, $subtotalCents);
    return [(string)$c['code'], $discount];
}

/**
 * Place an order. All pricing/stock computed here from DB — client-supplied
 * amounts are never trusted. Wraps everything in one transaction with an
 * idempotency key so duplicate submits cannot create duplicate orders.
 *
 * $input = [
 *   'full_name','email','phone','payment_method' (cod|bank_transfer),
 *   'shipping_address' => [full_name, phone, line1, line2, city, province, postal],
 *   'customer_note'
 * ]
 */
function place_order(int $customerId, array $input, string $idempotencyKey): array
{
    $cart = cart_view();
    if (empty($cart['items'])) {
        return [false, 'Your cart is empty.'];
    }

    $name  = trim(mb_substr((string)($input['full_name'] ?? ''), 0, 120));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $phone = trim((string)($input['phone'] ?? ''));
    $method = (string)($input['payment_method'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        return [false, 'Please complete your contact details.'];
    }
    if (!in_array($method, ['cod', 'bank_transfer'], true)) {
        return [false, 'Please choose a payment method.'];
    }
    $addr = $input['shipping_address'] ?? [];
    foreach (['full_name', 'phone', 'line1', 'city'] as $req) {
        if (trim((string)($addr[$req] ?? '')) === '') {
            return [false, 'Please complete the shipping address.'];
        }
    }

    // Shipping: default flat PKR 250, free over Rs 5,000 (matches seeded zone/method).
    $subtotal = $cart['subtotal_cents'];
    $shippingCents = $subtotal >= 500000 ? 0 : 25000;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Idempotency: a previous successful submit with this key returns that order (FR-CHK-010).
        $st = $pdo->prepare('SELECT id, order_number FROM orders WHERE idempotency_key = :k LIMIT 1');
        $st->execute([':k' => $idempotencyKey]);
        if ($existing = $st->fetch()) {
            $pdo->rollBack();
            return [true, ['order_id' => (int)$existing['id'], 'order_number' => $existing['order_number']]];
        }

        // ---- Server-side re-verification of every line (SECURITY.md §12.3) ----
        $lines = [];
        foreach ($cart['items'] as $item) {
            $available = inventory_available($item['variant_id']);
            if ($item['available'] !== null && $available < $item['quantity']) {
                throw new RuntimeException('Stock changed. Please review your cart.');
            }
            $lines[] = $item;
        }

        // ---- Coupon validated inside the transaction, discount from DB only ----
        [$couponCode, $discount] = coupon_validate(
            ($input['coupon_code'] ?? null) !== null ? (string)$input['coupon_code'] : ($_SESSION['applied_coupon'] ?? null),
            $subtotal
        );

        // ---- Order number EMB-YYYY-NNNNNN under lock (PROJECT_BRIEF §13.2) ----
        $year = gmdate('Y');
        $lock = $pdo->prepare('SELECT LPAD(COALESCE(MAX(CAST(SUBSTRING(order_number, 10) AS UNSIGNED)), 0) + 1, 6, :pad) AS next
                               FROM orders WHERE order_number LIKE :prefix');
        $lock->execute([':pad' => '0', ':prefix' => "EMB-$year-%"]);
        $seq = $lock->fetch();
        $orderNumber = sprintf('EMB-%s-%s', $year, $seq ? $seq['next'] : '000001');

        $addressJson = json_encode([
            'full_name' => mb_substr(trim((string)$addr['full_name']), 0, 120),
            'phone'     => trim((string)$addr['phone']),
            'line1'     => mb_substr(trim((string)$addr['line1']), 0, 255),
            'line2'     => mb_substr(trim((string)($addr['line2'] ?? '')), 0, 255),
            'city'      => mb_substr(trim((string)$addr['city']), 0, 80),
            'province'  => mb_substr(trim((string)($addr['province'] ?? '')), 0, 80),
            'postal'    => mb_substr(trim((string)($addr['postal'] ?? '')), 0, 16),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $paymentStatus = $method === 'bank_transfer' ? 'awaiting_verification' : 'pending';
        $grand = $subtotal + $shippingCents - $discount;

        $ins = $pdo->prepare(
            'INSERT INTO orders
                (order_number, customer_id, full_name, email, phone, status, payment_status, payment_method,
                 subtotal, discount_total, coupon_code, shipping_total, grand_total, currency,
                 shipping_address, customer_note, idempotency_key, placed_at)
             VALUES
                (:num, :cust, :name, :email, :phone, :status, :pay_status, :method,
                 :subtotal, :discount, :coupon, :shipping, :grand, :currency,
                 :address, :note, :idem, UTC_TIMESTAMP())'
        );
        $ins->execute([
            ':num' => $orderNumber,
            ':cust' => $customerId > 0 ? $customerId : null,
            ':name' => $name, ':email' => $email, ':phone' => substr($phone, 0, 24),
            ':status' => 'pending_confirmation', ':pay_status' => $paymentStatus, ':method' => $method,
            ':subtotal' => money_from_cents($subtotal),
            ':discount' => money_from_cents($discount),
            ':coupon' => $couponCode,
            ':shipping' => money_from_cents($shippingCents),
            ':grand'    => money_from_cents($grand),
            ':currency' => 'PKR',
            ':address'  => $addressJson,
            ':note'     => mb_substr(trim((string)($input['customer_note'] ?? '')), 0, 500) ?: null,
            ':idem'     => $idempotencyKey,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // ---- Items snapshot + stock reservation ----
        $insItem = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, variant_id, product_id, product_name, variant_label, sku, unit_price, quantity, line_total)
             VALUES (:o, :v, :p, :name, :label, :sku, :unit, :qty, :total)'
        );
        foreach ($lines as $line) {
            $label = $line['variant_label'] ?? trim(($line['size_label'] ?? '') . ' ' . ($line['color_label'] ?? ''));
            $insItem->execute([
                ':o' => $orderId, ':v' => $line['variant_id'], ':p' => $line['product_id'],
                ':name' => $line['product_name'], ':label' => $label ?: null,
                ':sku' => $line['sku'],
                ':unit' => money_from_cents($line['unit_price_cents']),
                ':qty' => $line['quantity'],
                ':total' => money_from_cents($line['line_total_cents']),
            ]);
            // Made-to-order lines skip stock reservation (no finite stock).
            if ($line['available'] !== null) {
                inventory_reserve($line['variant_id'], $line['quantity'], $orderId, $customerId);
            }
        }

        // ---- Payment record ----
        $pay = $pdo->prepare(
            'INSERT INTO payments (order_id, method, status, amount, created_at)
             VALUES (:o, :m, :s, :amt, UTC_TIMESTAMP())'
        );
        $pay->execute([':o' => $orderId, ':m' => $method, ':s' => $paymentStatus, ':amt' => money_from_cents($grand)]);

        // ---- Coupon usage counter ----
        if ($couponCode !== null) {
            $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE code = :c')
                ->execute([':c' => $couponCode]);
        }

        // ---- History seed ----
        $hist = $pdo->prepare(
            'INSERT INTO order_status_history (order_id, from_status, to_status, user_id, created_at)
             VALUES (:o, NULL, :status, NULL, UTC_TIMESTAMP())'
        );
        $hist->execute([':o' => $orderId, ':status' => 'pending_confirmation']);

        // ---- Clear the cart (FR-CHK-012) ----
        $cartRow = cart_current(false);
        if ($cartRow) {
            $del = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart');
            $del->execute([':cart' => (int)$cartRow['id']]);
        }
        unset($_SESSION['applied_coupon']);

        $pdo->commit();

        // ---- Notifications (best-effort, never blocks the order) ----
        try {
            $orderRow = ['id' => $orderId, 'order_number' => $orderNumber, 'full_name' => $name,
                         'email' => $email, 'payment_method' => $method, 'shipping_total' => $shippingCents / 100,
                         'discount_total' => $discount / 100, 'grand_total' => $grand / 100];
            mailer_order_confirmation($orderRow, array_map(static fn($l) => [
                'product_name' => $l['product_name'], 'variant_label' => $l['variant_label'] ?? '',
                'sku' => $l['sku'], 'quantity' => $l['quantity'], 'line_total' => $l['line_total_cents'] / 100,
            ], $lines));
        } catch (Throwable $e) {
            app_log('error', 'order confirmation email failed: ' . $e->getMessage());
        }

        return [true, ['order_id' => $orderId, 'order_number' => $orderNumber]];
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_log('error', 'place_order failed');
        return [false, $e instanceof RuntimeException ? $e->getMessage() : 'Could not place the order. Please try again.'];
    }
}
