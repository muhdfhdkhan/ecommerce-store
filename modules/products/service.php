<?php
// modules/products/service.php — catalog logic shared by storefront & admin.
// SECURITY.md §5.3: ORDER BY whitelist, LIMIT/OFFSET cast to int, LIKE with escaped wildcards.
declare(strict_types=1);

/**
 * Active-product search with filters.
 * $q never reaches SQL raw: wildcard-escaped and bound via prepared statement.
 */
function products_search(string $q, array $filters, int $perPage, int $page): array
{
    // Whitelisted ORDER BY (SECURITY.md §5.3 — never interpolate user input).
    $allowedSort = [
        'newest'     => 'p.created_at DESC',
        'price_asc'  => 'effective_price ASC',
        'price_desc' => 'effective_price DESC',
        'name_asc'   => 'p.name ASC',
    ];
    $orderBy = $allowedSort[$filters['sort'] ?? ''] ?? $allowedSort['newest'];

    $page    = max(1, $page);
    $limit   = min(48, max(1, $perPage));
    $offset  = ($page - 1) * $limit;

    $where  = ["p.status = 'active'", 'p.deleted_at IS NULL'];
    $params = [];

    if ($q !== '') {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($q, 0, 80));
        $where[]  = '(p.name LIKE :like_q OR p.sku LIKE :like_q)';
        $params[':like_q'] = "%$like%";
    }
    if (!empty($filters['category_id'])) {
        $where[]  = 'p.category_id = :cat';
        $params[':cat'] = (int)$filters['category_id'];
    }
    if (!empty($filters['on_sale'])) {
        $where[] = 'p.sale_price IS NOT NULL AND p.sale_price < p.base_price';
    }

    $whereSql = implode(' AND ', $where);

    $listSql = "SELECT p.id, p.name, p.slug, p.sku, p.base_price, p.sale_price, p.made_to_order,
                       COALESCE(pi.file_path, '') AS image_path,
                       CASE WHEN p.sale_price IS NOT NULL THEN p.sale_price ELSE p.base_price END AS effective_price
                FROM products p
                LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                WHERE $whereSql
                GROUP BY p.id, p.name, p.slug, p.sku, p.base_price, p.sale_price, p.made_to_order, p.created_at, pi.file_path
                ORDER BY $orderBy
                LIMIT :limit OFFSET :offset";

    $st = db()->prepare($listSql);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, PDO::PARAM_STR);
    }
    $st->bindValue(':limit', $limit, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();
    $items = $st->fetchAll();

    $cst = db()->prepare("SELECT COUNT(DISTINCT p.id) AS c FROM products p WHERE $whereSql");
    $cst->execute($params);
    $total = (int)($cst->fetch()['c'] ?? 0);

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $limit];
}

/** Single active product by slug, with images and variants (for the detail page). */
function product_find_by_slug(string $slug): ?array
{
    $st = db()->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.slug = :slug AND p.status = 'active' AND p.deleted_at IS NULL
         LIMIT 1"
    );
    $st->execute([':slug' => $slug]);
    $product = $st->fetch();
    if (!$product) {
        return null;
    }

    $id = (int)$product['id'];

    $ist = db()->prepare(
        'SELECT id, file_path, alt_text FROM product_images
         WHERE product_id = :id ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $ist->execute([':id' => $id]);
    $product['images'] = $ist->fetchAll();

    $vst = db()->prepare(
        'SELECT v.id, v.sku, v.size_label, v.color_label, v.price, v.sale_price,
                (inv.quantity_on_hand - inv.quantity_reserved) AS available
         FROM product_variants v
         LEFT JOIN inventory inv ON inv.variant_id = v.id
         WHERE v.product_id = :id AND v.is_active = 1 AND v.deleted_at IS NULL
         ORDER BY v.id ASC'
    );
    $vst->execute([':id' => $id]);
    $product['variants'] = $vst->fetchAll();

    return $product;
}

/** Effective unit price for a variant, computed ALWAYS from DB (SECURITY.md §20 — never client price). */
function variant_effective_price(int $variantId): ?array
{
    $st = db()->prepare(
        "SELECT v.id, v.product_id, v.sku, v.price, v.sale_price, v.is_active,
                p.status AS product_status, p.deleted_at, p.name AS product_name,
                p.made_to_order
         FROM product_variants v
         JOIN products p ON p.id = v.product_id
         WHERE v.id = :id"
    );
    $st->execute([':id' => $variantId]);
    $v = $st->fetch();
    if (!$v || $v['is_active'] != 1 || $v['product_status'] !== 'active' || $v['deleted_at'] !== null) {
        return null;
    }
    $price = ($v['sale_price'] !== null && (float)$v['sale_price'] < (float)$v['price'])
        ? (string)$v['sale_price']
        : (string)$v['price'];
    return ['variant' => $v, 'unit_price_cents' => money_to_cents($price)];
}
