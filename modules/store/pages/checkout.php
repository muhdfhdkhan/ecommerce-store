<?php
// modules/store/pages/checkout.php — one-page checkout; totals computed server-side (§12.3).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

require_once BASE_PATH . '/modules/orders/cart_service.php';

$cart = cart_view();
if (empty($cart['items'])) {
    safe_redirect('/cart');
}

$errors = [];
$placed = null;

// Coupon preview: session stores the validated code so the summary can show it.
$appliedCoupon = null;
$discountCents = 0;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    require_once BASE_PATH . '/modules/orders/service.php';

    // "Apply coupon" posts only that field — validate, remember, redisplay.
    if (($_POST['apply_coupon'] ?? '') === '1') {
        $code = strtoupper(trim(mb_substr((string)($_POST['coupon_code'] ?? ''), 0, 32)));
        try {
            [, $discountCents] = coupon_validate($code !== '' ? $code : null, $cart['subtotal_cents']);
            if ($code !== '') {
                $_SESSION['applied_coupon'] = $code;
            } else {
                unset($_SESSION['applied_coupon']);
            }
        } catch (RuntimeException $e) {
            $errors['coupon'] = $e->getMessage();
            unset($_SESSION['applied_coupon']);
        }
    } else {
    $idem = (string)($_POST['idempotency_key'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $idem)) {
        // Tampered or expired key: reject safely.
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        [$ok, $result] = place_order(
            (int)($_SESSION['customer_id'] ?? 0),
            [
                'full_name' => (string)($_POST['full_name'] ?? ''),
                'email' => (string)($_POST['email'] ?? ''),
                'phone' => (string)($_POST['phone'] ?? ''),
                'payment_method' => (string)($_POST['payment_method'] ?? ''),
                'customer_note' => (string)($_POST['customer_note'] ?? ''),
                'shipping_address' => [
                    'full_name' => (string)($_POST['ship_name'] ?? ''),
                    'phone' => (string)($_POST['ship_phone'] ?? ''),
                    'line1' => (string)($_POST['ship_line1'] ?? ''),
                    'line2' => (string)($_POST['ship_line2'] ?? ''),
                    'city' => (string)($_POST['ship_city'] ?? ''),
                    'province' => (string)($_POST['ship_province'] ?? ''),
                    'postal' => (string)($_POST['ship_postal'] ?? ''),
                ],
            ],
            $idem
        );
        if ($ok) {
            $placed = $result;
        } else {
            $errors['form'] = is_string($result) ? $result : 'Could not place the order.';
        }
    }
    }
}

if ($placed !== null) {
    store_layout('Order confirmed', function () use ($placed) { ?>
<div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
    <div class="rounded-3xl border border-line bg-surface p-8 text-center sm:p-12">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-success/10 text-success">
            <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 10 18 20 6"/></svg>
        </div>
        <h1 class="mt-5 text-2xl font-bold tracking-tight">Thank you! Your order is in.</h1>
        <p class="mt-2 text-muted-ink">Order number</p>
        <p class="text-xl font-bold tabular-nums"><?= e($placed['order_number']) ?></p>
        <p class="mt-4 text-sm text-muted-ink">We will contact you to confirm. You can track this order from your account.</p>
        <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="/account/orders" class="inline-flex h-12 items-center justify-center rounded-full bg-ink px-6 font-semibold text-white">Track order</a>
            <a href="/shop" class="inline-flex h-12 items-center justify-center rounded-full border border-line px-6 font-semibold hover:bg-canvas">Continue shopping</a>
        </div>
    </div>
</div>
<?php });
    return;
}

$subtotal = $cart['subtotal_cents'];
$shipping = $subtotal >= 500000 ? 0 : 25000;
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
if ($appliedCoupon) {
    try {
        require_once BASE_PATH . '/modules/orders/service.php';
        [, $discountCents] = coupon_validate($appliedCoupon, $subtotal);
    } catch (RuntimeException) {
        unset($_SESSION['applied_coupon']);
        $appliedCoupon = null;
        $discountCents = 0;
    }
}
$total = max(0, $subtotal + $shipping - $discountCents);
$idem = bin2hex(random_bytes(16));

store_layout('Checkout', function () use ($cart, $subtotal, $shipping, $total, $idem, $errors, $appliedCoupon, $discountCents) { ?>
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:py-12">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Checkout</h1>

    <?php if (isset($errors['form'])): ?>
        <div role="alert" class="mt-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <form method="post" action="/checkout" class="mt-6 grid gap-8 lg:grid-cols-[1fr_360px]">
        <?= csrf_field() ?>
        <input type="hidden" name="idempotency_key" value="<?= e($idem) ?>">

        <div class="space-y-6">
            <section class="rounded-3xl border border-line bg-surface p-5 sm:p-6">
                <h2 class="font-bold">Contact</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="full_name" class="block text-sm font-semibold">Full name</label>
                        <input id="full_name" name="full_name" required maxlength="120" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-semibold">Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required maxlength="254" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-semibold">Phone</label>
                        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required maxlength="24" placeholder="03xx-xxxxxxx" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-line bg-surface p-5 sm:p-6">
                <h2 class="font-bold">Shipping address</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="ship_name" class="block text-sm font-semibold">Recipient name</label>
                        <input id="ship_name" name="ship_name" required maxlength="120" autocomplete="shipping name" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                    </div>
                    <div>
                        <label for="ship_phone" class="block text-sm font-semibold">Recipient phone</label>
                        <input id="ship_phone" name="ship_phone" type="tel" inputmode="tel" required maxlength="24" autocomplete="shipping tel" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                    </div>
                    <div class="sm:col-span-2"><label for="ship_line1" class="block text-sm font-semibold">Street address</label>
                        <input id="ship_line1" name="ship_line1" required maxlength="255" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4"></div>
                    <div class="sm:col-span-2"><label for="ship_line2" class="block text-sm font-semibold">Apartment, suite <span class="font-normal text-muted-ink">(optional)</span></label>
                        <input id="ship_line2" name="ship_line2" maxlength="255" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4"></div>
                    <div><label for="ship_city" class="block text-sm font-semibold">City</label>
                        <input id="ship_city" name="ship_city" required maxlength="80" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4"></div>
                    <div><label for="ship_province" class="block text-sm font-semibold">Province</label>
                        <input id="ship_province" name="ship_province" maxlength="80" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4"></div>
                    <div><label for="ship_postal" class="block text-sm font-semibold">Postal code <span class="font-normal text-muted-ink">(optional)</span></label>
                        <input id="ship_postal" name="ship_postal" inputmode="numeric" maxlength="16" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4"></div>
                </div>
            </section>

            <section class="rounded-3xl border border-line bg-surface p-5 sm:p-6">
                <h2 class="font-bold">Payment</h2>
                <div class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line p-4 has-[:checked]:border-accent has-[:checked]:bg-accent-soft/40">
                        <input type="radio" name="payment_method" value="cod" checked class="mt-1 h-4 w-4 accent-[#30351B]">
                        <span><span class="block font-semibold">Cash on delivery</span><span class="block text-sm text-muted-ink">Pay in cash when your parcel arrives.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line p-4 has-[:checked]:border-accent has-[:checked]:bg-accent-soft/40">
                        <input type="radio" name="payment_method" value="bank_transfer" class="mt-1 h-4 w-4 accent-[#30351B]">
                        <span><span class="block font-semibold">Bank transfer</span><span class="block text-sm text-muted-ink">Transfer to our account; we verify before dispatch.</span></span>
                    </label>
                </div>
            </section>

            <section class="rounded-3xl border border-line bg-surface p-5 sm:p-6">
                <h2 class="font-bold">Order note <span class="text-sm font-normal text-muted-ink">(optional)</span></h2>
                <textarea name="customer_note" maxlength="500" rows="3" class="mt-3 w-full rounded-xl border border-line bg-canvas/60 px-4 py-3"></textarea>
            </section>
        </div>

        <!-- Summary -->
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="rounded-3xl border border-line bg-surface p-6">
                <h2 class="font-bold">Order summary</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <?php foreach ($cart['items'] as $item): ?>
                    <li class="flex justify-between gap-3">
                        <span class="min-w-0"><span class="line-clamp-1 font-medium"><?= e($item['product_name']) ?></span>
                            <span class="text-muted-ink tabular-nums">&times; <?= (int)$item['quantity'] ?></span></span>
                        <span class="shrink-0 tabular-nums"><?= money_fmt($item['line_total_cents']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <dl class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted-ink">Subtotal</dt><dd class="tabular-nums"><?= money_fmt($subtotal) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-muted-ink">Shipping</dt><dd class="tabular-nums"><?= $shipping === 0 ? 'Free' : money_fmt($shipping) ?></dd></div>
                    <?php if ($appliedCoupon): ?>
                    <div class="flex justify-between text-success"><dt>Discount (<?= e($appliedCoupon) ?>)</dt><dd class="tabular-nums">&minus;<?= money_fmt($discountCents) ?></dd></div>
                    <?php endif; ?>
                    <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt>Total</dt><dd class="tabular-nums"><?= money_fmt($total) ?></dd></div>
                </dl>

                <!-- Coupon -->
                <?php if (isset($errors['coupon'])): ?>
                    <p role="alert" class="mt-3 rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger"><?= e($errors['coupon']) ?></p>
                <?php endif; ?>
                <div class="mt-4 flex gap-2 border-t border-line pt-4">
                    <label class="sr-only" for="coupon_code">Coupon code</label>
                    <input id="coupon_code" name="coupon_code" maxlength="32" placeholder="Coupon code" value="<?= e($appliedCoupon ?? '') ?>"
                           class="h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm uppercase">
                    <button type="submit" name="apply_coupon" value="1" class="h-11 shrink-0 rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">Apply</button>
                </div>

                <button type="submit" class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-full bg-ink text-base font-semibold text-white hover:bg-ink/90">Place order</button>
                <p class="mt-3 text-center text-xs text-muted-ink">By placing the order you agree to our terms and return policy.</p>
            </div>
        </aside>
    </form>
</div>
<?php });
