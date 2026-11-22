<?php
// admin/categories.php — category management with images (FR-ADM-CAT-001..004).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

/** Category + all of its descendants (prevents parent cycles in the edit form). */
function category_descendant_ids(int $rootId): array
{
    $ids = [$rootId => true];
    $frontier = [$rootId];
    while ($frontier !== []) {
        $in = implode(',', array_fill(0, count($frontier), '?'));
        $st = db()->prepare("SELECT id FROM categories WHERE parent_id IN ($in) AND deleted_at IS NULL");
        $st->execute(array_values($frontier));
        $next = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $child) {
            $child = (int)$child;
            if (!isset($ids[$child])) {
                $ids[$child] = true;
                $next[] = $child;
            }
        }
        $frontier = $next;
    }
    return $ids;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'categories.edit');

    $do = (string)($_POST['do'] ?? '');
    $editId = int_in($_POST['id'] ?? null);
    $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 120));
    $parentId = int_in($_POST['parent_id'] ?? null);
    $desc = trim(mb_substr((string)($_POST['description'] ?? ''), 0, 500));
    $status = (($_POST['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';
    $removeImage = (($_POST['remove_image'] ?? '') === '1');

    if (($do === 'create' || $do === 'update') && $name === '') {
        $_SESSION['flash_error'] = 'Category name is required.';
        safe_redirect($do === 'update' && $editId ? '/admin/categories?action=edit&id=' . $editId : '/admin/categories');
    }

    // Never let a category become its own parent (or a child of its descendants).
    if ($parentId !== null) {
        $parentCheck = db()->prepare('SELECT id FROM categories WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $parentCheck->execute([':id' => $parentId]);
        $blocked = category_descendant_ids($editId ?? 0);
        if (!$parentCheck->fetch() || isset($blocked[$parentId])) {
            $parentId = null;
        }
    }

    // Store a new image first so a bad file never half-saves the category.
    $newImage = null;
    if (!empty($_FILES['image']['name'])) {
        try {
            $newImage = image_upload_store($_FILES['image'], 'categories');
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            safe_redirect($do === 'update' && $editId ? '/admin/categories?action=edit&id=' . $editId : '/admin/categories');
        }
    }

    if ($do === 'create' && $name !== '') {
        $slug = slugify($name);
        $dupe = db()->prepare('SELECT id FROM categories WHERE slug = :s');
        $dupe->execute([':s' => $slug]);
        if ($dupe->fetch()) { $slug .= '-' . bin2hex(random_bytes(2)); }
        try {
            $st = db()->prepare(
                'INSERT INTO categories (parent_id, name, slug, description, image_path, status)
                 VALUES (:p, :n, :s, :d, :img, :status)'
            );
            $st->execute([
                ':p' => $parentId,
                ':n' => $name,
                ':s' => $slug,
                ':d' => $desc ?: null,
                ':img' => $newImage,
                ':status' => $status,
            ]);
        } catch (Throwable $e) {
            if ($newImage !== null) { image_upload_delete($newImage); }
            throw $e;
        }
        audit('category_created', 'category', (int)db()->lastInsertId(), null, ['name' => $name]);
        $_SESSION['flash_success'] = $newImage !== null
            ? 'Category created with image.'
            : 'Category created.';
        safe_redirect('/admin/categories');
    }

    if ($do === 'update' && $editId) {
        $st = db()->prepare('SELECT * FROM categories WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $editId]);
        $existing = $st->fetch();
        if (!$existing) {
            if ($newImage !== null) { image_upload_delete($newImage); }
            $_SESSION['flash_error'] = 'Category not found.';
            safe_redirect('/admin/categories');
        }

        $oldImage = trim((string)($existing['image_path'] ?? ''));
        $imagePath = $oldImage !== '' ? $oldImage : null;
        if ($newImage !== null) {
            $imagePath = $newImage;
        } elseif ($removeImage) {
            $imagePath = null;
        }

        try {
            $upd = db()->prepare(
                'UPDATE categories
                 SET parent_id = :p, name = :n, description = :d, image_path = :img, status = :status
                 WHERE id = :id AND deleted_at IS NULL'
            );
            $upd->execute([
                ':p' => $parentId,
                ':n' => $name,
                ':d' => $desc ?: null,
                ':img' => $imagePath,
                ':status' => $status,
                ':id' => $editId,
            ]);
        } catch (Throwable $e) {
            if ($newImage !== null) { image_upload_delete($newImage); }
            throw $e;
        }

        if ($oldImage !== '' && $oldImage !== $imagePath) {
            image_upload_delete($oldImage);
        }
        audit('category_updated', 'category', $editId, null, ['name' => $name]);
        $_SESSION['flash_success'] = $newImage !== null
            ? 'Category updated with a new image.'
            : 'Category updated.';
        safe_redirect('/admin/categories');
    }

    safe_redirect('/admin/categories');
}

$action = (string)($_GET['action'] ?? 'list');
$editId = int_in($_GET['id'] ?? null);

admin_layout('Categories', function () use ($ADMIN, $action, $editId) {
    $canEdit = admin_user_can($ADMIN, 'categories.edit');
    $cats = db()->query(
        'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
         FROM categories c WHERE c.deleted_at IS NULL ORDER BY c.display_order, c.name'
    )->fetchAll();

    $editCat = null;
    if ($action === 'edit' && $editId && $canEdit) {
        $st = db()->prepare('SELECT * FROM categories WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $editId]);
        $editCat = $st->fetch() ?: null;
    }
    $parentOptions = [];
    $blocked = $editCat ? category_descendant_ids((int)$editCat['id']) : [];
    foreach ($cats as $c) {
        if ($editCat && isset($blocked[(int)$c['id']])) {
            continue;
        }
        $parentOptions[] = $c;
    }

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Categories</h1>
    <p class="mt-1 text-sm text-muted-ink">Category images appear on the homepage category tiles and as the shop banner.</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div class="overflow-hidden rounded-2xl border border-line bg-surface">
            <?php if (!$cats): ?>
                <p class="p-8 text-center text-sm text-muted-ink">No categories yet.</p>
            <?php else: ?>
            <div class="divide-y divide-line">
                <?php foreach ($cats as $c): ?>
                <div class="flex items-center gap-3 p-3 sm:p-4">
                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-line bg-stone-soft">
                        <?php if (!empty($c['image_path'])): ?>
                            <img src="/uploads/<?= e($c['image_path']) ?>" alt="" class="h-full w-full object-cover">
                        <?php else: ?>
                            <span class="grid h-full w-full place-items-center text-lg font-bold text-muted-ink"><?= e(mb_substr($c['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold"><?= e($c['name']) ?></p>
                        <p class="text-xs text-muted-ink">/<?= e($c['slug']) ?> · <?= (int)$c['product_count'] ?> products<?= $c['parent_id'] ? ' · child category' : '' ?></p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $c['status'] === 'active' ? 'bg-success/10 text-success' : 'bg-line text-muted-ink' ?>"><?= e($c['status']) ?></span>
                    <?php if ($canEdit): ?>
                        <a href="/admin/categories?action=edit&id=<?= (int)$c['id'] ?>" class="inline-flex h-9 items-center rounded-full border border-line px-3 text-xs font-semibold hover:bg-canvas">Edit</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
        <form method="post" enctype="multipart/form-data"
              action="/admin/categories<?= $editCat ? '?action=edit&id=' . (int)$editCat['id'] : '' ?>"
              class="h-fit rounded-2xl border border-line bg-surface p-5">
            <input type="hidden" name="do" value="<?= $editCat ? 'update' : 'create' ?>">
            <?php if ($editCat): ?>
                <input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>">
            <?php endif; ?>
            <?= csrf_field() ?>
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-bold"><?= $editCat ? 'Edit category' : 'New category' ?></h2>
                <?php if ($editCat): ?>
                    <a href="/admin/categories" class="text-xs font-semibold text-muted-ink hover:text-ink">Cancel</a>
                <?php endif; ?>
            </div>

            <label for="name" class="mt-3 block text-sm font-semibold">Name</label>
            <input id="name" name="name" required maxlength="120" value="<?= e((string)($editCat['name'] ?? '')) ?>"
                   class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">

            <label for="parent_id" class="mt-3 block text-sm font-semibold">Parent <span class="font-normal text-muted-ink">(optional)</span></label>
            <select id="parent_id" name="parent_id" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                <option value="">— top level —</option>
                <?php foreach ($parentOptions as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)($editCat['parent_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="description" class="mt-3 block text-sm font-semibold">Description</label>
            <textarea id="description" name="description" rows="2" maxlength="500"
                      class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-3 py-2"><?= e((string)($editCat['description'] ?? '')) ?></textarea>

            <label for="status" class="mt-3 block text-sm font-semibold">Status</label>
            <select id="status" name="status" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                <option value="active" <?= ($editCat['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($editCat['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <label for="category-image" class="mt-3 block text-sm font-semibold">Category image <span class="font-normal text-muted-ink">(JPG/PNG/WebP, ≤5 MB)</span></label>
            <?php if (!empty($editCat['image_path'])): ?>
                <div class="mt-2 flex items-center gap-3 rounded-xl border border-line bg-canvas/40 p-2">
                    <img src="/uploads/<?= e($editCat['image_path']) ?>" alt="" class="h-14 w-14 rounded-lg object-cover">
                    <label class="flex items-center gap-2 text-xs font-semibold">
                        <input type="checkbox" name="remove_image" value="1" class="h-4 w-4 rounded border-line">
                        Remove image
                    </label>
                </div>
            <?php endif; ?>
            <input id="category-image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                   class="mt-2 block w-full text-sm">

            <button class="mt-4 h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90"><?= $editCat ? 'Save category' : 'Create category' ?></button>
        </form>
        <?php endif; ?>
    </div>
<?php });
