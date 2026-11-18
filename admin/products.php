<?php
// admin/products.php — product list + create/edit form (FR-ADM-PROD-001..007).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

$action = (string)($_GET['action'] ?? 'list');
$editId = int_in($_GET['id'] ?? null);

// ---------- POST actions (CSRF + permission checked) ----------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();

    $do = (string)($_POST['do'] ?? '');
    if ($do === 'save') {
        admin_require_permission($ADMIN, 'products.edit');

        $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 180));
        $sku  = strtoupper(trim(mb_substr((string)($_POST['sku'] ?? ''), 0, 64)));
        $price = (string)($_POST['price'] ?? '');
        $salePrice = trim((string)($_POST['sale_price'] ?? ''));
        $status = in_array(($_POST['status'] ?? ''), ['draft', 'active', 'archived'], true) ? (string)$_POST['status'] : 'draft';
        $categoryId = int_in($_POST['category_id'] ?? null);
        $shortDesc = trim(mb_substr((string)($_POST['short_description'] ?? ''), 0, 400));
        $desc = trim(mb_substr((string)($_POST['description'] ?? ''), 0, 20000));
        $madeToOrder = (($_POST['made_to_order'] ?? '') === '1') ? 1 : 0;
        $leadTime = int_in($_POST['lead_time_days'] ?? null, 1, 365);
        $featured = (($_POST['is_featured'] ?? '') === '1') ? 1 : 0;

        // Server-side validation (SECURITY.md §19.2).
        $valid = true;
        $errors = [];
        if ($name === '') { $errors['name'] = 'Name is required.'; $valid = false; }
        if (!preg_match('/^[A-Z0-9-]{2,64}$/', $sku)) { $errors['sku'] = 'SKU: 2-64 chars, A-Z 0-9 dash.'; $valid = false; }
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $price) || (float)$price <= 0) { $errors['price'] = 'Enter a valid price.'; $valid = false; }
        if ($salePrice !== '' && (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $salePrice) || (float)$salePrice >= (float)$price)) { $errors['sale_price'] = 'Sale price must be below regular price.'; $valid = false; }

        if (!$valid) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            safe_redirect('/admin/products' . ($editId ? "?action=edit&id=$editId" : '?action=new'));
        }

        $pdo = db();
        if ($editId) {
            $st = $pdo->prepare(
                'UPDATE products SET name = :name, sku = :sku, category_id = :cat, short_description = :sd,
                        description = :d, base_price = :price, sale_price = :sale, status = :status,
                        is_featured = :featured, made_to_order = :mto, lead_time_days = :lead
                 WHERE id = :id AND deleted_at IS NULL'
            );
            $st->execute([':name' => $name, ':sku' => $sku, ':cat' => $categoryId, ':sd' => $shortDesc ?: null,
                ':d' => $desc ?: null, ':price' => $price, ':sale' => $salePrice !== '' ? $salePrice : null,
                ':status' => $status, ':featured' => $featured, ':mto' => $madeToOrder, ':lead' => $leadTime, ':id' => $editId]);
            audit('product_updated', 'product', $editId, null, ['name' => $name, 'price' => $price, 'status' => $status]);
        } else {
            $slug0 = slugify($name);
            $dupe = $pdo->prepare('SELECT id FROM products WHERE slug = :s LIMIT 1');
            $dupe->execute([':s' => $slug0]);
            $slug0 = $dupe->fetch() ? $slug0 . '-' . bin2hex(random_bytes(2)) : $slug0;
            $st = $pdo->prepare(
                'INSERT INTO products (name, slug, sku, category_id, short_description, description,
                                       base_price, sale_price, status, is_featured, made_to_order, lead_time_days)
                 VALUES (:name, :slug, :sku, :cat, :sd, :d, :price, :sale, :status, :featured, :mto, :lead)'
            );
            $st->execute([':name' => $name, ':slug' => $slug0, ':sku' => $sku, ':cat' => $categoryId,
                ':sd' => $shortDesc ?: null, ':d' => $desc ?: null, ':price' => $price,
                ':sale' => $salePrice !== '' ? $salePrice : null, ':status' => $status,
                ':featured' => $featured, ':mto' => $madeToOrder, ':lead' => $leadTime]);
            $editId = (int)$pdo->lastInsertId();
            audit('product_created', 'product', $editId, null, ['name' => $name, 'price' => $price]);

            // Images uploaded at creation time — same hardened pipeline as the Images section.
            $files = $_FILES['images'] ?? null;
            $altText = mb_substr(trim((string)($_POST['alt_text'] ?? '')), 0, 255);
            if (is_array($files['name'] ?? null)) {
                $count = min(count($files['name']), 8);
                $first = true;
                for ($i = 0; $i < $count; $i++) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $one = [
                        'name' => $files['name'][$i], 'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ];
                    try {
                        $rel = image_upload_store($one, 'products');
                        $pdo->prepare('INSERT INTO product_images (product_id, file_path, alt_text, sort_order, is_primary)
                                       VALUES (:p, :f, :a, :s, :prim)')
                            ->execute([':p' => $editId, ':f' => $rel,
                                       ':a' => $altText !== '' ? $altText : $name,
                                       ':s' => $i, ':prim' => $first ? 1 : 0]);
                        $first = false;
                    } catch (RuntimeException $e) {
                        app_log('error', 'create image rejected: ' . $e->getMessage());
                    }
                }
            }

            // First variant: from the explicit fields, or a sensible default from the product.
            $vSku = strtoupper(trim(mb_substr((string)($_POST['variant_sku'] ?? ''), 0, 64)));
            if ($vSku === '') { $vSku = $sku; }
            $vSize = trim(mb_substr((string)($_POST['variant_size'] ?? ''), 0, 32)) ?: null;
            $vLength = trim(mb_substr((string)($_POST['variant_length'] ?? ''), 0, 32)) ?: null;
            $vColor = trim(mb_substr((string)($_POST['variant_color'] ?? ''), 0, 32)) ?: null;
            $vPrice = trim((string)($_POST['variant_price'] ?? ''));
            if ($vPrice === '' || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $vPrice)) { $vPrice = $price; }
            if (preg_match('/^[A-Z0-9-]{2,64}$/', $vSku)) {
                $pdo->prepare('INSERT INTO product_variants (product_id, sku, size_label, length_label, color_label, price)
                               VALUES (:p, :sku, :size, :length, :color, :price)')
                    ->execute([':p' => $editId, ':sku' => $vSku, ':size' => $vSize, ':length' => $vLength, ':color' => $vColor, ':price' => $vPrice]);
                $vid = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO inventory (variant_id, quantity_on_hand) VALUES (:v, 0)')
                    ->execute([':v' => $vid]);
                $pdo->prepare('INSERT INTO stock_movements (variant_id, change_qty, reason, user_id, note, created_at)
                               VALUES (:v, 0, :r, :u, :n, UTC_TIMESTAMP())')
                    ->execute([':v' => $vid, ':r' => 'initial', ':u' => (int)$ADMIN['id'], ':n' => 'Variant created with product']);
            }
        }
        $_SESSION['flash_success'] = 'Product saved.';
        safe_redirect('/admin/products?action=edit&id=' . $editId);
    }

    if ($do === 'upload_images' && $editId) {
        admin_require_permission($ADMIN, 'products.edit');
        $files = $_FILES['images'] ?? null;
        $saved = 0;
        $failed = 0;
        if (is_array($files['name'] ?? null)) {
            // Enforce a sane per-request cap (SECURITY.md §18).
            $count = min(count($files['name']), 8);
            $chk = db()->prepare('SELECT 1 FROM product_images WHERE product_id = :id LIMIT 1');
            $chk->execute([':id' => $editId]);
            $hasPrimary = (bool)$chk->fetchColumn();
            for ($i = 0; $i < $count; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $one = [
                    'name' => $files['name'][$i], 'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
                try {
                    $rel = image_upload_store($one, 'products');
                    db()->prepare('INSERT INTO product_images (product_id, file_path, alt_text, sort_order, is_primary)
                                   VALUES (:p, :f, :a, :s, :prim)')
                        ->execute([':p' => $editId, ':f' => $rel,
                                   ':a' => mb_substr(trim((string)($_POST['alt_text'] ?? '')), 0, 255) ?: null,
                                   ':s' => $i, ':prim' => (!$hasPrimary && $saved === 0) ? 1 : 0]);
                    $saved++;
                } catch (RuntimeException $e) {
                    $failed++;
                    app_log('error', 'image upload rejected: ' . $e->getMessage());
                }
            }
        }
        $_SESSION['flash_' . ($saved > 0 ? 'success' : 'error')] =
            $saved > 0 ? "$saved image(s) uploaded." . ($failed ? " $failed rejected." : "") : ($files ? 'No valid image found — JPG/PNG/WebP under 5 MB only.' : 'Choose image files first.');
        safe_redirect('/admin/products?action=edit&id=' . $editId);
    }

    if ($do === 'primary_image' && $editId) {
        admin_require_permission($ADMIN, 'products.edit');
        $imgId = int_in($_POST['image_id'] ?? null);
        if ($imgId) {
            $pdo = db();
            $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :p')->execute([':p' => $editId]);
            $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = :i AND product_id = :p')
                ->execute([':i' => $imgId, ':p' => $editId]);
            $_SESSION['flash_success'] = 'Primary image updated.';
        }
        safe_redirect('/admin/products?action=edit&id=' . $editId);
    }

    if ($do === 'delete_image' && $editId) {
        admin_require_permission($ADMIN, 'products.edit');
        $imgId = int_in($_POST['image_id'] ?? null);
        if ($imgId) {
            $st = db()->prepare('SELECT file_path FROM product_images WHERE id = :i AND product_id = :p');
            $st->execute([':i' => $imgId, ':p' => $editId]);
            $path = $st->fetchColumn();
            if ($path) {
                image_upload_delete((string)$path);
                db()->prepare('DELETE FROM product_images WHERE id = :i')->execute([':i' => $imgId]);
                $_SESSION['flash_success'] = 'Image removed.';
            }
        }
        safe_redirect('/admin/products?action=edit&id=' . $editId);
    }

    if ($do === 'delete' && $editId) {
        admin_require_permission($ADMIN, 'products.delete');
        // Soft delete (PROJECT_BRIEF §22).
        $st = db()->prepare('UPDATE products SET deleted_at = UTC_TIMESTAMP(), status = :s WHERE id = :id AND deleted_at IS NULL');
        $st->execute([':s' => 'archived', ':id' => $editId]);
        audit('product_deleted', 'product', $editId);
        $_SESSION['flash_success'] = 'Product archived.';
        safe_redirect('/admin/products');
    }

    if ($do === 'add_variant' && $editId) {
        admin_require_permission($ADMIN, 'products.edit');
        $vSku = strtoupper(trim(mb_substr((string)($_POST['variant_sku'] ?? ''), 0, 64)));
        $vPrice = (string)($_POST['variant_price'] ?? '');
        $vStock = int_in($_POST['variant_stock'] ?? null, 0, 1000000) ?? 0;
        $size = trim(mb_substr((string)($_POST['size_label'] ?? ''), 0, 32)) ?: null;
        $length = trim(mb_substr((string)($_POST['length_label'] ?? ''), 0, 32)) ?: null;
        $color = trim(mb_substr((string)($_POST['color_label'] ?? ''), 0, 32)) ?: null;

        if (preg_match('/^[A-Z0-9-]{2,64}$/', $vSku) && preg_match('/^\d{1,10}(\.\d{1,2})?$/', $vPrice) && (float)$vPrice > 0) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $ins = $pdo->prepare(
                    'INSERT INTO product_variants (product_id, sku, size_label, length_label, color_label, price)
                     VALUES (:p, :sku, :size, :length, :color, :price)'
                );
                $ins->execute([':p' => $editId, ':sku' => $vSku, ':size' => $size, ':length' => $length, ':color' => $color, ':price' => $vPrice]);
                $vid = (int)$pdo->lastInsertId();
                // Seed inventory row.
                $inv = $pdo->prepare('INSERT INTO inventory (variant_id, quantity_on_hand) VALUES (:v, :q)');
                $inv->execute([':v' => $vid, ':q' => $vStock]);
                if ($vStock > 0) {
                    $mv = $pdo->prepare(
                        'INSERT INTO stock_movements (variant_id, change_qty, reason, user_id, note, created_at)
                         VALUES (:v, :q, :reason, :u, :note, UTC_TIMESTAMP())'
                    );
                    $mv->execute([':v' => $vid, ':q' => $vStock, ':reason' => 'purchase', ':u' => $ADMIN['id'], ':note' => 'Initial stock']);
                }
                $pdo->commit();
                audit('variant_added', 'product', $editId, null, ['sku' => $vSku, 'stock' => $vStock]);
                $_SESSION['flash_success'] = 'Variant added.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'Variant SKU must be unique.';
            }
        } else {
            $_SESSION['flash_error'] = 'Check variant SKU and price.';
        }
        safe_redirect('/admin/products?action=edit&id=' . $editId);
    }
}

admin_layout('Products', function () use ($ADMIN, $action, $editId) {
    $canEdit = admin_user_can($ADMIN, 'products.edit');

    if ($action === 'new' || ($action === 'edit' && $editId)) {
        $product = null;
        $variants = [];
        if ($action === 'edit' && $editId) {
            $st = db()->prepare('SELECT * FROM products WHERE id = :id AND deleted_at IS NULL');
            $st->execute([':id' => $editId]);
            $product = $st->fetch() ?: null;
            if (!$product) { abort(404); }
            $vs = db()->prepare(
                'SELECT v.*, (i.quantity_on_hand - i.quantity_reserved) AS available
                 FROM product_variants v LEFT JOIN inventory i ON i.variant_id = v.id
                 WHERE v.product_id = :id AND v.deleted_at IS NULL ORDER BY v.id'
            );
            $vs->execute([':id' => $editId]);
            $variants = $vs->fetchAll();
        }
        $cats = db()->query(
            "SELECT id, name FROM categories WHERE status = 'active' AND deleted_at IS NULL ORDER BY name"
        )->fetchAll();
        admin_product_form($product, $variants, $cats, $canEdit);
        return;
    }

    // ---- List ----
    $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
    $page = max(1, int_in($_GET['page'] ?? 1, 1, 10000) ?? 1);
    $limit = 15;
    $offset = ($page - 1) * $limit;

    $where = 'deleted_at IS NULL';
    $params = [];
    if ($q !== '') {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $where .= ' AND (name LIKE :q OR sku LIKE :q)';
        $params[':q'] = "%$like%";
    }
    $st = db()->prepare("SELECT id, name, sku, base_price, sale_price, status, is_featured FROM products WHERE $where ORDER BY id DESC LIMIT :l OFFSET :o");
    foreach ($params as $k => $v) { $st->bindValue($k, $v, PDO::PARAM_STR); }
    $st->bindValue(':l', $limit, PDO::PARAM_INT);
    $st->bindValue(':o', $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();

    $cst = db()->prepare("SELECT COUNT(*) FROM products WHERE $where");
    $cst->execute($params);
    $total = (int)$cst->fetchColumn();

    admin_flash();
    ?>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Products</h1>
        <?php if ($canEdit): ?>
        <a href="/admin/products?action=new" class="inline-flex h-11 items-center rounded-full bg-accent px-5 text-sm font-semibold text-white hover:bg-accent/90">+ New product</a>
        <?php endif; ?>
    </div>

    <form method="get" action="/admin/products" class="mt-4 flex gap-3">
        <input type="hidden" name="action" value="list">
        <label class="sr-only" for="pq">Search</label>
        <input id="pq" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU&hellip;" maxlength="80"
               class="h-11 w-full max-w-sm rounded-xl border border-line bg-surface px-4 text-sm">
        <button class="h-11 rounded-xl bg-ink px-5 text-sm font-semibold text-white">Search</button>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <?php if (!$rows): ?>
            <p class="p-8 text-center text-sm text-muted-ink">No products yet.</p>
        <?php else: ?>
        <div class="divide-y divide-line">
            <?php foreach ($rows as $r): ?>
            <div class="flex flex-wrap items-center gap-3 p-4 sm:flex-nowrap">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold"><?= e($r['name']) ?></p>
                    <p class="text-xs text-muted-ink tabular-nums"><?= e($r['sku']) ?> · <?= money_fmt(money_to_cents((string)$r['base_price'])) ?>
                        <?php if ($r['sale_price']): ?> · sale <?= money_fmt(money_to_cents((string)$r['sale_price'])) ?><?php endif; ?>
                        <?php if ($r['is_featured']): ?> · <span class="font-semibold text-accent">Featured</span><?php endif; ?>
                    </p>
                </div>
                <?php $statusCls = ['active' => 'bg-success/10 text-success', 'draft' => 'bg-warning/10 text-warning', 'archived' => 'bg-line text-muted-ink'][$r['status']] ?? ''; ?>
                <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize <?= $statusCls ?>"><?= e($r['status']) ?></span>
                <?php if ($canEdit): ?>
                <a href="/admin/products?action=edit&id=<?= (int)$r['id'] ?>" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">Edit</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
});
