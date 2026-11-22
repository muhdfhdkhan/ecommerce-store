<?php
// modules/store/pages/product.php — product detail with variant selection (FR-PROD-*).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

require_once BASE_PATH . '/modules/products/service.php';

$slug = (string)($_GET['slug'] ?? '');
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    abort(404);
}
$product = product_find_by_slug($slug);
if ($product === null) {
    abort(404);
}

// Compute display data server-side.
$variants = $product['variants'];
$mto = (bool)$product['made_to_order'];
$firstVariant = $variants[0] ?? null;
$fromPrice = null;
if ($variants) {
    $eff = array_map(static fn($v) => (float)(($v['sale_price'] !== null && (float)$v['sale_price'] < (float)$v['price']) ? $v['sale_price'] : $v['price']), $variants);
    $fromPrice = min($eff);
}
$images = $product['images'];

// Custom size control (made-to-order products) + share targets.
[$sizeMin, $sizeMax] = $mto ? product_size_bounds() : [1.0, 12.0];
$defaultSize = $sizeMin;
$presetSizeFound = false;
foreach ($variants as $v) {
    if (preg_match('/(\d+(?:\.\d+)?)/', (string)($v['size_label'] ?? ''), $m)) {
        $defaultSize = min($sizeMax, max($sizeMin, (float)$m[1]));
        $presetSizeFound = true;
        break;
    }
}
// Made-to-order variants often have no preset size — start in the middle of the range.
if ($mto && !$presetSizeFound) {
    $defaultSize = min($sizeMax, max($sizeMin, round((($sizeMin + $sizeMax) / 2) * 2) / 2));
}
$shareUrl = rtrim((string)config('url'), '/') . '/product/' . rawurlencode((string)$product['slug']);
$shareTitle = (string)$product['name'];
$shareText = trim((string)($product['short_description'] ?: 'Embroidered patch by Patches&Motifs.'));
$shareImage = $images ? rtrim((string)config('url'), '/') . '/uploads/' . $images[0]['file_path'] : '';

store_layout((string)$product['name'], function () use ($product, $variants, $mto, $fromPrice, $images, $sizeMin, $sizeMax, $defaultSize, $shareUrl, $shareTitle, $shareText, $shareImage) {
    $nonce = $GLOBALS['CSP_NONCE'];
?>
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-12">
    <nav aria-label="Breadcrumb" class="text-sm text-muted-ink">
        <a href="/" class="hover:text-ink">Home</a> <span aria-hidden="true">/</span>
        <a href="/shop" class="hover:text-ink">Shop</a>
        <?php if ($product['category_name']): ?>
            <span aria-hidden="true">/</span> <a href="/shop?category=<?= (int)($product['category_id'] ?? 0) ?>" class="hover:text-ink"><?= e($product['category_name']) ?></a>
        <?php endif; ?>
        <span aria-hidden="true">/</span> <span class="text-ink"><?= e($product['name']) ?></span>
    </nav>

    <div class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-12">
        <!-- Gallery -->
        <div>
            <div id="main-image" class="aspect-square overflow-hidden rounded-3xl border border-gold/30 bg-stone-soft">
                <?php if ($images): ?>
                    <img src="/uploads/<?= e($images[0]['file_path']) ?>" alt="<?= e($images[0]['alt_text'] ?: $product['name']) ?>"
                         class="h-full w-full object-cover" decoding="async">
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?>
                <div class="mt-3 flex gap-3 overflow-x-auto pb-1">
                    <?php foreach ($images as $i => $img): ?>
                        <button type="button" data-gallery-thumb="/uploads/<?= e($img['file_path']) ?>"
                                aria-label="View image <?= $i + 1 ?>"
                                class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-line bg-stone-soft transition hover:border-gold">
                            <img src="/uploads/<?= e($img['file_path']) ?>" alt="" loading="lazy" class="h-full w-full object-cover">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Buy panel -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl"><?= e($product['name']) ?></h1>
            <div class="gold-rule mt-3 w-16"></div>
            <p class="mt-3 text-sm text-muted-ink">SKU: <span class="tabular-nums"><?= e($product['sku']) ?></span></p>

            <p class="mt-4 text-2xl font-bold tabular-nums">
                <?= $fromPrice !== null ? 'From ' . money_fmt(money_to_cents((string)$fromPrice)) : '' ?>
            </p>

            <?php if ($product['short_description']): ?>
                <p class="mt-3 text-[15px] leading-relaxed text-muted-ink"><?= e($product['short_description']) ?></p>
            <?php endif; ?>

            <form method="post" action="/cart/add" id="add-to-cart-form" class="mt-6 space-y-5">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

                <div>
                    <label for="variant" class="block text-sm font-semibold">Size / Color</label>
                    <?php if ($variants): ?>
                        <div class="mt-2 flex flex-wrap gap-2" role="radiogroup" aria-label="Choose variant">
                            <?php foreach ($variants as $i => $v): ?>
                                <?php
                                $effV = ($v['sale_price'] !== null && (float)$v['sale_price'] < (float)$v['price']) ? (float)$v['sale_price'] : (float)$v['price'];
                                $out  = !$mto && ($v['available'] === null || (int)$v['available'] < 1);
                                $label = variant_label((string)$v['sku'], $v['size_label'] ?? null, $v['length_label'] ?? null, $v['color_label'] ?? null);
                                ?>
                                <label class="variant-chip inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition
                                    <?= $out ? 'cursor-not-allowed border-line bg-canvas text-muted-ink line-through' : 'border-line bg-surface hover:border-gold' ?>">
                                    <input type="radio" name="variant_id" value="<?= (int)$v['id'] ?>" data-price="<?= e(number_format($effV, 2, '.', '')) ?>"
                                           data-sku="<?= e($v['sku']) ?>" data-out="<?= $out ? '1' : '0' ?>"
                                           data-size="<?= e((string)($v['size_label'] ?? '')) ?>"
                                           class="sr-only" <?= $i === 0 ? 'checked' : '' ?> <?= $out ? 'disabled' : '' ?>>
                                    <span><?= e($label) ?></span>
                                    <?php if (!$mto && !$out && (int)$v['available'] <= 5): ?>
                                        <span class="text-[11px] font-semibold text-warning">Only <?= (int)$v['available'] ?> left</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="mt-2 text-sm text-danger">No variants available for this product.</p>
                    <?php endif; ?>
                </div>

                <?php if ($mto && $variants): ?>
                <div class="rounded-2xl border border-gold/40 bg-gold-soft/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label for="custom-size" class="text-sm font-semibold">Custom size (inches)</label>
                        <span class="text-xs font-bold tabular-nums text-gold-deep"><?= e(product_size_display($sizeMin)) ?>&Prime; &ndash; <?= e(product_size_display($sizeMax)) ?>&Prime;</span>
                    </div>
                    <div class="mt-3 grid items-center gap-3 sm:grid-cols-[1fr_6.5rem]">
                        <input id="custom-size-range" type="range"
                               min="<?= e(product_size_display($sizeMin)) ?>" max="<?= e(product_size_display($sizeMax)) ?>"
                               step="0.5" value="<?= e(product_size_display($defaultSize)) ?>" data-size-range
                               class="h-2 w-full cursor-pointer accent-[#30351B]" aria-label="Custom size in inches">
                        <div class="flex items-center gap-2">
                            <input id="custom-size" name="custom_size" type="number" inputmode="decimal"
                                   min="<?= e(product_size_display($sizeMin)) ?>" max="<?= e(product_size_display($sizeMax)) ?>"
                                   step="0.01" value="<?= e(product_size_display($defaultSize)) ?>" data-size-number
                                   class="h-12 w-full rounded-xl border border-line bg-surface px-3 text-base tabular-nums outline-none focus:border-accent">
                            <span class="text-sm font-semibold text-muted-ink">in</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-ink">Slide or type any size in this range. The preset you pick sets the colour and price.</p>
                    <p data-size-error class="mt-1 hidden text-xs font-semibold text-danger" role="alert"></p>
                </div>
                <?php endif; ?>

                <div class="flex items-end gap-3">
                    <div>
                        <label for="qty" class="block text-sm font-semibold">Quantity</label>
                        <input id="qty" name="qty" type="number" inputmode="numeric" min="1" max="99" value="1"
                               class="mt-1.5 h-12 w-24 rounded-xl border border-line bg-surface px-3 text-base tabular-nums outline-none focus:border-accent">
                    </div>
                    <p id="selected-price" class="pb-2 text-lg font-bold tabular-nums" aria-live="polite"></p>
                </div>

                <div class="sticky bottom-4 z-20 -mx-1 bg-canvas/90 p-1 backdrop-blur sm:static sm:bg-transparent sm:p-0">
                    <button type="submit" <?= $variants ? '' : 'disabled' ?>
                            class="inline-flex h-13 w-full items-center justify-center rounded-full bg-ink px-6 text-base font-semibold text-white transition hover:bg-gold-deep disabled:cursor-not-allowed disabled:opacity-40"
                            <?= $mto ? 'data-mto="1"' : '' ?>>
                        <?= $mto ? 'Order (made to order)' : 'Add to cart' ?>
                    </button>
                </div>
                <?php if ($mto && $product['lead_time_days']): ?>
                    <p class="text-sm text-muted-ink">Made to order &mdash; ready in about <?= (int)$product['lead_time_days'] ?> days.</p>
                <?php endif; ?>
            </form>

            <div class="mt-8 space-y-4 border-t border-line pt-6 text-sm">
                <?php if ((string)$product['description'] !== ''): ?>
                    <div>
                        <p class="font-semibold">Description</p>
                        <p class="mt-1 leading-relaxed text-muted-ink"><?= nl2br(e((string)$product['description'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Share this product -->
            <div class="mt-8 border-t border-line pt-6">
                <p class="text-sm font-semibold">Share this <?= $product['category_name'] ? e(mb_strtolower((string)$product['category_name'])) : 'product' ?></p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" data-share-native
                            data-share-title="<?= e($shareTitle) ?>" data-share-text="<?= e($shareText) ?>" data-share-url="<?= e($shareUrl) ?>"
                            class="inline-flex h-10 items-center rounded-full border border-line bg-surface px-4 text-sm font-semibold transition hover:border-gold hover:bg-gold-soft/40">
                        Share
                    </button>
                    <button type="button" data-share-copy data-share-url="<?= e($shareUrl) ?>"
                            class="inline-flex h-10 items-center rounded-full border border-line bg-surface px-4 text-sm font-semibold transition hover:border-gold hover:bg-gold-soft/40">
                        Copy link
                    </button>
                    <a href="https://wa.me/?text=<?= e(rawurlencode($shareTitle . ' ' . $shareUrl)) ?>" target="_blank" rel="noopener noreferrer"
                       class="inline-flex h-10 items-center rounded-full border border-line bg-surface px-4 text-sm font-semibold transition hover:border-gold hover:bg-gold-soft/40">
                        WhatsApp
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($shareUrl)) ?>" target="_blank" rel="noopener noreferrer"
                       class="inline-flex h-10 items-center rounded-full border border-line bg-surface px-4 text-sm font-semibold transition hover:border-gold hover:bg-gold-soft/40">
                        Facebook
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($shareUrl)) ?>&amp;text=<?= e(rawurlencode($shareTitle)) ?>" target="_blank" rel="noopener noreferrer"
                       class="inline-flex h-10 items-center rounded-full border border-line bg-surface px-4 text-sm font-semibold transition hover:border-gold hover:bg-gold-soft/40">
                        X
                    </a>
                </div>
                <p data-share-status role="status" aria-live="polite" class="mt-2 hidden text-xs font-semibold text-success"></p>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="variants-data" nonce="<?= e($nonce) ?>"><?= js(array_map(static fn($v) => [
    'id' => (int)$v['id'],
    'sku' => (string)$v['sku'],
    'price' => (float)(($v['sale_price'] !== null && (float)$v['sale_price'] < (float)$v['price']) ? $v['sale_price'] : $v['price']),
], $variants)) ?></script>
<?php }, [
    'meta_title' => $product['meta_title'] ?: null,
    'meta_description' => $product['meta_description'] ?: (string)($product['short_description'] ?: $product['name']),
    'og_type' => 'product',
    'canonical' => $shareUrl,
    'meta_image' => $shareImage,
]);
