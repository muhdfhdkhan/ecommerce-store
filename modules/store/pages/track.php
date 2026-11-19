<?php
// modules/store/pages/track.php — public order tracking.
// SECURITY.md §12.3 (never trust client input), §10 (rate limiting), §7 (CSRF).
// An order is only shown when the order number AND the email/phone on the order match,
// and repeated failures are throttled per IP so numbers cannot be enumerated.
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

/** Digits only, for tolerant phone comparison. */
function track_digits(string $value): string
{
    return (string)preg_replace('/\D+/', '', $value);
}

function track_status_label(string $status): string
{
    return [
        'pending_confirmation' => 'Pending confirmation',
        'confirmed'            => 'Confirmed',
        'processing'           => 'Being prepared',
        'dispatched'           => 'Dispatched',
        'delivered'            => 'Delivered',
        'cancelled'            => 'Cancelled',
        'on_hold'              => 'On hold',
        'returned'             => 'Returned',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/** Ordered progress steps used for the timeline (terminal states are shown separately). */
function track_steps(): array
{
    return ['pending_confirmation', 'confirmed', 'processing', 'dispatched', 'delivered'];
}

$number  = strtoupper(mb_substr(trim((string)($_GET['number'] ?? '')), 0, 32));
$contact = '';
$order   = null;
$items   = [];
$history = [];
$error   = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();

    $number  = strtoupper(mb_substr(trim((string)($_POST['order_number'] ?? '')), 0, 32));
    $contact = mb_substr(trim((string)($_POST['contact'] ?? '')), 0, 254);
    $honeypot = trim((string)($_POST['website'] ?? ''));

    if ($honeypot !== '' || $number === '' || $contact === '') {
        $error = 'Please enter your order number and the email or phone used at checkout.';
    } elseif (!rate_limit_ok('order_track', 'ip:' . client_ip(), 10, 900)) {
        http_response_code(429);
        $error = 'Too many lookups. Please wait a few minutes and try again.';
    } else {
        $st = db()->prepare(
            'SELECT id, order_number, full_name, email, phone, status, payment_status, payment_method,
                    subtotal, discount_total, shipping_total, grand_total, currency,
                    shipping_address, tracking_number, courier_name, created_at, updated_at
             FROM orders
             WHERE order_number = :n
             LIMIT 1'
        );
        $st->execute([':n' => $number]);
        $found = $st->fetch() ?: null;

        $matches = false;
        if ($found !== null) {
            if (strcasecmp((string)$found['email'], $contact) === 0) {
                $matches = true;
            } else {
                $given = track_digits($contact);
                $stored = track_digits((string)$found['phone']);
                if ($given !== '' && strlen($given) >= 7) {
                    $matches = ($given === $stored)
                        || (strlen($given) >= 10 && strlen($stored) >= 10 && substr($given, -10) === substr($stored, -10));
                }
            }
        }

        if (!$matches) {
            record_login_attempt('order_track', 'ip:' . client_ip(), false);
            audit('order_track_failed', 'order', $found !== null ? (int)$found['id'] : null, null, ['number' => $number]);
            $error = 'We could not find an order with those details. Check the order number and use the email or phone from your order confirmation.';
        } else {
            record_login_attempt('order_track', 'ip:' . client_ip(), true);
            $order = $found;

            $it = db()->prepare(
                'SELECT product_name, variant_label, sku, quantity, unit_price, line_total
                 FROM order_items WHERE order_id = :o ORDER BY id'
            );
            $it->execute([':o' => (int)$order['id']]);
            $items = $it->fetchAll();

            $hi = db()->prepare(
                'SELECT from_status, to_status, note, created_at
                 FROM order_status_history WHERE order_id = :o ORDER BY created_at, id'
            );
            $hi->execute([':o' => (int)$order['id']]);
            $history = $hi->fetchAll();
        }
    }
} elseif ($number !== '') {
    // Prefilled from /track?number=... — the contact field still has to be filled in.
    $contact = '';
}

store_layout('Track your order', function () use ($number, $contact, $order, $items, $history, $error) {
    $steps = track_steps();
    $current = $order['status'] ?? '';
    $isTerminal = in_array($current, ['cancelled', 'on_hold', 'returned'], true);
    $reached = $order !== null && !$isTerminal ? array_search($current, $steps, true) : -1;

    $address = [];
    if ($order !== null) {
        $decoded = json_decode((string)$order['shipping_address'], true);
        if (is_array($decoded)) {
            $address = $decoded;
        }
    }
    $city = trim((string)($address['city'] ?? ''));
    $statusClass = match ($current) {
        'delivered' => 'bg-success/10 text-success',
        'cancelled', 'returned' => 'bg-danger/10 text-danger',
        'on_hold' => 'bg-warning/10 text-warning',
        default => 'bg-gold-soft text-gold-deep',
    };
    ?>
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:py-16">
    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-gold-deep">Order tracking</p>
    <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Where is my order?</h1>
    <p class="mt-2 text-sm text-muted-ink">
        Enter your order number (it looks like <span class="font-mono text-ink">EMB-2026-000123</span>) and the email
        address or phone number you used at checkout.
    </p>

    <form method="post" action="/track" class="mt-8 rounded-3xl border border-gold/40 bg-surface p-5 shadow-[0_18px_50px_-30px_rgba(176,141,87,0.55)] sm:p-7">
        <?= csrf_field() ?>
        <div aria-hidden="true" class="absolute -left-[9999px] h-0 w-0 overflow-hidden">
            <label for="website">Website</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <?php if ($error !== null): ?>
        <div role="alert" class="mb-4 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="order_number" class="block text-sm font-semibold">Order number</label>
                <input id="order_number" name="order_number" required maxlength="32" value="<?= e($number) ?>"
                       placeholder="EMB-2026-000123" autocomplete="off"
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 font-mono uppercase">
            </div>
            <div>
                <label for="contact" class="block text-sm font-semibold">Email or phone</label>
                <input id="contact" name="contact" required maxlength="254" value="<?= e($contact) ?>"
                       placeholder="you@example.com" autocomplete="email"
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
        </div>
        <button type="submit" class="mt-5 inline-flex h-12 items-center rounded-full bg-ink px-7 text-sm font-semibold text-white transition hover:bg-gold-deep">
            Track order
        </button>
        <p class="mt-3 text-xs text-muted-ink">For your privacy we only show an order when both details match.</p>
    </form>

    <?php if ($order !== null): ?>
    <section class="mt-8 overflow-hidden rounded-3xl border border-gold/40 bg-surface">
        <div class="border-b border-gold/25 bg-gold-soft/60 p-5 sm:p-7">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-gold-deep">Order</p>
                    <p class="mt-1 font-mono text-lg font-bold tracking-tight"><?= e((string)$order['order_number']) ?></p>
                    <p class="mt-1 text-xs text-muted-ink">Placed <?= e(substr((string)$order['created_at'], 0, 10)) ?> &middot; <?= e(ucfirst((string)$order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Bank transfer')) ?></p>
                </div>
                <span class="rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wide <?= $statusClass ?>"><?= e(track_status_label((string)$current)) ?></span>
            </div>

            <?php if ($isTerminal): ?>
            <p class="mt-4 text-sm text-muted-ink">This order is marked <strong class="text-ink"><?= e(strtolower(track_status_label((string)$current))) ?></strong>. If that looks wrong, please contact us.</p>
            <?php else: ?>
            <ol class="mt-5 grid gap-2 sm:grid-cols-5" aria-label="Order progress">
                <?php foreach ($steps as $i => $step): $done = $i <= $reached; ?>
                <li class="flex items-center gap-2 sm:flex-col sm:items-start sm:gap-1.5">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-[11px] font-bold <?= $done ? 'bg-gold-deep text-white' : 'border border-line bg-surface text-muted-ink' ?>">
                        <?= $done ? '&#10003;' : ($i + 1) ?>
                    </span>
                    <span class="text-xs font-semibold <?= $done ? 'text-ink' : 'text-muted-ink' ?>"><?= e(track_status_label($step)) ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </div>

        <div class="grid gap-6 p-5 sm:p-7 lg:grid-cols-2">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-muted-ink">Delivery</h2>
                <p class="mt-2 text-sm font-semibold"><?= e((string)$order['full_name']) ?></p>
                <?php if ($city !== ''): ?><p class="text-sm text-muted-ink"><?= e($city) ?></p><?php endif; ?>
                <?php if (!empty($order['courier_name']) || !empty($order['tracking_number'])): ?>
                <dl class="mt-4 space-y-1.5 text-sm">
                    <?php if (!empty($order['courier_name'])): ?>
                    <div class="flex justify-between gap-3"><dt class="text-muted-ink">Courier</dt><dd class="font-semibold"><?= e((string)$order['courier_name']) ?></dd></div>
                    <?php endif; ?>
                    <?php if (!empty($order['tracking_number'])): ?>
                    <div class="flex justify-between gap-3"><dt class="text-muted-ink">Tracking #</dt><dd class="font-mono font-semibold"><?= e((string)$order['tracking_number']) ?></dd></div>
                    <?php endif; ?>
                </dl>
                <?php else: ?>
                <p class="mt-4 text-sm text-muted-ink">Courier details appear here once your parcel is dispatched.</p>
                <?php endif; ?>
            </div>

            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-muted-ink">Updates</h2>
                <ol class="mt-2 space-y-2.5 border-l border-gold/30 pl-4">
                    <?php if ($history): ?>
                        <?php foreach (array_reverse($history) as $h): ?>
                        <li class="relative text-sm">
                            <span aria-hidden="true" class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-gold-deep"></span>
                            <span class="font-semibold"><?= e(track_status_label((string)$h['to_status'])) ?></span>
                            <span class="block text-xs text-muted-ink tabular-nums"><?= e(substr((string)$h['created_at'], 0, 16)) ?> UTC</span>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-sm">
                            <span class="font-semibold"><?= e(track_status_label((string)$current)) ?></span>
                            <span class="block text-xs text-muted-ink tabular-nums"><?= e(substr((string)$order['created_at'], 0, 16)) ?> UTC</span>
                        </li>
                    <?php endif; ?>
                </ol>
            </div>
        </div>

        <div class="border-t border-line p-5 sm:p-7">
            <h2 class="text-sm font-bold uppercase tracking-wide text-muted-ink">Items</h2>
            <ul class="mt-3 divide-y divide-line">
                <?php foreach ($items as $item): ?>
                <li class="flex items-start justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold"><?= e((string)$item['product_name']) ?></p>
                        <p class="text-xs text-muted-ink">
                            <?= $item['variant_label'] ? e((string)$item['variant_label']) . ' &middot; ' : '' ?>
                            Qty <?= (int)$item['quantity'] ?> &middot; <span class="font-mono"><?= e((string)$item['sku']) ?></span>
                        </p>
                    </div>
                    <span class="text-sm font-semibold tabular-nums"><?= e(money_fmt(money_to_cents((string)$item['line_total']))) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (!$items): ?><li class="py-3 text-sm text-muted-ink">No items recorded for this order.</li><?php endif; ?>
            </ul>

            <dl class="mt-4 space-y-1.5 border-t border-line pt-4 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-muted-ink">Subtotal</dt><dd class="tabular-nums"><?= e(money_fmt(money_to_cents((string)$order['subtotal']))) ?></dd></div>
                <?php if ((float)$order['discount_total'] > 0): ?>
                <div class="flex justify-between gap-3"><dt class="text-muted-ink">Discount</dt><dd class="tabular-nums text-success">&minus;<?= e(money_fmt(money_to_cents((string)$order['discount_total']))) ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between gap-3"><dt class="text-muted-ink">Shipping</dt><dd class="tabular-nums"><?= (float)$order['shipping_total'] > 0 ? e(money_fmt(money_to_cents((string)$order['shipping_total']))) : 'Free' ?></dd></div>
                <div class="flex justify-between gap-3 border-t border-line pt-2 text-base font-bold"><dt>Total</dt><dd class="tabular-nums"><?= e(money_fmt(money_to_cents((string)$order['grand_total']))) ?></dd></div>
            </dl>
            <p class="mt-3 text-xs text-muted-ink">Payment: <?= e(ucfirst(str_replace('_', ' ', (string)$order['payment_status']))) ?></p>
        </div>
    </section>
    <?php elseif ($error === null): ?>
    <section class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-gold-deep">1. Confirm</p>
            <p class="mt-1.5 text-sm text-muted-ink">We confirm your order and check stock.</p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-gold-deep">2. Prepare</p>
            <p class="mt-1.5 text-sm text-muted-ink">Your patches and motifs are packed with care.</p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-gold-deep">3. Deliver</p>
            <p class="mt-1.5 text-sm text-muted-ink">Track the courier until it reaches your door.</p>
        </div>
    </section>
    <?php endif; ?>

    <p class="mt-8 text-sm text-muted-ink">
        Need a hand? <a href="/contact" class="font-semibold text-ink underline decoration-gold decoration-2 underline-offset-4">Contact us</a>
        with your order number and we will look into it.
    </p>
</div>
<?php });
