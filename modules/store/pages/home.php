<?php
// modules/store/pages/home.php — homepage (DESIGN.md §7): hero slider, categories,
// deals, new arrivals, marquee. Copy is patch/motif focused.
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';
require_once BASE_PATH . '/modules/products/service.php';

// Hero slides: admin-managed via Website → Banners; fall back to defaults.
$hero = db()->prepare(
    "SELECT title, subtitle, image_path, link_url FROM banners
     WHERE is_active = 1 AND image_path IS NOT NULL AND image_path != ''
       AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
       AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP())
     ORDER BY sort_order LIMIT 5"
);
$hero->execute();
$slides = $hero->fetchAll();
if (!$slides) {
    $slides = [
        ['title' => 'Patches that tell your story', 'subtitle' => 'Iron-on and sew-on patches, stitched to be noticed.', 'image_path' => 'hero/slide-1.jpg', 'link_url' => '/shop'],
        ['title' => 'Motifs for every making moment', 'subtitle' => 'From bridal dupattas to denim jackets — find your finish.', 'image_path' => 'hero/slide-2.jpg', 'link_url' => '/shop'],
        ['title' => 'Made to order, made for you', 'subtitle' => 'Name labels and custom runs with a clear lead time.', 'image_path' => 'hero/slide-3.jpg', 'link_url' => '/shop?category=3'],
    ];
}

// Featured (deals) = on-sale products; New arrivals = latest; Categories = top level.
$deals = products_search('', ['on_sale' => true, 'sort' => 'newest'], 4, 1)['items'];
$new   = products_search('', ['sort' => 'newest'], 8, 1)['items'];
$cats  = db()->query(
    "SELECT id, name, slug FROM categories WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, name LIMIT 6"
)->fetchAll();
$collections = db()->prepare(
    "SELECT title, slug, description, image_path FROM collections
     WHERE status = 'active' AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
     AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP()) ORDER BY sort_order LIMIT 4"
);
$collections->execute();
$collections = $collections->fetchAll();

store_layout('Patches & Motifs', function () use ($slides, $deals, $new, $cats, $collections) { ?>
    <!-- Section 2 — Hero slider (DESIGN.md §7.2) -->
    <section class="px-4 pt-6 sm:px-6">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-[36px] bg-sage ring-1 ring-gold/30">
            <div data-hero class="relative">
                <?php foreach ($slides as $i => $s):
                    $img = $s['image_path'] ? '/uploads/' . e($s['image_path']) : null; ?>
                <div data-hero-slide class="grid items-center gap-6 p-6 sm:p-10 lg:grid-cols-2 lg:p-14 <?= $i === 0 ? '' : 'hidden' ?>">
                    <div>
                        <p class="flex items-center gap-2.5 text-[11px] font-bold uppercase tracking-[0.28em] text-gold-deep">
                            <span aria-hidden="true" class="h-px w-8 bg-gold"></span>Patches &amp; Motifs
                        </p>
                        <h1 class="mt-3 text-4xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-6xl"><?= e($s['title']) ?></h1>
                        <?php if (!empty($s['subtitle'])): ?>
                        <p class="mt-4 max-w-md text-[15px] leading-relaxed text-muted-ink sm:text-base"><?= e($s['subtitle']) ?></p>
                        <?php endif; ?>
                        <a href="<?= e($s['link_url'] ?: '/shop') ?>" class="mt-7 inline-flex h-12 items-center rounded-full bg-ink px-7 text-base font-semibold text-white transition hover:bg-gold-deep">Shop patches &amp; motifs</a>
                    </div>
                    <div class="aspect-[4/3] overflow-hidden rounded-[28px] bg-stone-soft ring-1 ring-gold/25 lg:aspect-auto lg:h-[420px]">
                        <?php if ($img): ?>
                            <img src="<?= $img ?>" alt="<?= e($s['title']) ?>" class="h-full w-full object-cover" decoding="async" fetchpriority="<?= $i === 0 ? 'high' : 'low' ?>">
                        <?php else: ?>
                            <div class="grid h-full w-full place-items-center text-muted-ink">
                                <svg viewBox="0 0 24 24" class="h-16 w-16 opacity-30" fill="none" stroke="currentColor" stroke-width="1"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M7.5 9.5l9 5M16.5 9.5l-9 5" stroke-linecap="round"/></svg>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (count($slides) > 1): ?>
                <div class="absolute bottom-5 left-1/2 flex -translate-x-1/2 gap-2">
                    <?php foreach ($slides as $i => $s): ?>
                    <button type="button" data-hero-dot aria-label="Slide <?= $i + 1 ?>"
                            class="h-2.5 w-2.5 rounded-full <?= $i === 0 ? 'bg-gold-deep' : 'bg-ink/20' ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Section 4 — Popular categories -->
    <?php if ($cats): ?>
    <section class="px-4 pt-14 sm:px-6 lg:pt-20">
        <div class="mx-auto max-w-7xl">
            <div class="gold-rule mb-5 w-20"></div>
            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Shop by category</h2>
            <div class="mt-6 flex gap-4 overflow-x-auto pb-2 lg:grid lg:grid-cols-6 lg:overflow-visible">
                <?php foreach ($cats as $c): ?>
                    <a href="/shop?category=<?= (int)$c['id'] ?>" class="group w-36 shrink-0 lg:w-auto">
                        <div class="aspect-[3/4] overflow-hidden rounded-3xl bg-stone-soft ring-1 ring-transparent transition group-hover:ring-gold/60">
                            <div class="grid h-full place-items-center text-muted-ink"><span class="text-3xl font-bold"><?= e(mb_substr($c['name'], 0, 1)) ?></span></div>
                        </div>
                        <p class="mt-2 text-sm font-semibold group-hover:text-gold-deep"><?= e($c['name']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Section 5 — Daily deals (black panel) -->
    <?php if ($deals): ?>
    <section class="px-4 pt-14 sm:px-6 lg:pt-20">
        <div class="mx-auto max-w-7xl rounded-[36px] bg-ink p-6 text-white ring-1 ring-gold/25 sm:p-10">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Deals of the day</h2>
                <a href="/shop?sale=1" class="text-sm font-semibold text-gold underline-offset-4 hover:text-white hover:underline">View all</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <?php foreach ($deals as $p):
                    $eff = $p['sale_price']; $base = $p['base_price'];
                    $dealImg = $p['image_path'] !== '' ? '/uploads/' . e($p['image_path']) : null; ?>
                    <a href="/product/<?= e($p['slug']) ?>" class="group overflow-hidden rounded-3xl bg-white/[0.06] p-3 transition hover:bg-white/10 hover:ring-1 hover:ring-gold/50">
                        <div class="aspect-square overflow-hidden rounded-2xl bg-white/10">
                            <?php if ($dealImg): ?><img src="<?= $dealImg ?>" alt="<?= e($p['name']) ?>" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-[1.03]"><?php endif; ?>
                        </div>
                        <h3 class="mt-3 line-clamp-1 text-sm font-semibold"><?= e($p['name']) ?></h3>
                        <p class="mt-1 text-sm font-bold tabular-nums text-gold"><?= money_fmt(money_to_cents((string)$eff)) ?>
                            <s class="ml-1 text-xs font-normal text-white/40"><?= money_fmt(money_to_cents((string)$base)) ?></s></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Section 6 — Collections strip -->
    <?php if ($collections): ?>
    <section class="px-4 pt-14 sm:px-6 lg:pt-20">
        <div class="mx-auto max-w-7xl">
            <div class="gold-rule mb-5 w-20"></div>
            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Featured collections</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($collections as $c): ?>
                    <div class="overflow-hidden rounded-3xl border border-line bg-surface transition hover:border-gold/60 hover:shadow-md">
                        <div class="aspect-[5/4] bg-stone-soft">
                            <?php if (!empty($c['image_path'])): ?>
                                <img src="/uploads/<?= e($c['image_path']) ?>" alt="<?= e($c['title']) ?>" loading="lazy" class="h-full w-full object-cover">
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold"><?= e($c['title']) ?></h3>
                            <p class="mt-1 line-clamp-2 text-sm text-muted-ink"><?= e((string)$c['description']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Section 7 — New arrivals -->
    <section class="px-4 pt-14 sm:px-6 lg:pt-20">
        <div class="mx-auto max-w-7xl">
            <div class="gold-rule mb-5 w-20"></div>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">New arrivals</h2>
                <a href="/shop" class="text-sm font-semibold underline-offset-4 hover:text-gold-deep hover:underline">Shop all</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 lg:gap-6">
                <?php foreach (array_slice($new, 0, 8) as $p):
                    $eff = ($p['sale_price'] !== null && (float)$p['sale_price'] < (float)$p['base_price']) ? $p['sale_price'] : $p['base_price'];
                    $img = $p['image_path'] !== '' ? '/uploads/' . e($p['image_path']) : null; ?>
                    <article class="group overflow-hidden rounded-3xl border border-line bg-surface transition hover:border-gold/60 hover:shadow-md">
                        <a href="/product/<?= e($p['slug']) ?>">
                            <div class="aspect-square overflow-hidden bg-stone-soft">
                                <?php if ($img): ?><img src="<?= $img ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.03]"><?php endif; ?>
                            </div>
                            <div class="p-3 sm:p-4">
                                <h3 class="line-clamp-2 min-h-10 text-[15px] font-semibold leading-snug"><?= e($p['name']) ?></h3>
                                <p class="mt-1.5 text-sm font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$eff)) ?></p>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Section 8 — Typographic marquee (respects reduced motion via CSS) -->
    <section class="mt-16 overflow-hidden border-y border-gold/25 bg-surface py-6" aria-hidden="true">
        <p class="marquee whitespace-nowrap text-3xl font-bold tracking-tight text-gold/40 sm:text-5xl">
            PATCHES &amp; MOTIFS &nbsp;&bull;&nbsp; MADE TO BE NOTICED &nbsp;&bull;&nbsp; PATCHES &amp; MOTIFS &nbsp;&bull;&nbsp; DETAILS MAKE THE DESIGN
        </p>
    </section>
<?php });
