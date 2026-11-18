<?php
// admin/shipping.php — zones & flat-rate methods (FR-SHIP-001..004).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'shipping.manage');

    $do = (string)($_POST['do'] ?? '');
    if ($do === 'add_method') {
        $zoneId = int_in($_POST['zone_id'] ?? null);
        $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 80));
        $fee = (string)($_POST['flat_fee'] ?? '0');
        $freeOver = trim((string)($_POST['free_over'] ?? ''));
        $days = trim(mb_substr((string)($_POST['estimate_days'] ?? ''), 0, 32));
        if ($zoneId && $name !== '' && preg_match('/^\d{1,8}(\.\d{1,2})?$/', $fee)) {
            $st = db()->prepare(
                'INSERT INTO shipping_methods (zone_id, name, flat_fee, free_over_amount, estimate_days)
                 VALUES (:z, :n, :f, :fo, :d)'
            );
            $st->execute([':z' => $zoneId, ':n' => $name, ':f' => $fee,
                          ':fo' => ($freeOver !== '' && preg_match('/^\d{1,8}(\.\d{1,2})?$/', $freeOver)) ? $freeOver : null,
                          ':d' => $days ?: null]);
            audit('shipping_method_added', 'shipping_method', (int)db()->lastInsertId());
            $_SESSION['flash_success'] = 'Shipping method added.';
        } else {
            $_SESSION['flash_error'] = 'Check the method name and fee.';
        }
    }
    if ($do === 'add_zone') {
        $name = trim(mb_substr((string)($_POST['zone_name'] ?? ''), 0, 80));
        $cities = trim(mb_substr((string)($_POST['cities'] ?? ''), 0, 2000));
        if ($name !== '') {
            $st = db()->prepare('INSERT INTO shipping_zones (name, cities) VALUES (:n, :c)');
            $st->execute([':n' => $name, ':c' => $cities ?: null]);
            audit('shipping_zone_added', 'shipping_zone', (int)db()->lastInsertId());
            $_SESSION['flash_success'] = 'Zone added.';
        }
    }
    safe_redirect('/admin/shipping');
}

admin_layout('Shipping', function () use ($ADMIN) {
    $canManage = admin_user_can($ADMIN, 'shipping.manage');
    $zones = db()->query(
        'SELECT z.*, (SELECT COUNT(*) FROM shipping_methods m WHERE m.zone_id = z.id AND m.is_active = 1) AS method_count
         FROM shipping_zones z WHERE z.is_active = 1 ORDER BY z.id'
    )->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Shipping</h1>
    <p class="mt-1 text-sm text-muted-ink">Flat-rate zones. Courier API integration arrives in a later phase.</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-4">
            <?php foreach ($zones as $z): ?>
            <div class="rounded-2xl border border-line bg-surface p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold"><?= e($z['name']) ?></h2>
                    <?php if ($z['is_default']): ?><span class="rounded-full bg-accent-soft px-3 py-1 text-xs font-semibold text-accent">Default</span><?php endif; ?>
                </div>
                <?php if (!empty($z['cities'])): ?><p class="mt-1 text-xs text-muted-ink">Cities: <?= e($z['cities']) ?></p><?php endif; ?>
                <?php
                $ms = db()->prepare('SELECT * FROM shipping_methods WHERE zone_id = :z ORDER BY id');
                $ms->execute([':z' => (int)$z['id']]);
                ?>
                <ul class="mt-3 space-y-2">
                    <?php foreach ($ms->fetchAll() as $m): ?>
                    <li class="flex items-center gap-3 rounded-xl border border-line p-3 text-sm">
                        <span class="font-semibold"><?= e($m['name']) ?></span>
                        <span class="text-muted-ink">flat</span>
                        <span class="font-semibold tabular-nums"><?= money_fmt(money_to_cents((string)$m['flat_fee'])) ?></span>
                        <?php if ($m['free_over_amount']): ?><span class="text-muted-ink">free over <?= money_fmt(money_to_cents((string)$m['free_over_amount'])) ?></span><?php endif; ?>
                        <?php if ($m['estimate_days']): ?><span class="text-muted-ink">· <?= e($m['estimate_days']) ?></span><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($canManage): ?>
        <div class="space-y-6">
            <form method="post" class="rounded-2xl border border-line bg-surface p-5">
                <input type="hidden" name="do" value="add_zone">
                <?= csrf_field() ?>
                <h2 class="font-bold">New zone</h2>
                <label class="mt-3 block text-sm font-semibold" for="zone_name">Name</label>
                <input id="zone_name" name="zone_name" required maxlength="80" placeholder="Pakistan" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                <label class="mt-3 block text-sm font-semibold" for="cities">Cities <span class="font-normal text-muted-ink">(comma separated, optional)</span></label>
                <input id="cities" name="cities" maxlength="2000" placeholder="Karachi, Lahore, Islamabad" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                <button class="mt-4 h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90">Add zone</button>
            </form>

            <form method="post" class="rounded-2xl border border-line bg-surface p-5">
                <input type="hidden" name="do" value="add_method">
                <?= csrf_field() ?>
                <h2 class="font-bold">New method</h2>
                <label class="mt-3 block text-sm font-semibold" for="zone_id">Zone</label>
                <select id="zone_id" name="zone_id" required class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                    <?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>"><?= e($z['name']) ?></option><?php endforeach; ?>
                </select>
                <label class="mt-3 block text-sm font-semibold" for="mname">Method name</label>
                <input id="mname" name="name" required maxlength="80" placeholder="Standard delivery" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                <label class="mt-3 block text-sm font-semibold" for="fee">Flat fee (PKR)</label>
                <input id="fee" name="flat_fee" required inputmode="decimal" value="250" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 tabular-nums">
                <label class="mt-3 block text-sm font-semibold" for="freeover">Free over amount <span class="font-normal text-muted-ink">(optional)</span></label>
                <input id="freeover" name="free_over" inputmode="decimal" placeholder="5000" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 tabular-nums">
                <label class="mt-3 block text-sm font-semibold" for="est">Estimate</label>
                <input id="est" name="estimate_days" maxlength="32" placeholder="2–4 days" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                <button class="mt-4 h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90">Add method</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
<?php });
