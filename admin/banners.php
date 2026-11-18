<?php
// admin/banners.php — hero slides + announcement bar (Phase 7: website management).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'cms.manage');

    $do = (string)($_POST['do'] ?? '');

    if ($do === 'create_banner') {
        $title = trim(mb_substr((string)($_POST['title'] ?? ''), 0, 120));
        $subtitle = mb_substr(trim((string)($_POST['subtitle'] ?? '')), 0, 255) ?: null;
        $link = mb_substr(trim((string)($_POST['link_url'] ?? '')), 0, 255) ?: null;
        $sort = int_in($_POST['sort_order'] ?? null, 0, 999) ?? 0;
        if ($title === '') {
            $_SESSION['flash_error'] = 'Title is required.';
        } elseif (empty($_FILES['image']['name'])) {
            $_SESSION['flash_error'] = 'Choose a slide image first.';
        } else {
            try {
                $rel = image_upload_store($_FILES['image'], 'hero');
                db()->prepare(
                    'INSERT INTO banners (title, image_path, subtitle, link_url, sort_order, is_active)
                     VALUES (:t, :img, :sub, :link, :s, 1)'
                )->execute([':t' => $title, ':img' => $rel, ':sub' => $subtitle, ':link' => $link, ':s' => $sort]);
                audit('banner_created', 'banner', (int)db()->lastInsertId(), null, ['title' => $title]);
                $_SESSION['flash_success'] = 'Hero slide added.';
            } catch (RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
        }
    }

    if ($do === 'replace_image') {
        $bid = int_in($_POST['banner_id'] ?? null);
        if ($bid && !empty($_FILES['image']['name'])) {
            try {
                $rel = image_upload_store($_FILES['image'], 'hero');
                $st = db()->prepare('SELECT image_path FROM banners WHERE id = :id');
                $st->execute([':id' => $bid]);
                $old = $st->fetchColumn();
                if ($old) { image_upload_delete((string)$old); }
                db()->prepare('UPDATE banners SET image_path = :img WHERE id = :id')->execute([':img' => $rel, ':id' => $bid]);
                audit('banner_image_updated', 'banner', $bid);
                $_SESSION['flash_success'] = 'Slide image replaced.';
            } catch (RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
        }
    }

    if ($do === 'toggle_banner') {
        $bid = int_in($_POST['banner_id'] ?? null);
        if ($bid) {
            db()->prepare('UPDATE banners SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $bid]);
            audit('banner_toggled', 'banner', $bid);
            $_SESSION['flash_success'] = 'Slide status updated.';
        }
    }

    if ($do === 'delete_banner') {
        $bid = int_in($_POST['banner_id'] ?? null);
        if ($bid) {
            $st = db()->prepare('SELECT image_path FROM banners WHERE id = :id');
            $st->execute([':id' => $bid]);
            $old = $st->fetchColumn();
            if ($old) { image_upload_delete((string)$old); }
            db()->prepare('DELETE FROM banners WHERE id = :id')->execute([':id' => $bid]);
            audit('banner_deleted', 'banner', $bid);
            $_SESSION['flash_success'] = 'Slide removed.';
        }
    }

    if ($do === 'save_announcement') {
        $text = trim(mb_substr((string)($_POST['announcement_text'] ?? ''), 0, 200));
        $active = int_in($_POST['announcement_active'] ?? null, 0, 1) ?? 0;
        $up = db()->prepare(
            'INSERT INTO store_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $up->execute([':k' => 'announcement_text', ':v' => $text]);
        $up->execute([':k' => 'announcement_active', ':v' => (string)$active]);
        audit('announcement_saved', 'store_settings', null, null, ['active' => $active]);
        $_SESSION['flash_success'] = 'Announcement saved.';
    }

    safe_redirect('/admin/banners');
}

admin_layout('Banners', function () use ($ADMIN) {
    $canManage = admin_user_can($ADMIN, 'cms.manage');
    $slides = db()->query('SELECT id, title, image_path, subtitle, link_url, sort_order, is_active FROM banners ORDER BY sort_order, id')->fetchAll();
    $settings = db()->query(
        "SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('announcement_text','announcement_active')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $annText = (string)($settings['announcement_text'] ?? '');
    $annActive = ($settings['announcement_active'] ?? '0') === '1';

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Banners &amp; announcements</h1>
    <p class="mt-1 text-sm text-muted-ink">Hero slides drive the homepage slider (5 max); the announcement bar shows on every store page.</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
            <?php foreach ($slides as $s): ?>
            <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-line bg-surface p-4">
                <div class="h-16 w-28 shrink-0 overflow-hidden rounded-xl bg-stone-soft">
                    <?php if (!empty($s['image_path'])): ?>
                        <img src="/uploads/<?= e($s['image_path']) ?>" alt="" class="h-full w-full object-cover">
                    <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold"><?= e($s['title']) ?></p>
                    <p class="truncate text-xs text-muted-ink"><?= e((string)$s['subtitle']) ?><?= $s['link_url'] ? ' · ' . e($s['link_url']) : '' ?> · sort <?= (int)$s['sort_order'] ?></p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $s['is_active'] ? 'bg-success/10 text-success' : 'bg-line text-muted-ink' ?>"><?= $s['is_active'] ? 'Live' : 'Hidden' ?></span>
                <?php if ($canManage): ?>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="post" enctype="multipart/form-data" class="flex items-center gap-2">
                        <input type="hidden" name="do" value="replace_image">
                        <input type="hidden" name="banner_id" value="<?= (int)$s['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="max-w-[150px] text-xs" aria-label="New image for <?= e($s['title']) ?>">
                        <button class="h-10 rounded-xl border border-line px-3 text-sm font-semibold hover:bg-canvas">Replace</button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="toggle_banner">
                        <input type="hidden" name="banner_id" value="<?= (int)$s['id'] ?>">
                        <button class="h-10 rounded-xl border border-line px-3 text-sm font-semibold hover:bg-canvas"><?= $s['is_active'] ? 'Hide' : 'Show' ?></button>
                    </form>
                    <form method="post" data-confirm>
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="delete_banner">
                        <input type="hidden" name="banner_id" value="<?= (int)$s['id'] ?>">
                        <button class="h-10 rounded-xl border border-danger/40 px-3 text-sm font-semibold text-danger hover:bg-danger/10">Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$slides): ?><p class="rounded-2xl border border-line bg-surface p-8 text-center text-sm text-muted-ink">No slides — the homepage shows defaults until you add one.</p><?php endif; ?>

            <?php if ($canManage): ?>
            <form method="post" enctype="multipart/form-data" class="rounded-2xl border border-line bg-surface p-5">
                <input type="hidden" name="do" value="create_banner">
                <?= csrf_field() ?>
                <h2 class="font-bold">New hero slide</h2>
                <label for="btitle" class="mt-3 block text-sm font-semibold">Title</label>
                <input id="btitle" name="title" required maxlength="120" placeholder="Patches that tell your story" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                <label for="bsub" class="mt-3 block text-sm font-semibold">Subtitle</label>
                <input id="bsub" name="subtitle" maxlength="255" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                <label for="blink" class="mt-3 block text-sm font-semibold">Link</label>
                <input id="blink" name="link_url" maxlength="255" placeholder="/shop" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="bsort" class="block text-sm font-semibold">Sort order</label>
                        <input id="bsort" name="sort_order" type="number" min="0" max="999" value="0" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
                    </div>
                    <div>
                        <label for="bimg" class="block text-sm font-semibold">Image <span class="font-normal text-muted-ink">(≤5 MB)</span></label>
                        <input id="bimg" type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="mt-2.5 w-full text-sm">
                    </div>
                </div>
                <button class="mt-4 h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90">Add slide</button>
            </form>
            <?php endif; ?>
        </div>

        <div>
            <?php if ($canManage): ?>
            <form method="post" class="rounded-2xl border border-line bg-surface p-5">
                <input type="hidden" name="do" value="save_announcement">
                <?= csrf_field() ?>
                <h2 class="font-bold">Announcement bar</h2>
                <label for="anntext" class="mt-3 block text-sm font-semibold">Text <span class="font-normal text-muted-ink">(leave empty to clear)</span></label>
                <input id="anntext" name="announcement_text" maxlength="200" value="<?= e($annText) ?>"
                       placeholder="Free shipping over Rs 5,000" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
                <label class="mt-3 flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="announcement_active" value="1" <?= $annActive ? 'checked' : '' ?> class="h-4 w-4 rounded border-line">
                    Show the bar
                </label>
                <button class="mt-4 h-11 w-full rounded-xl bg-ink font-semibold text-white">Save announcement</button>
            </form>
            <?php endif; ?>

            <section class="mt-6 rounded-2xl border border-line bg-surface p-5">
                <h2 class="font-bold">Email log</h2>
                <p class="mt-1 text-xs text-muted-ink">Transactional mail attempts (order confirmations, status updates).</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php $emails = db()->query('SELECT to_email, subject, event, status, created_at FROM email_log ORDER BY id DESC LIMIT 8')->fetchAll(); ?>
                    <?php foreach ($emails as $m): ?>
                    <li class="flex items-center gap-2 border-b border-line pb-2">
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $m['status'] === 'sent' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' ?>"><?= e($m['status']) ?></span>
                        <span class="min-w-0 flex-1 truncate"><?= e($m['subject']) ?><span class="block text-xs text-muted-ink"><?= e($m['to_email']) ?> · <?= e($m['created_at']) ?></span></span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$emails): ?><li class="text-sm text-muted-ink">No emails yet.</li><?php endif; ?>
                </ul>
            </section>
        </div>
    </div>
<?php });
