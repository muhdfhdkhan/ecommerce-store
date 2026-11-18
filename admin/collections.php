<?php
// admin/collections.php — curated product groups (FR-ADM-COL-001..003).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'collections.edit');

    $do = (string)($_POST['do'] ?? '');
    $title = trim(mb_substr((string)($_POST['title'] ?? ''), 0, 120));
    $desc = trim(mb_substr((string)($_POST['description'] ?? ''), 0, 400));

    if ($do === 'create' && $title !== '') {
        $slug = slugify($title);
        $dupe = db()->prepare('SELECT id FROM collections WHERE slug = :s');
        $dupe->execute([':s' => $slug]);
        if ($dupe->fetch()) { $slug .= '-' . bin2hex(random_bytes(2)); }
        $st = db()->prepare('INSERT INTO collections (title, slug, description) VALUES (:t, :s, :d)');
        $st->execute([':t' => $title, ':s' => $slug, ':d' => $desc ?: null]);
        audit('collection_created', 'collection', (int)db()->lastInsertId(), null, ['title' => $title]);
        $_SESSION['flash_success'] = 'Collection created.';
    }

    if ($do === 'add_product') {
        $collId = int_in($_POST['collection_id'] ?? null);
        $prodId = int_in($_POST['product_id'] ?? null);
        if ($collId && $prodId) {
            $st = db()->prepare('INSERT IGNORE INTO collection_products (collection_id, product_id) VALUES (:c, :p)');
            $st->execute([':c' => $collId, ':p' => $prodId]);
            audit('collection_product_added', 'collection', $collId, null, ['product' => $prodId]);
            $_SESSION['flash_success'] = 'Product added to collection.';
        }
    }
    safe_redirect('/admin/collections');
}

admin_layout('Collections', function () use ($ADMIN) {
    $canEdit = admin_user_can($ADMIN, 'collections.edit');
    $colls = db()->query(
        'SELECT c.*, (SELECT COUNT(*) FROM collection_products cp WHERE cp.collection_id = c.id) AS product_count
         FROM collections c ORDER BY c.sort_order, c.id DESC'
    )->fetchAll();
    $products = db()->query("SELECT id, name, sku FROM products WHERE status = 'active' AND deleted_at IS NULL ORDER BY name LIMIT 200")->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Collections</h1>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="overflow-hidden rounded-2xl border border-line bg-surface">
            <?php if (!$colls): ?>
                <p class="p-8 text-center text-sm text-muted-ink">No collections yet.</p>
            <?php else: ?>
            <div class="divide-y divide-line">
                <?php foreach ($colls as $c): ?>
                <div class="flex items-center gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold"><?= e($c['title']) ?></p>
                        <p class="text-xs text-muted-ink">/<?= e($c['slug']) ?> · <?= (int)$c['product_count'] ?> products · <?= e($c['status']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
        <div class="space-y-6">
            <form method="post" class="rounded-2xl border border-line bg-surface p-5">
                <input type="hidden" name="do" value="create">
                <?= csrf_field() ?>
                <h2 class="font-bold">New collection</h2>
                <label for="title" class="mt-3 block text-sm font-semibold">Title</label>
                <input id="title" name="title" required maxlength="120" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                <label for="cdesc" class="mt-3 block text-sm font-semibold">Description</label>
                <textarea id="cdesc" name="description" rows="2" maxlength="400" class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-3 py-2"></textarea>
                <button class="mt-4 h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90">Create</button>
            </form>

            <form method="post" class="rounded-2xl border border-line bg-surface p-5">
                <input type="hidden" name="do" value="add_product">
                <?= csrf_field() ?>
                <h2 class="font-bold">Add product to collection</h2>
                <label class="mt-3 block text-sm font-semibold" for="collsel">Collection</label>
                <select id="collsel" name="collection_id" required class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                    <?php foreach ($colls as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
                </select>
                <label class="mt-3 block text-sm font-semibold" for="prodsel">Product</label>
                <select id="prodsel" name="product_id" required class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                    <?php foreach ($products as $pr): ?><option value="<?= (int)$pr['id'] ?>"><?= e($pr['name']) ?> (<?= e($pr['sku']) ?>)</option><?php endforeach; ?>
                </select>
                <button class="mt-4 h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90">Add</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
<?php });
