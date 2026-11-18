<?php
// admin/settings.php — store identity settings (MVP: store info, currency, order prefix).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'settings.edit');

    $keys = ['store_name', 'store_email', 'store_phone', 'store_address', 'bank_details', 'cod_fee'];
    foreach ($keys as $k) {
        $val = trim(mb_substr((string)($_POST[$k] ?? ''), 0, 500));
        $st = db()->prepare(
            'INSERT INTO store_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $st->execute([':k' => $k, ':v' => $val]);
    }
    audit('settings_updated', 'store_settings', null, null, ['keys' => $keys]);
    $_SESSION['flash_success'] = 'Settings saved.';
    safe_redirect('/admin/settings');
}

admin_layout('Settings', function () use ($ADMIN) {
    $canEdit = admin_user_can($ADMIN, 'settings.edit');

    $st = db()->prepare('SELECT setting_key, setting_value FROM store_settings');
    $st->execute();
    $settings = [];
    foreach ($st->fetchAll() as $row) {
        $settings[$row['setting_key']] = (string)$row['setting_value'];
    }

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Store settings</h1>

    <form method="post" class="mt-6 max-w-2xl space-y-6">
        <?= csrf_field() ?>
        <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
            <h2 class="font-bold">Store identity</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="store_name" class="block text-sm font-semibold">Store name</label>
                    <input id="store_name" name="store_name" maxlength="120" value="<?= e($settings['store_name'] ?? 'Patches&Motifs') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div>
                    <label for="store_email" class="block text-sm font-semibold">Contact email</label>
                    <input id="store_email" name="store_email" type="email" maxlength="254" value="<?= e($settings['store_email'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div>
                    <label for="store_phone" class="block text-sm font-semibold">Contact phone</label>
                    <input id="store_phone" name="store_phone" maxlength="24" value="<?= e($settings['store_phone'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div class="sm:col-span-2">
                    <label for="store_address" class="block text-sm font-semibold">Business address</label>
                    <input id="store_address" name="store_address" maxlength="500" value="<?= e($settings['store_address'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                </div>
                <div class="sm:col-span-2">
                    <label for="bank_details" class="block text-sm font-semibold">Bank transfer instructions <span class="font-normal text-muted-ink">(shown at checkout)</span></label>
                    <textarea id="bank_details" name="bank_details" rows="3" maxlength="500" <?= $canEdit ? '' : 'disabled' ?>
                              class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-4 py-3"><?= e($settings['bank_details'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="cod_fee" class="block text-sm font-semibold">COD fee (PKR, 0 = none)</label>
                    <input id="cod_fee" name="cod_fee" inputmode="decimal" value="<?= e($settings['cod_fee'] ?? '0') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
                </div>
            </div>
        </section>

        <?php if ($canEdit): ?>
        <button type="submit" class="inline-flex h-12 items-center rounded-full bg-accent px-7 font-semibold text-white hover:bg-accent/90">Save settings</button>
        <?php endif; ?>
    </form>
<?php });
