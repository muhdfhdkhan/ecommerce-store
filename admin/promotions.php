<?php
// admin/promotions.php — coupon management (Phase 6: promotions & coupons).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'promotions.manage');

    $do = (string)($_POST['do'] ?? '');

    if ($do === 'create') {
        $code  = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)($_POST['code'] ?? '')) ?? '');
        $type  = in_array(($_POST['type'] ?? ''), ['percent', 'fixed'], true) ? (string)$_POST['type'] : 'percent';
        $value = (string)($_POST['value'] ?? '');
        $min   = trim((string)($_POST['min_subtotal'] ?? '0'));
        $limit = int_in($_POST['usage_limit'] ?? null, 1, 1000000);
        $ends  = trim((string)($_POST['ends_at'] ?? ''));

        $valid = preg_match('/^[A-Z0-9]{3,32}$/', $code) === 1
            && preg_match('/^\d{1,8}(\.\d{1,2})?$/', $value) === 1 && (float)$value > 0
            && ($type === 'percent' ? (float)$value <= 90 : true)
            && ($min === '' || preg_match('/^\d{1,8}(\.\d{1,2})?$/', $min) === 1)
            && ($ends === '' || DateTimeImmutable::createFromFormat('Y-m-d', $ends) !== false);

        if (!$valid) {
            $_SESSION['flash_error'] = 'Check the coupon fields (code 3–32 A-Z/0-9; percent ≤ 90%).';
        } else {
            try {
                db()->prepare(
                    'INSERT INTO coupons (code, type, value, min_subtotal, usage_limit, ends_at, is_active)
                     VALUES (:c, :t, :v, :m, :l, :e, 1)'
                )->execute([
                    ':c' => $code, ':t' => $type, ':v' => $value,
                    ':m' => $min !== '' ? $min : '0', ':l' => $limit,
                    ':e' => $ends !== '' ? $ends . ' 23:59:59' : null,
                ]);
                audit('coupon_created', 'coupon', (int)db()->lastInsertId(), null, ['code' => $code, 'type' => $type, 'value' => $value]);
                $_SESSION['flash_success'] = "Coupon $code created.";
            } catch (Throwable) {
                $_SESSION['flash_error'] = 'A coupon with that code already exists.';
            }
        }
    }

    if ($do === 'toggle' || $do === 'delete') {
        $cid = int_in($_POST['coupon_id'] ?? null);
        if ($cid) {
            if ($do === 'toggle') {
                db()->prepare('UPDATE coupons SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $cid]);
                audit('coupon_toggled', 'coupon', $cid);
                $_SESSION['flash_success'] = 'Coupon status updated.';
            } else {
                db()->prepare('DELETE FROM coupons WHERE id = :id')->execute([':id' => $cid]);
                audit('coupon_deleted', 'coupon', $cid);
                $_SESSION['flash_success'] = 'Coupon deleted.';
            }
        }
    }
    safe_redirect('/admin/promotions');
}

admin_layout('Promotions', function () use ($ADMIN) {
    $canManage = admin_user_can($ADMIN, 'promotions.manage');
    $coupons = db()->query(
        'SELECT id, code, type, value, min_subtotal, usage_limit, used_count, ends_at, is_active
         FROM coupons ORDER BY id DESC LIMIT 100'
    )->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Promotions &amp; coupons</h1>
    <p class="mt-1 text-sm text-muted-ink">Coupons are validated server-side at checkout; usage counters increment inside the order transaction.</p>

    <div class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <div class="divide-y divide-line">
            <?php foreach ($coupons as $c): ?>
            <div class="flex flex-wrap items-center gap-3 p-4">
                <span class="font-mono font-bold tracking-wide"><?= e($c['code']) ?></span>
                <span class="rounded-full bg-canvas px-3 py-1 text-xs font-semibold text-muted-ink">
                    <?= $c['type'] === 'percent' ? e(rtrim(rtrim((string)$c['value'], '0'), '.')) . '% off' : money_fmt(money_to_cents((string)$c['value'])) . ' off' ?>
                </span>
                <span class="text-xs text-muted-ink tabular-nums">min <?= money_fmt(money_to_cents((string)$c['min_subtotal'])) ?>
                    · used <?= (int)$c['used_count'] ?><?= $c['usage_limit'] !== null ? '/' . (int)$c['usage_limit'] : '' ?>
                    <?= $c['ends_at'] ? '· until ' . e(substr((string)$c['ends_at'], 0, 10)) : '' ?></span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $c['is_active'] ? 'bg-success/10 text-success' : 'bg-line text-muted-ink' ?>"><?= $c['is_active'] ? 'Active' : 'Paused' ?></span>
                <?php if ($canManage): ?>
                <div class="ml-auto flex items-center gap-2">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="toggle">
                        <input type="hidden" name="coupon_id" value="<?= (int)$c['id'] ?>">
                        <button class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas"><?= $c['is_active'] ? 'Pause' : 'Activate' ?></button>
                    </form>
                    <form method="post" data-confirm>
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="delete">
                        <input type="hidden" name="coupon_id" value="<?= (int)$c['id'] ?>">
                        <button class="inline-flex h-10 items-center rounded-xl border border-danger/40 px-4 text-sm font-semibold text-danger hover:bg-danger/10">Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$coupons): ?><p class="p-8 text-center text-sm text-muted-ink">No coupons yet.</p><?php endif; ?>
        </div>
    </div>

    <?php if ($canManage): ?>
    <form method="post" class="mt-6 max-w-2xl rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <input type="hidden" name="do" value="create">
        <?= csrf_field() ?>
        <h2 class="font-bold">New coupon</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="code" class="block text-sm font-semibold">Code</label>
                <input id="code" name="code" required maxlength="32" placeholder="WELCOME10" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 font-mono uppercase">
            </div>
            <div>
                <label for="type" class="block text-sm font-semibold">Type</label>
                <select id="type" name="type" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-3">
                    <option value="percent">Percent off</option>
                    <option value="fixed">Fixed amount off</option>
                </select>
            </div>
            <div>
                <label for="value" class="block text-sm font-semibold">Value <?= e('(% or PKR)') ?></label>
                <input id="value" name="value" required inputmode="decimal" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
            </div>
            <div>
                <label for="min_subtotal" class="block text-sm font-semibold">Minimum subtotal (PKR)</label>
                <input id="min_subtotal" name="min_subtotal" inputmode="decimal" value="0" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
            </div>
            <div>
                <label for="usage_limit" class="block text-sm font-semibold">Total usage limit <span class="font-normal text-muted-ink">(optional)</span></label>
                <input id="usage_limit" name="usage_limit" type="number" min="1" max="1000000" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
            </div>
            <div>
                <label for="ends_at" class="block text-sm font-semibold">Ends on <span class="font-normal text-muted-ink">(optional)</span></label>
                <input id="ends_at" name="ends_at" type="date" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
        </div>
        <button type="submit" class="mt-5 inline-flex h-12 items-center rounded-full bg-accent px-7 font-semibold text-white hover:bg-accent/90">Create coupon</button>
    </form>
    <?php endif; ?>
<?php });
