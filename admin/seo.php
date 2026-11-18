<?php
// admin/seo.php — per-entity meta title/description editing (Phase 7: SEO management).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'seo.manage');

    $do = (string)($_POST['do'] ?? '');
    $id = int_in($_POST['entity_id'] ?? null);
    $metaTitle = mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 160) ?: null;
    $metaDesc = mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 300) ?: null;

    if ($id) {
        match ($do) {
            'product' => db()->prepare('UPDATE products SET meta_title = :mt, meta_description = :md WHERE id = :id')
                ->execute([':mt' => $metaTitle, ':md' => $metaDesc, ':id' => $id]),
            'category' => db()->prepare('UPDATE categories SET meta_title = :mt, meta_description = :md WHERE id = :id')
                ->execute([':mt' => $metaTitle, ':md' => $metaDesc, ':id' => $id]),
            'page' => db()->prepare('UPDATE pages SET meta_title = :mt, meta_description = :md WHERE id = :id')
                ->execute([':mt' => $metaTitle, ':md' => $metaDesc, ':id' => $id]),
            default => null,
        };
        if ($do !== 'unknown') {
            audit('seo_updated', $do, $id);
            $_SESSION['flash_success'] = 'SEO fields saved.';
        }
    }
    safe_redirect('/admin/seo?type=' . urlencode($do ?: 'product'));
}

admin_layout('SEO', function () use ($ADMIN) {
    $canManage = admin_user_can($ADMIN, 'seo.manage');
    $type = in_array(($_GET['type'] ?? ''), ['product', 'category', 'page'], true) ? (string)$_GET['type'] : 'product';

    $rows = match ($type) {
        'category' => db()->query(
            "SELECT id, name AS label, CONCAT('/shop?category=', id) AS url, meta_title, meta_description
             FROM categories WHERE deleted_at IS NULL ORDER BY name LIMIT 100"
        )->fetchAll(),
        'page' => db()->query(
            "SELECT id, title AS label, CONCAT('/page/', slug) AS url, meta_title, meta_description
             FROM pages ORDER BY id DESC LIMIT 100"
        )->fetchAll(),
        default => db()->query(
            "SELECT id, name AS label, CONCAT('/product/', slug) AS url, meta_title, meta_description
             FROM products WHERE deleted_at IS NULL AND status = 'active' ORDER BY name LIMIT 100"
        )->fetchAll(),
    };

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">SEO management</h1>
    <p class="mt-1 text-sm text-muted-ink">Meta titles ≤ 60 characters and descriptions ≤ 160 rank best; longer values are trimmed at render.</p>

    <div class="mt-4 flex gap-2">
        <?php foreach (['product' => 'Products', 'category' => 'Categories', 'page' => 'Pages'] as $t => $label): ?>
        <a href="/admin/seo?type=<?= $t ?>" class="inline-flex h-11 items-center rounded-xl px-5 text-sm font-semibold <?= $type === $t ? 'bg-ink text-white' : 'border border-line hover:bg-canvas' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <div class="divide-y divide-line">
            <?php foreach ($rows as $r): ?>
            <details class="p-4">
                <summary class="flex cursor-pointer flex-wrap items-center gap-3 text-sm">
                    <span class="font-semibold"><?= e($r['label']) ?></span>
                    <span class="text-xs text-muted-ink"><?= e($r['url']) ?></span>
                    <?php if (empty($r['meta_title']) && empty($r['meta_description'])): ?>
                        <span class="rounded-full bg-warning/10 px-3 py-1 text-xs font-semibold text-warning">Missing</span>
                    <?php endif; ?>
                </summary>
                <?php if ($canManage): ?>
                <form method="post" class="mt-3 grid gap-3 rounded-xl border border-line bg-canvas/40 p-4 sm:grid-cols-[1fr_140px]">
                    <input type="hidden" name="do" value="<?= $type ?>">
                    <input type="hidden" name="entity_id" value="<?= (int)$r['id'] ?>">
                    <?= csrf_field() ?>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-muted-ink">Meta title</label>
                        <input name="meta_title" maxlength="160" value="<?= e((string)$r['meta_title']) ?>"
                               class="mt-1 h-11 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-muted-ink">Meta description</label>
                        <textarea name="meta_description" rows="2" maxlength="300"
                                  class="mt-1 w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm"><?= e((string)$r['meta_description']) ?></textarea>
                    </div>
                    <button class="h-11 rounded-xl bg-ink px-5 text-sm font-semibold text-white sm:col-start-2">Save</button>
                </form>
                <?php endif; ?>
            </details>
            <?php endforeach; ?>
            <?php if (!$rows): ?><p class="p-8 text-center text-sm text-muted-ink">Nothing to optimise yet.</p><?php endif; ?>
        </div>
    </div>
<?php });
