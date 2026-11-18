<?php
// database/make_placeholders.php — one-time generator for demo imagery (CLI only).
// Creates branded placeholder JPGs for hero slides, products and the collection
// so the storefront shows real art before photography arrives (DESIGN.md §14).
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once dirname(__DIR__) . '/includes/bootstrap.php';

const W = 1000;
const H = 1000;

$palette = [
    ['#E7EAE2', '#30351B'], // sage / ink
    ['#EFF3D4', '#26734A'], // pale accent / green
    ['#F2E8DC', '#7A4E2D'], // cream / brown
    ['#E8E4F0', '#4B3B6B'], // lilac / plum
    ['#DCE8E8', '#2D6A6A'], // mist / teal
    ['#F0DAD6', '#8A4A42'], // blush / clay
];

function draw_motif($img, array $bg, array $fg, int $seed): void
{
    // Background
    [$b1, $f1] = [hexcolor($img, $bg[0]), hexcolor($img, $bg[1])];
    imagefill($img, 0, 0, $b1);

    // Decorative "stitch" rings: pseudo-random by seed, deterministic.
    mt_srand($seed);
    for ($i = 0; $i < 7; $i++) {
        $cx = mt_rand(120, W - 120);
        $cy = mt_rand(120, H - 120);
        $r  = mt_rand(60, 220);
        $lw = mt_rand(6, 18);
        imagesetthickness($img, $lw);
        imagearc($img, $cx, $cy, $r * 2, $r * 2, 0, 360, $f1);
    }
    // Leaf/petal diamonds
    for ($i = 0; $i < 5; $i++) {
        $cx = mt_rand(150, W - 150);
        $cy = mt_rand(150, H - 150);
        $s  = mt_rand(50, 120);
        imagefilledpolygon($img, [
            $cx, $cy - $s, $cx + $s, $cy, $cx, $cy + $s, $cx - $s, $cy,
        ], $f1);
    }
}

function hexcolor($img, string $hex): int
{
    return (int)imagecolorallocate($img, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
}

function save_jpg($img, string $rel): void
{
    $abs = PUBLIC_PATH . '/uploads/' . $rel;
    $dir = dirname($abs);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    imagejpeg($img, $abs, 85);
    imagedestroy($img);
    echo "created uploads/$rel\n";
}

// ---- Hero slides (wider crop) ----
foreach ([1, 2, 3] as $n) {
    $img = imagecreatetruecolor(1600, 900);
    $pal = $palette[($n - 1) % count($palette)];
    draw_motif($img, $pal[0] === '#E7EAE2' ? ['#DDE3D5', '#30351B'] : $pal, [$pal[1], $pal[1]], 100 + $n);
    save_jpg($img, "hero/slide-$n.jpg");
}

// ---- Product images ----
$pdo = db();
$rows = $pdo->query('SELECT id, slug, name FROM products WHERE deleted_at IS NULL ORDER BY id')->fetchAll();
$i = 0;
foreach ($rows as $r) {
    $i++;
    $pal = $palette[$i % count($palette)];
    $img = imagecreatetruecolor(W, H);
    draw_motif($img, $pal, [$pal[1], $pal[1]], 500 + (int)$r['id']);

    $exists = $pdo->prepare('SELECT id FROM product_images WHERE product_id = :p AND is_primary = 1 LIMIT 1');
    $exists->execute([':p' => $r['id']]);
    if ($exists->fetchColumn()) {
        imagedestroy($img);
        continue; // already has a primary image
    }
    $rel = 'products/' . gmdate('Y-m') . '/seed-' . $r['slug'] . '.jpg';
    save_jpg($img, $rel);
    $pdo->prepare('INSERT INTO product_images (product_id, file_path, alt_text, sort_order, is_primary)
                   VALUES (:p, :f, :a, 0, 1)')
        ->execute([':p' => $r['id'], ':f' => $rel, ':a' => $r['name']]);
}

// ---- Collection cover ----
$coll = $pdo->query("SELECT id FROM collections WHERE slug = 'the-bridal-edit' AND (image_path IS NULL OR image_path = '')")->fetch();
if ($coll) {
    $img = imagecreatetruecolor(1200, 960);
    draw_motif($img, ['#F2E8DC', '#7A4E2D'], ['#7A4E2D', '#7A4E2D'], 777);
    save_jpg($img, 'collections/bridal-edit.jpg');
    $pdo->prepare("UPDATE collections SET image_path = 'collections/bridal-edit.jpg' WHERE id = :id")
        ->execute([':id' => $coll['id']]);
}

echo "Placeholder imagery done.\n";
