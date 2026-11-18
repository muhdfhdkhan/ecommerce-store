<?php
// database/seed_catalog.php — demo catalog, shipping, CMS pages (CLI only, idempotent).
// Usage: php database/seed_catalog.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();
$pdo->beginTransaction();
try {
    // ---------- Shipping zone + flat method (matches checkout constants) ----------
    $zoneId = null;
    $st = $pdo->prepare('SELECT id FROM shipping_zones WHERE name = :n LIMIT 1');
    $st->execute([':n' => 'Pakistan']);
    $zoneId = $st->fetchColumn();
    if ($zoneId === false) {
        $ins = $pdo->prepare('INSERT INTO shipping_zones (name, cities, is_default, is_active) VALUES (:n, :c, 1, 1)');
        $ins->execute([':n' => 'Pakistan', ':c' => null]);
        $zoneId = (int)$pdo->lastInsertId();
    }
    $st = $pdo->prepare('SELECT id FROM shipping_methods WHERE zone_id = :z AND name = :n LIMIT 1');
    $st->execute([':z' => $zoneId, ':n' => 'Standard delivery']);
    if ($st->fetchColumn() === false) {
        $pdo->prepare('INSERT INTO shipping_methods (zone_id, name, flat_fee, free_over_amount, estimate_days, is_active)
                       VALUES (:z, :n, 250.00, 5000.00, :d, 1)')
            ->execute([':z' => $zoneId, ':n' => 'Standard delivery', ':d' => '2-4 days']);
    }

    // ---------- Categories ----------
    $categories = [
        ['Iron-on Patches', 'iron-on-patches', 'Ready-to-apply embroidered patches with a heat-activated backing.'],
        ['Sew-on Motifs', 'sew-on-motifs', 'Delicate motifs meant to be stitched on for a permanent finish.'],
        ['Bridal & Wedding', 'bridal-wedding', 'Heirloom-quality embroidery for dresses, dupattas and veils.'],
        ['Name Labels', 'name-labels', 'Personalised embroidered labels for uniforms and keeping.'],
    ];
    $catIds = [];
    foreach ($categories as [$name, $slug, $desc]) {
        $st = $pdo->prepare('SELECT id FROM categories WHERE slug = :s LIMIT 1');
        $st->execute([':s' => $slug]);
        $found = $st->fetchColumn();
        if ($found !== false) {
            $catIds[$slug] = (int)$found;
            continue;
        }
        $pdo->prepare('INSERT INTO categories (name, slug, description, status) VALUES (:n, :s, :d, :st)')
            ->execute([':n' => $name, ':s' => $slug, ':d' => $desc, ':st' => 'active']);
        $catIds[$slug] = (int)$pdo->lastInsertId();
    }

    // ---------- Products + variants + inventory ----------
    // Each product: [name, slug, sku, categorySlug, basePrice, salePrice|null, shortDesc, isMto, leadDays|null,
    //                variants: [[sku, size|null, color|null, price, stock]]]
    $products = [
        ['Floral Vine Patch', 'floral-vine-patch', 'PM-FLV-001', 'iron-on-patches',
          '1850.00', '1450.00', 'A trailing vine of hand-drawn florals, embroidered in moss green and blush.', 0, null,
          [
              ['PM-FLV-001-S', 'Small', null, '1450.00', 24],
              ['PM-FLV-001-L', 'Large', null, '1850.00', 15],
          ]],
         ['Ethereal Bloom Motif', 'ethereal-bloom-motif', 'PM-ETH-002', 'bridal-wedding',
          '4200.00', null, 'Couture bridal motif with pearl-beaded edges, made to order for your gown.', 1, 14,
          [
              ['PM-ETH-002-MTO', null, 'Ivory', '4200.00', 0],
          ]],
         ['Starlight Constellation Patch', 'starlight-constellation-patch', 'PM-STR-003', 'iron-on-patches',
          '1200.00', null, 'A scatter of metallic stars stitched in gold thread on midnight navy.', 0, null,
          [
              ['PM-STR-003-S', 'Small', null, '1200.00', 40],
          ]],
         ['Heritage Peacock Motif', 'heritage-peacock-motif', 'PM-PKC-004', 'sew-on-motifs',
          '2600.00', '2200.00', 'A regal peacock rendered in jewel tones with fine satin-stitch feathers.', 0, null,
          [
              ['PM-PKC-004-S', 'Small', 'Emerald', '2200.00', 18],
              ['PM-PKC-004-M', 'Medium', 'Emerald', '2600.00', 9],
          ]],
         ['Monogram Name Label', 'monogram-name-label', 'PM-NAM-005', 'name-labels',
          '900.00', null, 'Your name in classic script, stitched onto a woven label with rose border.', 1, 7,
          [
              ['PM-NAM-005-MTO', null, null, '900.00', 0],
          ]],
         ['Wildflower Scatter Patch', 'wildflower-scatter-patch', 'PM-WLD-006', 'iron-on-patches',
          '1500.00', null, 'Loose sprigs of cornflower and daisy, perfect for denim jackets and totes.', 0, null,
          [
              ['PM-WLD-006-S', 'Small', null, '1500.00', 30],
              ['PM-WLD-006-M', 'Medium', 'Mixed', '1650.00', 0],
          ]],
    ];

    foreach ($products as [$pName, $pSlug, $pSku, $catSlug, $base, $sale, $short, $mto, $lead, $variants]) {
        $st = $pdo->prepare('SELECT id FROM products WHERE slug = :s LIMIT 1');
        $st->execute([':s' => $pSlug]);
        $pid = $st->fetchColumn();
        if ($pid === false) {
            $pdo->prepare(
                'INSERT INTO products (name, slug, sku, category_id, short_description, base_price, sale_price,
                                       status, is_featured, made_to_order, lead_time_days)
                 VALUES (:n, :s, :sku, :cat, :sd, :base, :sale, :status, :feat, :mto, :lead)'
            )->execute([
                ':n' => $pName, ':s' => $pSlug, ':sku' => $pSku, ':cat' => $catIds[$catSlug],
                ':sd' => $short, ':base' => $base, ':sale' => $sale, ':status' => 'active',
                ':feat' => 1, ':mto' => $mto, ':lead' => $lead,
            ]);
            $pid = (int)$pdo->lastInsertId();
        }

        foreach ($variants as [$vSku, $vSize, $vColor, $vPrice, $vStock]) {
            $st = $pdo->prepare('SELECT id FROM product_variants WHERE sku = :sku LIMIT 1');
            $st->execute([':sku' => $vSku]);
            $vid = $st->fetchColumn();
            if ($vid !== false) {
                continue;
            }
            $pdo->prepare('INSERT INTO product_variants (product_id, sku, size_label, color_label, price, is_active)
                           VALUES (:p, :sku, :size, :color, :price, 1)')
                ->execute([':p' => $pid, ':sku' => $vSku, ':size' => $vSize, ':color' => $vColor, ':price' => $vPrice]);
            $vid = (int)$pdo->lastInsertId();

            if ($vStock > 0) {
                $pdo->prepare('INSERT INTO inventory (variant_id, quantity_on_hand, quantity_reserved) VALUES (:v, :q, 0)')
                    ->execute([':v' => $vid, ':q' => $vStock]);
                $pdo->prepare('INSERT INTO stock_movements (variant_id, change_qty, reason, ref_type, note, created_at)
                               VALUES (:v, :q, :reason, :ref, :note, UTC_TIMESTAMP())')
                    ->execute([':v' => $vid, ':q' => $vStock, ':reason' => 'purchase', ':ref' => 'initial', ':note' => 'Seed stock']);
            }
        }
    }

    // ---------- Demo collection ----------
    $st = $pdo->prepare('SELECT id FROM collections WHERE slug = :s LIMIT 1');
    $st->execute([':s' => 'the-bridal-edit']);
    if ($st->fetchColumn() === false) {
        $pdo->prepare('INSERT INTO collections (title, slug, description, status, sort_order)
                       VALUES (:t, :s, :d, :status, 0)')
            ->execute([':t' => 'The Bridal Edit', ':s' => 'the-bridal-edit',
                       ':d' => 'Pearls, ivory threads and heirloom details for the big day.', ':status' => 'active']);
        $collId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT IGNORE INTO collection_products (collection_id, product_id)
                       SELECT :c, id FROM products WHERE slug IN (:s1, :s2)')
            ->execute([':c' => $collId, ':s1' => 'ethereal-bloom-motif', ':s2' => 'heritage-peacock-motif']);
    }

    // ---------- Welcome coupon ----------
    $st = $pdo->prepare('SELECT id FROM coupons WHERE code = :c LIMIT 1');
    $st->execute([':c' => 'WELCOME10']);
    if ($st->fetchColumn() === false) {
        $pdo->prepare('INSERT INTO coupons (code, type, value, min_subtotal, usage_limit, per_customer_limit, is_active)
                       VALUES (:c, :t, :v, :min, 100, 1, 1)')
            ->execute([':c' => 'WELCOME10', ':t' => 'percent', ':v' => '10.00', ':min' => '1000.00']);
    }

    // ---------- CMS policy pages (footer links) ----------
    $pages = [
        ['shipping-policy', 'Shipping policy',
         "Orders are dispatched from Karachi within 2-4 working days of confirmation.\n"
         . "Standard delivery is Rs 250 flat; shipping is free on orders of Rs 5,000 and above.\n"
         . "Cash on delivery is available nationwide; bank transfers are dispatched once funds are verified."],
        ['returns-policy', 'Returns & refunds',
         "Ready-to-ship items may be returned within 7 days of delivery if unused and in original packaging.\n"
         . "Made-to-order and personalised items cannot be returned unless faulty.\n"
         . "Refunds are issued to the original payment method within 5 working days of receiving a return."],
        ['privacy-policy', 'Privacy policy',
         "We collect only the details needed to fulfil your order: name, contact information and address.\n"
         . "Your data is never sold. Payment details are handled by the store team over verified channels.\n"
         . "You may request deletion of your account data at any time by contacting us."],
    ];
    foreach ($pages as [$slug, $title, $content]) {
        $st = $pdo->prepare('SELECT id FROM pages WHERE slug = :s LIMIT 1');
        $st->execute([':s' => $slug]);
        if ($st->fetchColumn() === false) {
            $pdo->prepare('INSERT INTO pages (title, slug, content, status) VALUES (:t, :s, :c, :st)')
                ->execute([':t' => $title, ':s' => $slug, ':c' => $content, ':st' => 'published']);
        }
    }

    $pdo->commit();
    echo "Catalog seed complete: 4 categories, 6 products, shipping zone + method, 1 collection, 1 coupon, 3 CMS pages.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Catalog seed failed: ' . $e->getMessage() . "\n");
    app_log('error', 'seed_catalog failed: ' . $e->getMessage());
    exit(1);
}
