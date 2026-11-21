<?php
// includes/product_import.php — CSV product import (preview + save).
//
// Flow: upload CSV -> parse/normalise into session rows -> render an editable
// preview -> save each valid row (product + first variant + stock + images).
// All input is re-validated on save; the preview is never trusted
// (SECURITY.md §19.2, §18).
declare(strict_types=1);

const PRODUCT_IMPORT_MAX_ROWS = 100;
const PRODUCT_IMPORT_MAX_BYTES = 2 * 1024 * 1024; // 2 MB CSV
const PRODUCT_IMPORT_MAX_IMAGES = 8;

/** Column aliases -> canonical field name. */
function product_import_header_aliases(): array
{
    return [
        'name' => 'name',
        'product' => 'name',
        'product_name' => 'name',
        'title' => 'name',
        'sku' => 'sku',
        'product_sku' => 'sku',
        'code' => 'sku',
        'price' => 'price',
        'base_price' => 'price',
        'regular_price' => 'price',
        'sale_price' => 'sale_price',
        'discount_price' => 'sale_price',
        'category' => 'category',
        'category_name' => 'category',
        'category_id' => 'category_id',
        'status' => 'status',
        'short_description' => 'short_description',
        'short_desc' => 'short_description',
        'description' => 'description',
        'details' => 'description',
        'size' => 'size',
        'size_label' => 'size',
        'length' => 'length',
        'length_label' => 'length',
        'color' => 'color',
        'colour' => 'color',
        'color_label' => 'color',
        'stock' => 'stock',
        'quantity' => 'stock',
        'qty' => 'stock',
        'variant_sku' => 'variant_sku',
        'alt_text' => 'alt_text',
        'alt' => 'alt_text',
    ];
}

/** Lowercase, trim and snake_case a CSV header. */
function product_import_key(string $header): string
{
    $key = strtolower(trim($header));
    $key = preg_replace('/[\s\-]+/u', '_', $key) ?? '';
    $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
    return trim($key, '_');
}

/** Pick the delimiter that appears most often in the header record. */
function product_import_delimiter(string $line): string
{
    $best = ',';
    $bestCount = substr_count($line, ',');
    foreach ([';', "\t"] as $candidate) {
        $count = substr_count($line, $candidate);
        if ($count > $bestCount) {
            $best = $candidate;
            $bestCount = $count;
        }
    }
    return $best;
}

/**
 * Read + normalise a CSV file into raw rows.
 * Returns ['headers' => string[], 'rows' => [['line' => int, 'data' => array]]].
 */
function product_import_read_csv(string $path): array
{
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Could not read the uploaded file.');
    }

    $hasBom = fread($fh, 3) === "\xEF\xBB\xBF";
    if (!$hasBom) {
        rewind($fh);
    }

    $firstLine = fgets($fh);
    if ($firstLine === false) {
        fclose($fh);
        throw new RuntimeException('The CSV file is empty.');
    }
    $delimiter = product_import_delimiter($firstLine);

    rewind($fh);
    if ($hasBom) {
        fseek($fh, 3);
    }

    $aliases = product_import_header_aliases();
    $headers = null;
    $rows = [];
    $recordNo = 0;

    while (($cells = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
        $recordNo++;
        $hasContent = false;
        foreach ($cells as $cell) {
            if (trim((string)$cell) !== '') {
                $hasContent = true;
                break;
            }
        }
        if (!$hasContent) {
            continue;
        }

        if ($headers === null) {
            $canonical = [];
            foreach ($cells as $cell) {
                $key = product_import_key((string)$cell);
                if ($key !== '') {
                    $canonical[] = $aliases[$key] ?? $key;
                }
            }
            if (!in_array('name', $canonical, true) || !in_array('sku', $canonical, true)) {
                fclose($fh);
                throw new RuntimeException('CSV must include "name" and "sku" columns.');
            }
            $headers = $canonical;
            continue;
        }

        if (count($rows) >= PRODUCT_IMPORT_MAX_ROWS) {
            fclose($fh);
            throw new RuntimeException('CSV has too many rows (maximum ' . PRODUCT_IMPORT_MAX_ROWS . ').');
        }

        $data = [];
        foreach ($headers as $i => $field) {
            $data[$field] = trim((string)($cells[$i] ?? ''));
        }
        $rows[] = ['line' => $recordNo, 'data' => $data];
    }
    fclose($fh);

    if ($headers === null) {
        throw new RuntimeException('The CSV file is empty.');
    }
    if ($rows === []) {
        throw new RuntimeException('The CSV file has no product rows.');
    }

    return ['headers' => $headers, 'rows' => $rows];
}

/** Existing SKUs/slugs/categories, loaded once per request. */
function product_import_context(): array
{
    $pdo = db();
    $productSkus = [];
    foreach ($pdo->query('SELECT sku FROM products')->fetchAll(PDO::FETCH_COLUMN) as $sku) {
        $productSkus[strtoupper((string)$sku)] = true;
    }
    $variantSkus = [];
    foreach ($pdo->query('SELECT sku FROM product_variants')->fetchAll(PDO::FETCH_COLUMN) as $sku) {
        $variantSkus[strtoupper((string)$sku)] = true;
    }
    $slugs = [];
    foreach ($pdo->query('SELECT slug FROM products')->fetchAll(PDO::FETCH_COLUMN) as $slug) {
        $slugs[(string)$slug] = true;
    }

    $categoryRows = $pdo->query(
        "SELECT id, name FROM categories WHERE status = 'active' AND deleted_at IS NULL ORDER BY name"
    )->fetchAll();

    $categories = [];
    $categoryIds = [];
    $categoryNames = [];
    foreach ($categoryRows as $cat) {
        $categories[mb_strtolower(trim((string)$cat['name']))] = (int)$cat['id'];
        $categoryIds[(int)$cat['id']] = true;
        $categoryNames[(int)$cat['id']] = (string)$cat['name'];
    }

    return [
        'product_skus' => $productSkus,
        'variant_skus' => $variantSkus,
        'slugs' => $slugs,
        'categories' => $categories,
        'category_ids' => $categoryIds,
        'category_names' => $categoryNames,
        'category_rows' => $categoryRows,
    ];
}

/** Trim to a max length (multibyte-safe). */
function product_import_text(mixed $value, int $max): string
{
    return mb_substr(trim((string)$value), 0, $max);
}

/**
 * Parse a price cell. Returns [normalised decimal|null, wasProvided].
 * Accepts "Rs 1,200", "PKR 1200", "1200.00".
 */
function product_import_money(mixed $value): array
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return [null, false];
    }
    $raw = preg_replace('/^(?:rs\.?|pkr|₨)\s*/iu', '', $raw) ?? $raw;
    $raw = str_replace([',', ' ', "\u{00A0}"], '', $raw);
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $raw)) {
        return [null, true];
    }
    $cents = (int)round((float)$raw * 100);
    if ($cents <= 0) {
        return [null, true];
    }
    return [number_format($cents / 100, 2, '.', ''), true];
}

/**
 * Normalise + validate one row against the current catalogue context.
 * Uniqueness only joins the "seen" sets when the row is otherwise valid, so a
 * broken row does not block a later row that reuses its SKU.
 */
function product_import_normalize_row(
    array $raw,
    array $ctx,
    array &$seenProductSkus,
    array &$seenVariantSkus,
    int $line
): array {
    $errors = [];
    $warnings = [];

    $name = product_import_text($raw['name'] ?? '', 180);
    $sku = strtoupper(product_import_text($raw['sku'] ?? '', 64));

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!preg_match('/^[A-Z0-9-]{2,64}$/', $sku)) {
        $errors[] = 'SKU must be 2–64 characters: A–Z, 0–9 or dash.';
    }

    [$price, $priceGiven] = product_import_money($raw['price'] ?? '');
    if (!$priceGiven) {
        $errors[] = 'Price is required.';
    } elseif ($price === null) {
        $errors[] = 'Price must be a positive amount (for example 1200.00).';
    }

    [$sale, $saleGiven] = product_import_money($raw['sale_price'] ?? '');
    if ($saleGiven && $sale === null) {
        $errors[] = 'Sale price must be a positive amount.';
    } elseif ($sale !== null && $price !== null && (float)$sale >= (float)$price) {
        $errors[] = 'Sale price must be below the regular price.';
    }

    $stockRaw = trim((string)($raw['stock'] ?? ''));
    $stock = 0;
    if ($stockRaw !== '') {
        $parsed = filter_var($stockRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
        if ($parsed === false) {
            $errors[] = 'Stock must be a whole number between 0 and 1,000,000.';
        } else {
            $stock = (int)$parsed;
        }
    }

    $status = strtolower(trim((string)($raw['status'] ?? 'draft')));
    if ($status === '') {
        $status = 'draft';
    }
    if (!in_array($status, ['draft', 'active', 'archived'], true)) {
        $errors[] = 'Status must be draft, active or archived.';
    }

    $categoryName = product_import_text($raw['category'] ?? '', 120);
    $categoryId = null;
    $explicitCategory = int_in($raw['category_id'] ?? null);
    if ($explicitCategory !== null && isset($ctx['category_ids'][$explicitCategory])) {
        $categoryId = $explicitCategory;
        $categoryName = $ctx['category_names'][$explicitCategory] ?? '';
    } elseif ($categoryName !== '') {
        $key = mb_strtolower($categoryName);
        if (isset($ctx['categories'][$key])) {
            $categoryId = (int)$ctx['categories'][$key];
        } elseif (ctype_digit($categoryName) && isset($ctx['category_ids'][(int)$categoryName])) {
            $categoryId = (int)$categoryName;
            $categoryName = $ctx['category_names'][$categoryId] ?? $categoryName;
        } else {
            $warnings[] = 'Category "' . $categoryName . '" was not found — the product will have no category.';
            $categoryId = null;
        }
    }

    $variantSku = strtoupper(product_import_text($raw['variant_sku'] ?? '', 64));
    if ($variantSku === '') {
        $variantSku = $sku;
    } elseif (!preg_match('/^[A-Z0-9-]{2,64}$/', $variantSku)) {
        $errors[] = 'Variant SKU must be 2–64 characters: A–Z, 0–9 or dash.';
    }

    // Duplicate checks (existing catalogue + earlier CSV rows).
    if (preg_match('/^[A-Z0-9-]{2,64}$/', $sku)) {
        if (isset($ctx['product_skus'][$sku]) || isset($seenProductSkus[$sku])) {
            $errors[] = 'SKU already exists — this row will be skipped.';
        }
    }
    if (preg_match('/^[A-Z0-9-]{2,64}$/', $variantSku)) {
        if (isset($ctx['variant_skus'][$variantSku]) || isset($seenVariantSkus[$variantSku])) {
            $errors[] = 'Variant SKU already exists — this row will be skipped.';
        }
    }

    $altText = product_import_text($raw['alt_text'] ?? '', 255);
    if ($altText === '') {
        $altText = $name;
    }

    $row = [
        'line' => $line,
        'name' => $name,
        'sku' => $sku,
        'price' => $price ?? '',
        'sale_price' => $sale ?? '',
        'status' => $status,
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'short_description' => product_import_text($raw['short_description'] ?? '', 400),
        'description' => product_import_text($raw['description'] ?? '', 20000),
        'size' => product_import_text($raw['size'] ?? '', 32),
        'length' => product_import_text($raw['length'] ?? '', 32),
        'color' => product_import_text($raw['color'] ?? '', 32),
        'stock' => $stock,
        'variant_sku' => $variantSku,
        'alt_text' => $altText,
        'errors' => array_values(array_unique($errors)),
        'warnings' => array_values(array_unique($warnings)),
    ];

    if ($row['errors'] === []) {
        $seenProductSkus[$sku] = true;
        $seenVariantSkus[$variantSku] = true;
    }

    return $row;
}

/** Validate the uploaded CSV file and return its temporary path. */
function product_import_validate_upload(?array $file): string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose a CSV file first.');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > PRODUCT_IMPORT_MAX_BYTES) {
        throw new RuntimeException('CSV must be under 2 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Invalid upload.');
    }
    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        throw new RuntimeException('Please upload a .csv file.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'text/plain', 'text/csv', 'application/csv', 'text/x-csv',
        'application/vnd.ms-excel', 'application/octet-stream',
    ];
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Please upload a CSV file.');
    }
    return (string)$file['tmp_name'];
}

/**
 * Extract the files for one preview row from a nested $_FILES['images'] array.
 * Supports both images[0][] (multiple) and images[0] (single) shapes.
 */
function product_import_row_files(int $index): array
{
    $files = $_FILES['images'] ?? null;
    if (!is_array($files) || !isset($files['name'][$index])) {
        return [];
    }

    $names = $files['name'][$index];
    if (!is_array($names)) {
        return [[
            'name' => (string)$names,
            'type' => (string)($files['type'][$index] ?? ''),
            'tmp_name' => (string)($files['tmp_name'][$index] ?? ''),
            'error' => (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($files['size'][$index] ?? 0),
        ]];
    }

    $out = [];
    foreach ($names as $i => $name) {
        $out[] = [
            'name' => (string)$name,
            'type' => (string)($files['type'][$index][$i] ?? ''),
            'tmp_name' => (string)($files['tmp_name'][$index][$i] ?? ''),
            'error' => (int)($files['error'][$index][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($files['size'][$index][$i] ?? 0),
        ];
    }
    return $out;
}

/** Build the raw row for re-validation from the editable preview POST. */
function product_import_row_from_post(int $index, array $stored, array $post): array
{
    $rows = $post['rows'] ?? null;
    $input = is_array($rows) && is_array($rows[$index] ?? null) ? $rows[$index] : [];
    $pick = static function (string $key, string $fallback) use ($input): string {
        $value = $input[$key] ?? null;
        return is_scalar($value) ? (string)$value : $fallback;
    };

    $storedCategoryId = $stored['category_id'] ?? null;

    return [
        'name' => $pick('name', (string)($stored['name'] ?? '')),
        'sku' => $pick('sku', (string)($stored['sku'] ?? '')),
        'price' => $pick('price', (string)($stored['price'] ?? '')),
        'sale_price' => $pick('sale_price', (string)($stored['sale_price'] ?? '')),
        'status' => $pick('status', (string)($stored['status'] ?? 'draft')),
        'category' => '',
        'category_id' => $pick('category_id', $storedCategoryId !== null ? (string)$storedCategoryId : ''),
        'short_description' => $pick('short_description', (string)($stored['short_description'] ?? '')),
        'description' => $pick('description', (string)($stored['description'] ?? '')),
        'size' => $pick('size', (string)($stored['size'] ?? '')),
        'length' => $pick('length', (string)($stored['length'] ?? '')),
        'color' => $pick('color', (string)($stored['color'] ?? '')),
        'stock' => $pick('stock', (string)($stored['stock'] ?? '0')),
        'variant_sku' => $pick('variant_sku', (string)($stored['variant_sku'] ?? '')),
        'alt_text' => $pick('alt_text', (string)($stored['alt_text'] ?? '')),
    ];
}

/** Build a unique slug, recording it in the context set. */
function product_import_unique_slug(string $name, array &$usedSlugs): string
{
    $base = slugify($name);
    $slug = $base;
    $n = 2;
    while (isset($usedSlugs[$slug])) {
        $slug = $base . '-' . $n;
        $n++;
    }
    $usedSlugs[$slug] = true;
    return $slug;
}

/**
 * Insert every valid row (own transaction per row so one failure does not
 * discard the rest). Images are stored first and removed again if the DB
 * transaction fails.
 *
 * Returns ['created','skipped','images','errors'=>[],'warnings'=>[],'saved'=>[indices]].
 */
function product_import_save_rows(array $rows, array &$ctx, int $adminId): array
{
    $pdo = db();
    $result = [
        'created' => 0,
        'skipped' => 0,
        'images' => 0,
        'errors' => [],
        'warnings' => [],
        'saved' => [],
    ];

    foreach ($rows as $index => $row) {
        if ($row['errors'] !== []) {
            $result['skipped']++;
            $result['errors'][] = ['line' => (int)$row['line'], 'errors' => $row['errors']];
            continue;
        }

        // ---- Images: store before the transaction, delete on rollback ----
        $storedPaths = [];
        $files = product_import_row_files((int)$index);
        if (count($files) > PRODUCT_IMPORT_MAX_IMAGES) {
            $result['warnings'][] = 'Row ' . (int)$row['line'] . ': only the first '
                . PRODUCT_IMPORT_MAX_IMAGES . ' images were used.';
            $files = array_slice($files, 0, PRODUCT_IMPORT_MAX_IMAGES);
        }
        foreach ($files as $file) {
            $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                $result['warnings'][] = 'Row ' . (int)$row['line'] . ': an image could not be uploaded.';
                continue;
            }
            try {
                $storedPaths[] = image_upload_store($file, 'products');
            } catch (RuntimeException $e) {
                $result['warnings'][] = 'Row ' . (int)$row['line'] . ': ' . $e->getMessage();
                app_log('error', 'CSV import image rejected: ' . $e->getMessage());
            }
        }

        try {
            $pdo->beginTransaction();

            $slug = product_import_unique_slug((string)$row['name'], $ctx['slugs']);
            $ins = $pdo->prepare(
                'INSERT INTO products
                    (name, slug, sku, category_id, short_description, description,
                     base_price, sale_price, status)
                 VALUES
                    (:name, :slug, :sku, :cat, :short, :desc, :price, :sale, :status)'
            );
            $ins->execute([
                ':name' => $row['name'],
                ':slug' => $slug,
                ':sku' => $row['sku'],
                ':cat' => $row['category_id'],
                ':short' => $row['short_description'] !== '' ? $row['short_description'] : null,
                ':desc' => $row['description'] !== '' ? $row['description'] : null,
                ':price' => $row['price'],
                ':sale' => $row['sale_price'] !== '' ? $row['sale_price'] : null,
                ':status' => $row['status'],
            ]);
            $productId = (int)$pdo->lastInsertId();

            $vs = $pdo->prepare(
                'INSERT INTO product_variants (product_id, sku, size_label, length_label, color_label, price)
                 VALUES (:p, :sku, :size, :length, :color, :price)'
            );
            $vs->execute([
                ':p' => $productId,
                ':sku' => $row['variant_sku'],
                ':size' => $row['size'] !== '' ? $row['size'] : null,
                ':length' => $row['length'] !== '' ? $row['length'] : null,
                ':color' => $row['color'] !== '' ? $row['color'] : null,
                ':price' => $row['price'],
            ]);
            $variantId = (int)$pdo->lastInsertId();

            $pdo->prepare('INSERT INTO inventory (variant_id, quantity_on_hand) VALUES (:v, :q)')
                ->execute([':v' => $variantId, ':q' => (int)$row['stock']]);

            if ((int)$row['stock'] > 0) {
                $pdo->prepare(
                    'INSERT INTO stock_movements (variant_id, change_qty, reason, user_id, note, created_at)
                     VALUES (:v, :q, :r, :u, :n, UTC_TIMESTAMP())'
                )->execute([
                    ':v' => $variantId,
                    ':q' => (int)$row['stock'],
                    ':r' => 'purchase',
                    ':u' => $adminId,
                    ':n' => 'Initial stock (CSV import)',
                ]);
            }

            if ($storedPaths !== []) {
                $img = $pdo->prepare(
                    'INSERT INTO product_images (product_id, file_path, alt_text, sort_order, is_primary)
                     VALUES (:p, :f, :a, :s, :prim)'
                );
                foreach ($storedPaths as $n => $path) {
                    $img->execute([
                        ':p' => $productId,
                        ':f' => $path,
                        ':a' => $row['alt_text'],
                        ':s' => $n,
                        ':prim' => $n === 0 ? 1 : 0,
                    ]);
                }
            }

            $pdo->commit();

            $ctx['product_skus'][$row['sku']] = true;
            $ctx['variant_skus'][$row['variant_sku']] = true;
            $result['created']++;
            $result['images'] += count($storedPaths);
            $result['saved'][] = (int)$index;
            audit('product_imported', 'product', $productId, null, [
                'sku' => $row['sku'],
                'source' => 'csv',
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($storedPaths as $path) {
                image_upload_delete($path);
            }
            app_log('error', 'CSV import row ' . (int)$row['line'] . ' failed: ' . $e->getMessage());
            $result['skipped']++;
            $result['errors'][] = [
                'line' => (int)$row['line'],
                'errors' => ['Could not save this product. Check that the SKU is unique and try again.'],
            ];
        }
    }

    return $result;
}

/** Sample CSV (one example row) for the download link. */
function product_import_sample_csv(): string
{
    $fh = fopen('php://temp', 'r+');
    if ($fh === false) {
        return '';
    }
    fputcsv($fh, [
        'name', 'sku', 'price', 'sale_price', 'category', 'status', 'size', 'length',
        'color', 'stock', 'variant_sku', 'short_description', 'description', 'alt_text',
    ], ',', '"', '');
    fputcsv($fh, [
        'Rose Patch', 'ROSE-3IN', '1200', '999', 'Patches', 'active', '3"', '',
        'Red', '25', 'ROSE-3IN-RED', 'Embroidered rose patch',
        'Iron-on embroidered rose patch, 3 inch.', 'Rose patch',
    ], ',', '"', '');
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return is_string($csv) ? $csv : '';
}
