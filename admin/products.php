<?php
// admin/products.php — product list + create/edit form (FR-ADM-PROD-001..007).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';
require_once BASE_PATH . '/includes/product_import.php';

$action = (string)($_GET['action'] ?? 'list');
$editId = int_in($_GET['id'] ?? null);

// ---------- CSV sample download (no HTML layout) ----------
if ($action === 'import_sample') {
    admin_require_permission($ADMIN, 'products.edit');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="product-import-sample.csv"');
    echo product_import_sample_csv();
    exit;
}

// ---------- CSV preview page (GET after the upload POST redirect) ----------
$importPreview = null;
if ($action === 'import') {
    admin_require_permission($ADMIN, 'products.edit');
    $importPreview = $_SESSION['product_import'] ?? null;
    if (!is_array($importPreview) || empty($importPreview['rows']) || !is_array($importPreview['rows'])) {
        unset($_SESSION['product_import']);
        $_SESSION['flash_error'] = 'No CSV preview is open. Upload a CSV file first.';
        safe_redirect('/admin/products');
    }
}

// ---------- POST actions (CSRF + permission checked) ----------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // A body larger than post_max_size arrives empty, including the CSRF token.
    if ($_POST === [] && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $_SESSION['flash_error'] = 'That upload was too large for the server. Save fewer rows or images at a time.';
        safe_redirect('/admin/products');
    }

    csrf_verify();

    $do = (string)($_POST['do'] ?? '');

    // ---------- CSV import: parse and open the preview ----------
    if ($do === 'import_preview') {
        admin_require_permission($ADMIN, 'products.edit');
        try {
            $tmp = product_import_validate_upload($_FILES['csv'] ?? null);
            $parsed = product_import_read_csv($tmp);
            $ctx = product_import_context();
            $seenProduct = $ctx['product_skus'];
            $seenVariant = $ctx['variant_skus'];
            $rows = [];
            foreach ($parsed['rows'] as $raw) {
                $rows[] = product_import_normalize_row(
                    $raw['data'], $ctx, $seenProduct, $seenVariant, (int)$raw['line']
                );
            }
            $_SESSION['product_import'] = [
                'token' => bin2hex(random_bytes(16)),
                'file' => mb_substr(trim((string)($_FILES['csv']['name'] ?? 'products.csv')), 0, 120),
                'created_at' => time(),
                'rows' => $rows,
            ];
            $_SESSION['flash_success'] = count($rows) . ' row(s) read from the CSV. Review the preview and save.';
            safe_redirect('/admin/products?action=import');
        } catch (RuntimeException $e) {
            unset($_SESSION['product_import']);
            $_SESSION['flash_error'] = $e->getMessage();
            safe_redirect('/admin/products');
        }
    }

    // ---------- CSV import: save reviewed rows + images ----------
    if ($do === 'import_save') {
        admin_require_permission($ADMIN, 'products.edit');
        $preview = $_SESSION['product_import'] ?? null;
        $token = (string)($_POST['import_token'] ?? '');
        if (!is_array($preview) || empty($preview['rows']) || !is_string($preview['token'] ?? null)
            || !hash_equals($preview['token'], $token)) {
            unset($_SESSION['product_import']);
            $_SESSION['flash_error'] = 'The import preview expired. Upload the CSV again.';
            safe_redirect('/admin/products');
        }

        $ctx = product_import_context();
        $seenProduct = $ctx['product_skus'];
        $seenVariant = $ctx['variant_skus'];
        $rows = [];
        foreach ($preview['rows'] as $index => $stored) {
            $rows[$index] = product_import_normalize_row(
                product_import_row_from_post((int)$index, $stored, $_POST),
                $ctx,
                $seenProduct,
                $seenVariant,
                (int)($stored['line'] ?? ($index + 2))
            );
        }

        $result = product_import_save_rows($rows, $ctx, (int)$ADMIN['id']);

        // Keep unsaved rows so the admin can fix them and retry.
        $saved = array_flip($result['saved']);
        $errorsByLine = [];
        foreach ($result['errors'] as $rowError) {
            $errorsByLine[(int)$rowError['line']] = array_merge(
                $errorsByLine[(int)$rowError['line']] ?? [],
                $rowError['errors']
            );
        }
        $remaining = [];
        foreach ($rows as $index => $row) {
            if (isset($saved[$index])) {
                continue;
            }
            if (isset($errorsByLine[(int)$row['line']])) {
                $row['errors'] = array_values(array_unique(array_merge(
                    $row['errors'],
                    $errorsByLine[(int)$row['line']]
                )));
            }
            $remaining[] = $row;
        }

        if ($remaining !== []) {
            $_SESSION['product_import'] = [
                // New token: a stale preview tab must not overwrite the remaining rows.
                'token' => bin2hex(random_bytes(16)),
                'file' => (string)($preview['file'] ?? 'products.csv'),
                'created_at' => time(),
                'rows' => $remaining,
            ];
        } else {
            unset($_SESSION['product_import']);
        }

        audit('products_imported', 'product', null, null, [
            'file' => (string)($preview['file'] ?? 'products.csv'),
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'images' => $result['images'],
        ]);

        $message = $result['created'] . ' product(s) imported';
        if ($result['images'] > 0) {
            $message .= ', ' . $result['images'] . ' image(s) saved';
        }
        $message .= '.';
        $warningText = $result['warnings'] !== []
            ? ' ' . implode(' ', array_slice($result['warnings'], 0, 3))
            : '';
        if ($result['skipped'] > 0) {
            $_SESSION['flash_error'] = $message . ' ' . $result['skipped']
                . ' row(s) still need attention — fix the highlighted fields and save again.' . $warningText;
        } else {
            $_SESSION['flash_success'] = $message;
            if ($warningText !== '') {
                $_SESSION['flash_error'] = trim($warningText);
            }
        }
        safe_redirect($remaining !== [] ? '/admin/products?action=import' : '/admin/products');
    }

    // ---------- CSV import: discard the preview ----------
    if ($do === 'import_cancel') {
        admin_require_permission($ADMIN, 'products.edit');
        unset($_SESSION['product_import']);
        $_SESSION['flash_success'] = 'CSV import cancelled.';
        safe_redirect('/admin/products');
    }

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

admin_layout('Products', function () use ($ADMIN, $action, $editId, $importPreview) {
    $canEdit = admin_user_can($ADMIN, 'products.edit');

    // ---- CSV import preview (opened after a successful upload) ----
    if ($action === 'import' && is_array($importPreview)) {
        $importRows = $importPreview['rows'];
        $validCount = 0;
        foreach ($importRows as $importRow) {
            if ($importRow['errors'] === []) {
                $validCount++;
            }
        }
        $cats = db()->query(
            "SELECT id, name FROM categories WHERE status = 'active' AND deleted_at IS NULL ORDER BY name"
        )->fetchAll();
        $fileLimit = (int)ini_get('max_file_uploads');
        if ($fileLimit <= 0) {
            $fileLimit = 20;
        }
        ?>
        <div class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="import-title"
                 class="mx-auto w-full max-w-6xl overflow-hidden rounded-2xl border border-line bg-surface shadow-2xl">
                <div class="px-4 pt-4 sm:px-6"><?php admin_flash(); ?></div>
                <div class="sticky top-0 z-10 flex flex-wrap items-center gap-3 rounded-t-2xl border-b border-line bg-surface px-4 py-4 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <h1 id="import-title" class="text-lg font-bold tracking-tight">Import products from CSV</h1>
                        <p class="text-xs text-muted-ink">
                            <?= e((string)($importPreview['file'] ?? 'products.csv')) ?> ·
                            <?= count($importRows) ?> row(s) · <?= $validCount ?> ready to save
                        </p>
                    </div>
                    <form method="post" action="/admin/products?action=import">
                        <input type="hidden" name="do" value="import_cancel">
                        <?= csrf_field() ?>
                        <button type="submit" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">Cancel</button>
                    </form>
                </div>

                <form method="post" action="/admin/products?action=import" enctype="multipart/form-data">
                    <input type="hidden" name="do" value="import_save">
                    <input type="hidden" name="import_token" value="<?= e((string)($importPreview['token'] ?? '')) ?>">
                    <?= csrf_field() ?>

                    <div class="space-y-4 px-4 py-4 sm:px-6">
                        <p class="rounded-xl border border-line bg-canvas/60 px-4 py-3 text-xs text-muted-ink">
                            Each CSV row becomes one product with its first variant. Rows with red errors are skipped
                            automatically. Add up to <?= (int)PRODUCT_IMPORT_MAX_IMAGES ?> images per row (JPG/PNG/WebP,
                            under 5 MB each); the first image becomes the primary. The server accepts up to
                            <?= $fileLimit ?> files per save, so split very large image batches.
                        </p>

                        <?php foreach ($importRows as $i => $row): ?>
                        <?php $hasErrors = $row['errors'] !== []; ?>
                        <section data-import-row class="rounded-2xl border p-4 <?= $hasErrors ? 'border-danger/40 bg-danger/5' : 'border-line bg-canvas/30' ?>">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold">Row <?= (int)$i + 1 ?></span>
                                <span class="text-xs text-muted-ink">CSV row <?= (int)($row['line'] ?? 0) ?></span>
                                <?php if ($hasErrors): ?>
                                    <span class="ml-auto rounded-full bg-danger/10 px-3 py-1 text-[11px] font-semibold text-danger">Will be skipped</span>
                                <?php else: ?>
                                    <span class="ml-auto rounded-full bg-success/10 px-3 py-1 text-[11px] font-semibold text-success">Ready</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($hasErrors): ?>
                            <ul class="mt-2 space-y-1 text-xs text-danger">
                                <?php foreach ($row['errors'] as $rowError): ?>
                                    <li><?= e((string)$rowError) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <?php if ($row['warnings'] !== []): ?>
                            <ul class="mt-2 space-y-1 text-xs text-warning">
                                <?php foreach ($row['warnings'] as $rowWarning): ?>
                                    <li><?= e((string)$rowWarning) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-name-<?= (int)$i ?>">Product name</label>
                                    <input id="ir-name-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][name]" maxlength="180"
                                           value="<?= e((string)($row['name'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-sku-<?= (int)$i ?>">Product SKU</label>
                                    <input id="ir-sku-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][sku]" maxlength="64"
                                           value="<?= e((string)($row['sku'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 font-mono text-sm uppercase">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-vsku-<?= (int)$i ?>">Variant SKU</label>
                                    <input id="ir-vsku-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][variant_sku]" maxlength="64"
                                           value="<?= e((string)($row['variant_sku'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 font-mono text-sm uppercase">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-price-<?= (int)$i ?>">Price (PKR)</label>
                                    <input id="ir-price-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][price]" inputmode="decimal"
                                           value="<?= e((string)($row['price'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm tabular-nums">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-sale-<?= (int)$i ?>">Sale price</label>
                                    <input id="ir-sale-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][sale_price]" inputmode="decimal"
                                           value="<?= e((string)($row['sale_price'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm tabular-nums">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-stock-<?= (int)$i ?>">Stock</label>
                                    <input id="ir-stock-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][stock]" type="number" min="0" max="1000000"
                                           value="<?= (int)($row['stock'] ?? 0) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm tabular-nums">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-status-<?= (int)$i ?>">Status</label>
                                    <select id="ir-status-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][status]"
                                            class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                                        <?php foreach (['draft', 'active', 'archived'] as $statusOption): ?>
                                            <option value="<?= $statusOption ?>" <?= ($row['status'] ?? 'draft') === $statusOption ? 'selected' : '' ?>><?= ucfirst($statusOption) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-cat-<?= (int)$i ?>">Category</label>
                                    <select id="ir-cat-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][category_id]"
                                            class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                                        <option value="">— none —</option>
                                        <?php foreach ($cats as $cat): ?>
                                            <option value="<?= (int)$cat['id'] ?>" <?= (int)($row['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= e((string)$cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-size-<?= (int)$i ?>">Size</label>
                                    <input id="ir-size-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][size]" maxlength="32"
                                           list="import-size-presets" value="<?= e((string)($row['size'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-length-<?= (int)$i ?>">Length</label>
                                    <input id="ir-length-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][length]" maxlength="32"
                                           value="<?= e((string)($row['length'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-color-<?= (int)$i ?>">Color</label>
                                    <input id="ir-color-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][color]" maxlength="32"
                                           value="<?= e((string)($row['color'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-alt-<?= (int)$i ?>">Image alt text</label>
                                    <input id="ir-alt-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][alt_text]" maxlength="255"
                                           value="<?= e((string)($row['alt_text'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                                </div>

                                <div class="sm:col-span-2 lg:col-span-4">
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-short-<?= (int)$i ?>">Short description</label>
                                    <input id="ir-short-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][short_description]" maxlength="400"
                                           value="<?= e((string)($row['short_description'] ?? '')) ?>"
                                           class="mt-1 h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm">
                                </div>
                                <div class="sm:col-span-2 lg:col-span-4">
                                    <label class="block text-xs font-semibold text-muted-ink" for="ir-desc-<?= (int)$i ?>">Full description</label>
                                    <textarea id="ir-desc-<?= (int)$i ?>" name="rows[<?= (int)$i ?>][description]" rows="2" maxlength="20000"
                                              class="mt-1 w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm"><?= e((string)($row['description'] ?? '')) ?></textarea>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-line bg-surface p-3">
                                <label class="block text-xs font-semibold text-muted-ink" for="ir-images-<?= (int)$i ?>">
                                    Images for this product <span class="font-normal">(one or more, JPG/PNG/WebP, max 5 MB each)</span>
                                </label>
                                <input id="ir-images-<?= (int)$i ?>" type="file" name="images[<?= (int)$i ?>][]"
                                       accept="image/jpeg,image/png,image/webp" multiple data-import-images
                                       class="mt-1 block w-full max-w-md rounded-xl border border-line bg-surface px-3 py-2 text-sm">
                                <div data-import-preview class="mt-2 flex flex-wrap gap-2" aria-live="polite"></div>
                            </div>
                        </section>
                        <?php endforeach; ?>
                        <datalist id="import-size-presets">
                            <?php foreach (['1"', '1.5"', '2"', '3"', '4"', '5"', '6"', '8"', '10"', 'Small', 'Medium', 'Large', 'One size'] as $sizePreset): ?>
                                <option value="<?= e($sizePreset) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 rounded-b-2xl border-t border-line bg-surface px-4 py-4 sm:px-6">
                        <p class="text-xs text-muted-ink"><?= $validCount ?> of <?= count($importRows) ?> row(s) ready. Invalid rows stay in the preview so you can fix them.</p>
                        <button type="submit" class="ml-auto inline-flex h-11 items-center rounded-full bg-accent px-6 text-sm font-semibold text-white hover:bg-accent/90">
                            Save <?= $validCount ?> product(s)
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return;
    }

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
        $where .= ' AND (name LIKE :q_name OR sku LIKE :q_sku)';
        $params[':q_name'] = "%$like%";
        $params[':q_sku']  = "%$like%";
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
        <div class="flex flex-wrap items-center gap-2">
            <form method="post" action="/admin/products" enctype="multipart/form-data" class="flex items-center gap-2">
                <input type="hidden" name="do" value="import_preview">
                <?= csrf_field() ?>
                <label class="inline-flex h-11 cursor-pointer items-center rounded-full border border-line bg-surface px-5 text-sm font-semibold hover:bg-canvas">
                    Upload product via CSV
                    <input type="file" name="csv" accept=".csv,text/csv" required class="sr-only" data-import-file>
                </label>
                <noscript>
                    <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-ink px-4 text-sm font-semibold text-white">Upload</button>
                </noscript>
            </form>
            <a href="/admin/products?action=import_sample" class="inline-flex h-11 items-center rounded-full border border-line px-4 text-sm font-semibold hover:bg-canvas">Sample CSV</a>
            <a href="/admin/products?action=new" class="inline-flex h-11 items-center rounded-full bg-accent px-5 text-sm font-semibold text-white hover:bg-accent/90">+ New product</a>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($canEdit): ?>
    <p class="mt-2 text-xs text-muted-ink">CSV import creates one product + its first variant per row. Choose a file to open the editable preview; add images per row before saving.</p>
    <?php endif; ?>

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
