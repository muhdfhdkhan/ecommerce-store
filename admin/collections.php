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
        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            try {
                $imagePath = image_upload_store($_FILES['image'], 'collections');
            } catch (RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
        }
        $st = db()->prepare('INSERT INTO collections (title, slug, description, image_path) VALUES (:t, :s, :d, :img)');
        $st->execute([':t' => $title, ':s' => $slug, ':d' => $desc ?: null, ':img' => $imagePath]);
        audit('collection_created', 'collection', (int)db()->lastInsertId(), null, ['title' => $title]);
        if ($imagePath !== null) {
            $_SESSION['flash_success'] = 'Collection created with image.';
        } elseif (!isset($_SESSION['flash_error'])) {
            $_SESSION['flash_success'] = 'Collection created.';
        }
    }

    if ($do === 'upload_image') {
        $collId = int_in($_POST['collection_id'] ?? null);
        if ($collId && !empty($_FILES['image']['name'])) {
            try {
                $rel = image_upload_store($_FILES['image'], 'collections');
                $st = db()->prepare('SELECT image_path FROM collections WHERE id = :id');
                $st->execute([':id' => $collId]);
                $old = $st->fetchColumn();
                if ($old) { image_upload_delete((string)$old); }
                db()->prepare('UPDATE collections SET image_path = :img WHERE id = :id')
                    ->execute([':img' => $rel, ':id' => $collId]);
                audit('collection_image_updated', 'collection', $collId);
                $_SESSION['flash_success'] = 'Collection image updated.';
            } catch (RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = 'Choose an image first.';
        }
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
        <div class="space-y-4">
            <?php foreach ($colls as $c): ?>
            <div class="flex items-center gap-4 rounded-2xl border border-line bg-surface p-4">
                <div class="h-16 w-20 shrink-0 overflow-hidden rounded-xl bg-stone-soft">
                    <?php if (!empty($c['image_path'])): ?>
                        <img src="/uploads/<?= e($c['image_path']) ?>" alt="" class="h-full w-full object-cover">
                    <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold"><?= e($c['title']) ?></p>
                    <p class="text-xs text-muted-ink">/<?= e($c['slug']) ?> · <?= (int)$c['product_count'] ?> products · <?= e($c['status']) ?></p>
                </div>
                <?php if ($canEdit): ?>
                <form method="post" enctype="multipart/form-data" class="flex items-center gap-2">
                    <input type="hidden" name="do" value="upload_image">
                    <input type="hidden" name="collection_id" value="<?= (int)$c['id'] ?>">
                    <?= csrf_field() ?>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required
                           class="max-w-[180px] text-xs" aria-label="Image for <?= e($c['title']) ?>">
                    <button class="h-10 rounded-xl bg-ink px-4 text-sm font-semibold text-white">Set image</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$colls): ?><p class="rounded-2xl border border-line bg-surface p-8 text-center text-sm text-muted-ink">No collections yet.</p><?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
        <div class="space-y-6">
            <form method="post" enctype="multipart/form-data" class="rounded-2xl border border-line bg-surface p-5">
                <input type="hidden" name="do" value="create">
                <?= csrf_field() ?>
                <h2 class="font-bold">New collection</h2>
                <label for="title" class="mt-3 block text-sm font-semibold">Title</label>
                <input id="title" name="title" required maxlength="120" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                <label for="cdesc" class="mt-3 block text-sm font-semibold">Description</label>
                <textarea id="cdesc" name="description" rows="2" maxlength="400" class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-3 py-2"></textarea>
                <label for="cimg" class="mt-3 block text-sm font-semibold">Cover image <span class="font-normal text-muted-ink">(JPG/PNG/WebP, ≤5 MB)</span></label>
                <input id="cimg" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-1.5 w-full text-sm">
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
