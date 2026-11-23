<?php
// modules/store/pages/cart.php — cart page (FR-CART-*, DESIGN.md §9).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

require_once BASE_PATH . '/modules/orders/cart_service.php';

// Non-JS-safe cart mutations (remove / quantity) plus the add-to-cart fallback.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $do = (string)($_POST['do'] ?? 'add');

    if ($do === 'remove') {
        $lineId = int_in($_POST['cart_item_id'] ?? null);
        if ($lineId !== null) {
            cart_remove_item($lineId);
        }
        safe_redirect('/cart');
    }

    if ($do === 'update') {
        $lineId = int_in($_POST['cart_item_id'] ?? null);
        $qty = int_in($_POST['qty'] ?? null, 1, 99);
        if ($lineId !== null && $qty !== null) {
            cart_update_quantity($lineId, $qty);
        }
        safe_redirect('/cart');
    }

    // Non-JS fallback for the add-to-cart form (POST /cart/add). Same server-side
    // validation as the JSON endpoint — no client-trusted values (SECURITY.md §12.3).
    $variantId = int_in($_POST['variant_id'] ?? null);
    $qty = int_in($_POST['qty'] ?? 1, 1, 99) ?? 1;
    $customSize = is_scalar($_POST['custom_size'] ?? null) ? (string)$_POST['custom_size'] : '';
    if ($variantId !== null) {
        cart_add_item($variantId, $qty, $customSize);
    }
    safe_redirect('/cart');
}

$cart = cart_view();

store_layout('Cart', function () use ($cart) { ?>
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:py-12">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Your cart</h1>
    <p data-cart-notice class="mt-4 hidden rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert"></p>

    <?php if (empty($cart['items'])): ?>
        <div class="mt-8 rounded-3xl border border-line bg-surface p-10 text-center">
            <p class="text-lg font-semibold">Your cart is empty</p>
            <p class="mt-1 text-sm text-muted-ink">Browse the shop to find something you love.</p>
            <a href="/shop" class="mt-5 inline-flex h-11 items-center rounded-full bg-ink px-6 text-sm font-semibold text-white">Start shopping</a>
        </div>
    <?php else: ?>
        <ul class="mt-8 space-y-4" id="cart-lines">
            <?php foreach ($cart['items'] as $item):
                $label = $item['variant_label'] ?? trim(($item['size_label'] ?? '') . ' ' . ($item['color_label'] ?? '')); ?>
            <li class="flex gap-4 rounded-3xl border border-line bg-surface p-4" data-line-id="<?= (int)$item['cart_item_id'] ?>">
                <div class="h-20 w-20 shrink-0 rounded-2xl bg-stone-soft"></div>
                <div class="min-w-0 flex-1">
                    <a href="/product/<?= e($item['slug']) ?>" class="font-semibold hover:underline"><?= e($item['product_name']) ?></a>
                    <p class="text-sm text-muted-ink"><?= $label !== '' ? e($label) . ' · ' : '' ?><span class="tabular-nums"><?= e($item['sku']) ?></span></p>
                    <div class="mt-2 flex items-center gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            <span class="text-muted-ink">Qty</span>
                            <input type="number" min="1" max="99" value="<?= (int)$item['quantity'] ?>" data-qty-input
                                   class="h-10 w-16 rounded-xl border border-line bg-canvas px-2 tabular-nums">
                        </label>
                        <form method="post" action="/cart" class="inline" data-remove-form>
                            <input type="hidden" name="do" value="remove">
                            <input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>">
                            <?= csrf_field() ?>
                            <button type="submit" data-remove-line="<?= (int)$item['cart_item_id'] ?>"
                                    class="text-sm font-semibold text-danger hover:underline">Remove</button>
                        </form>
                        <?php if ($item['available'] !== null && (int)$item['available'] < (int)$item['quantity']): ?>
                            <span class="text-xs font-semibold text-warning">Only <?= (int)$item['available'] ?> in stock</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="shrink-0 font-bold tabular-nums"><?= money_fmt($item['line_total_cents']) ?></p>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="mt-8 rounded-3xl border border-line bg-surface p-6">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-muted-ink">Subtotal</dt><dd class="font-semibold tabular-nums" data-subtotal><?= money_fmt($cart['subtotal_cents']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-muted-ink">Shipping</dt><dd class="tabular-nums">Calculated at checkout</dd></div>
            </dl>
            <a href="/checkout" class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-full bg-ink text-base font-semibold text-white hover:bg-ink/90">Checkout</a>
        </div>
    <?php endif; ?>
</div>
<?php });
