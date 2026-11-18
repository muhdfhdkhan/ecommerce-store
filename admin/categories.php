<?php
// admin/categories.php — category management (FR-ADM-CAT-001..003).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'categories.edit');

    $do = (string)($_POST['do'] ?? '');
    $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 120));
    $parentId = int_in($_POST['parent_id'] ?? null);
    $desc = trim(mb_substr((string)($_POST['description'] ?? ''), 0, 500));

    if ($do === 'create' && $name !== '') {
        $slug = slugify($name);
        $dupe = db()->prepare('SELECT id FROM categories WHERE slug = :s');
        $dupe->execute([':s' => $slug]);
        if ($dupe->fetch()) { $slug .= '-' . bin2hex(random_bytes(2)); }
        $st = db()->prepare(
            'INSERT INTO categories (parent_id, name, slug, description) VALUES (:p, :n, :s, :d)'
        );
        $st->execute([':p' => $parentId, ':n' => $name, ':s' => $slug, ':d' => $desc ?: null]);
        audit('category_created', 'category', (int)db()->lastInsertId(), null, ['name' => $name]);
        $_SESSION['flash_success'] = 'Category created.';
    }
    safe_redirect('/admin/categories');
}

admin_layout('Categories', function () use ($ADMIN) {
    $canEdit = admin_user_can($ADMIN, 'categories.edit');
    $cats = db()->query(
        'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
         FROM categories c WHERE c.deleted_at IS NULL ORDER BY c.display_order, c.name'
    )->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Categories</h1>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="overflow-hidden rounded-2xl border border-line bg-surface">
            <?php if (!$cats): ?>
                <p class="p-8 text-center text-sm text-muted-ink">No categories yet.</p>
            <?php else: ?>
            <div class="divide-y divide-line">
                <?php foreach ($cats as $c): ?>
                <div class="flex items-center gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold"><?= e($c['name']) ?></p>
                        <p class="text-xs text-muted-ink">/<?= e($c['slug']) ?> · <?= (int)$c['product_count'] ?> products</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $c['status'] === 'active' ? 'bg-success/10 text-success' : 'bg-line text-muted-ink' ?>"><?= e($c['status']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
        <form method="post" class="h-fit rounded-2xl border border-line bg-surface p-5">
            <input type="hidden" name="do" value="create">
            <?= csrf_field() ?>
            <h2 class="font-bold">New category</h2>
            <label for="name" class="mt-3 block text-sm font-semibold">Name</label>
            <input id="name" name="name" required maxlength="120" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
            <label for="parent_id" class="mt-3 block text-sm font-semibold">Parent <span class="font-normal text-muted-ink">(optional)</span></label>
            <select id="parent_id" name="parent_id" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3 text-sm">
                <option value="">— top level —</option>
                <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
            <label for="description" class="mt-3 block text-sm font-semibold">Description</label>
            <textarea id="description" name="description" rows="2" maxlength="500" class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-3 py-2"></textarea>
            <button class="mt-4 h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90">Create category</button>
        </form>
        <?php endif; ?>
    </div>
<?php });
