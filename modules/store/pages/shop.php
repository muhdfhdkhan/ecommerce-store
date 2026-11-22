<?php
// modules/store/pages/shop.php — product grid with search/filter/sort (DESIGN.md §7, FR-CAT-*).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

require_once BASE_PATH . '/modules/products/service.php';

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
$categoryId = int_in($_GET['category'] ?? null);
$sort = (string)($_GET['sort'] ?? 'newest');
if (!in_array($sort, ['newest', 'price_asc', 'price_desc', 'name_asc'], true)) {
    $sort = 'newest';
}
$onSale = (($_GET['sale'] ?? '') === '1');
$page = max(1, int_in($_GET['page'] ?? 1, 1, 10000) ?? 1);

$result = products_search($q, [
    'category_id' => $categoryId,
    'sort' => $sort,
    'on_sale' => $onSale,
], 12, $page);

$cats = db()->prepare(
    "SELECT id, name FROM categories WHERE status = 'active' AND parent_id IS NULL AND deleted_at IS NULL ORDER BY name"
);
$cats->execute();
$categories = $cats->fetchAll();

// Selected category (banner + heading) — active only.
$activeCategory = null;
if ($categoryId !== null) {
    $cst = db()->prepare(
        "SELECT id, name, description, image_path FROM categories
         WHERE id = :id AND status = 'active' AND deleted_at IS NULL LIMIT 1"
    );
    $cst->execute([':id' => $categoryId]);
    $activeCategory = $cst->fetch() ?: null;
}

$totalPages = max(1, (int)ceil($result['total'] / $result['per_page']));

store_layout($q !== '' ? "Search: $q" : 'Shop', function () use ($result, $categories, $activeCategory, $q, $categoryId, $sort, $onSale, $page, $totalPages) { ?>
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-12">
    <div class="gold-rule mb-5 w-20"></div>
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl"><?= $q !== '' ? 'Search results' : ($activeCategory ? e((string)$activeCategory['name']) : 'Shop all') ?></h1>
    <p class="mt-1 text-sm text-muted-ink tabular-nums"><?= (int)$result['total'] ?> product<?= $result['total'] == 1 ? '' : 's' ?></p>

    <?php if ($activeCategory): ?>
    <section class="mt-6 overflow-hidden rounded-3xl border border-line bg-surface sm:grid sm:grid-cols-[240px_1fr]">
        <?php if (!empty($activeCategory['image_path'])): ?>
            <div class="aspect-[4/3] bg-stone-soft sm:aspect-auto sm:h-full">
                <img src="/uploads/<?= e($activeCategory['image_path']) ?>" alt="<?= e((string)$activeCategory['name']) ?>"
                     class="h-full w-full object-cover">
            </div>
        <?php endif; ?>
        <div class="flex flex-col justify-center p-5 sm:p-7">
            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-gold-deep">Category</p>
            <h2 class="mt-1 text-xl font-bold tracking-tight sm:text-2xl"><?= e((string)$activeCategory['name']) ?></h2>
            <?php if (!empty($activeCategory['description'])): ?>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-muted-ink"><?= e((string)$activeCategory['description']) ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Search + filters -->
    <form method="get" action="/shop" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="sr-only" for="q">Search products</label>
        <input id="q" name="q" type="search" value="<?= e($q) ?>" maxlength="80" placeholder="Search patches and motifs&hellip;"
               class="h-12 w-full rounded-full border border-line bg-surface px-5 text-base outline-none focus:border-accent sm:max-w-sm">
        <label class="sr-only" for="category">Category</label>
        <select id="category" name="category" class="h-12 rounded-full border border-line bg-surface px-4 text-sm">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="sort">Sort</label>
        <select id="sort" name="sort" class="h-12 rounded-full border border-line bg-surface px-4 text-sm">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A&ndash;Z</option>
        </select>
        <label class="inline-flex h-12 shrink-0 items-center gap-2 rounded-full border border-line bg-surface px-4 text-sm">
            <input type="checkbox" name="sale" value="1" <?= $onSale ? 'checked' : '' ?> class="h-4 w-4 accent-[#30351B]"> On sale
        </label>
        <button type="submit" class="inline-flex h-12 shrink-0 items-center justify-center rounded-full bg-ink px-6 text-sm font-semibold text-white hover:bg-gold-deep">Apply</button>
    </form>

    <?php if (empty($result['items'])): ?>
        <div class="mt-12 rounded-3xl border border-line bg-surface p-10 text-center">
            <p class="text-lg font-semibold">Nothing found</p>
            <p class="mt-1 text-sm text-muted-ink">Try a different search or browse the whole shop.</p>
            <a href="/shop" class="mt-5 inline-flex h-11 items-center rounded-full bg-ink px-6 text-sm font-semibold text-white">View all products</a>
        </div>
    <?php else: ?>
        <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 lg:gap-6">
            <?php foreach ($result['items'] as $p): ?>
                <?php
                $eff   = ($p['sale_price'] !== null && (float)$p['sale_price'] < (float)$p['base_price']) ? $p['sale_price'] : $p['base_price'];
                $onSaleItem = $eff !== $p['base_price'];
                $img   = $p['image_path'] !== '' ? '/uploads/' . e($p['image_path']) : null;
                ?>
                <article class="group relative overflow-hidden rounded-3xl border border-line bg-surface transition hover:border-gold/60 hover:shadow-md">
                    <a href="/product/<?= e($p['slug']) ?>" class="block">
                        <div class="aspect-square overflow-hidden bg-stone-soft">
                            <?php if ($img): ?>
                                <img src="<?= $img ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async"
                                     class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.03]">
                            <?php endif; ?>
                        </div>
                        <div class="p-3 sm:p-4">
                            <?php if ($onSaleItem): ?><span class="mb-1 inline-block rounded-full bg-gold-soft px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-gold-deep">Sale</span><?php endif; ?>
                            <h3 class="line-clamp-2 min-h-10 text-[15px] font-semibold leading-snug"><?= e($p['name']) ?></h3>
                            <p class="mt-1.5 text-sm font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$eff)) ?>
                                <?php if ($onSaleItem): ?><s class="ml-1 text-xs font-normal text-muted-ink"><?= money_fmt(money_to_cents((string)$p['base_price'])) ?></s><?php endif; ?>
                            </p>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-10 flex flex-wrap items-center justify-center gap-2" aria-label="Pagination">
                <?php $mk = fn(int $p) => '/shop?' . http_build_query(['q' => $q, 'category' => $categoryId ?: '', 'sort' => $sort, 'sale' => $onSale ? '1' : '', 'page' => $p]); ?>
                <?php if ($page > 1): ?><a href="<?= e($mk($page - 1)) ?>" class="inline-flex h-11 items-center rounded-full border border-line px-4 text-sm font-medium hover:bg-canvas">&larr; Prev</a><?php endif; ?>
                <span class="px-3 text-sm text-muted-ink tabular-nums">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
                <?php if ($page < $totalPages): ?><a href="<?= e($mk($page + 1)) ?>" class="inline-flex h-11 items-center rounded-full border border-line px-4 text-sm font-medium hover:bg-canvas">Next &rarr;</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php }, [
    'meta_title' => 'Shop patches & motifs',
    'meta_description' => 'Browse embroidered patches, motifs and made-to-order labels.',
]);
