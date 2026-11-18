<?php
// modules/store/pages/home.php — homepage per DESIGN.md §7 (hero, categories, deals, collections).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

require_once BASE_PATH . '/modules/products/service.php';

// Featured (deals) = on-sale products; New arrivals = latest; Categories = top level.
$deals = products_search('', ['on_sale' => true, 'sort' => 'newest'], 4, 1)['items'];
$new   = products_search('', ['sort' => 'newest'], 8, 1)['items'];
$cats  = db()->query(
    "SELECT id, name, slug FROM categories WHERE status = 'active' AND deleted_at IS NULL ORDER BY display_order, name LIMIT 6"
)->fetchAll();
$collections = db()->query(
    "SELECT title, slug FROM collections WHERE status = 'active' AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
     AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP()) ORDER BY sort_order LIMIT 4"
)->fetchAll();

store_layout('Embroidery patches & motifs', function () use ($deals, $new, $cats, $collections) { ?>
    <!-- Section 2 — Editorial hero (DESIGN.md §7.2) -->
    <section class="px-4 pt-6 sm:px-6">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-[36px] bg-sage">
            <div class="grid items-center gap-6 p-6 sm:p-10 lg:grid-cols-2 lg:p-14">
                <div>
                    <p class="text-sm font-semibold text-accent">Crafted details. Distinctive finishes.</p>
                    <h1 class="mt-3 text-4xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-6xl">Embroidery that makes every detail matter.</h1>
                    <p class="mt-4 max-w-md text-[15px] leading-relaxed text-muted-ink sm:text-base">Explore patches and motifs made to bring your ideas to life &mdash; from bridal sets to everyday accents.</p>
                    <a href="/shop" class="mt-7 inline-flex h-12 items-center rounded-full bg-ink px-7 text-base font-semibold text-white transition hover:bg-ink/90">Explore the collection</a>
                </div>
                <div class="aspect-[4/3] overflow-hidden rounded-[28px] bg-stone-soft lg:aspect-auto lg:h-[420px]">
                    <!-- Editorial photo slot: replace with brand photography per DESIGN.md §14 -->
                    <div class="grid h-full w-full place-items-center text-muted-ink">
                        <svg viewBox="0 0 24 24" class="h-16 w-16 opacity-30" fill="none" stroke="currentColor" stroke-width="1"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M7.5 9.5l9 5M16.5 9.5l-9 5" stroke-linecap="round"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 4 — Popular categories -->
    <?php if ($cats): ?>
    <section class="px-4 pt-14 sm:px-6 lg:pt-20">
        <div class="mx-auto max-w-7xl">
            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Explore popular categories</h2>
            <div class="mt-6 flex gap-4 overflow-x-auto pb-2 lg:grid lg:grid-cols-6 lg:overflow-visible">
                <?php foreach ($cats as $c): ?>
                    <a href="/shop?category=<?= (int)$c['id'] ?>" class="group w-36 shrink-0 lg:w-auto">
                        <div class="aspect-[3/4] overflow-hidden rounded-3xl bg-stone-soft transition group-hover:opacity-90">
                            <div class="grid h-full place-items-center text-muted-ink"><span class="text-sm font-semibold"><?= e(mb_substr($c['name'], 0, 1)) ?></span></div>
                        </div>
                        <p class="mt-2 text-sm font-semibold"><?= e($c['name']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Section 5 — Daily deals (black panel) -->
    <?php if ($deals): ?>
    <section class="px-4 pt-14 sm:px-6 lg:pt-20">
        <div class="mx-auto max-w-7xl rounded-[36px] bg-ink p-6 text-white sm:p-10">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Deals of the day</h2>
                <a href="/shop?sale=1" class="text-sm font-semibold text-white/80 underline-offset-4 hover:text-white hover:underline">View all</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <?php foreach ($deals as $p):
                    $eff = $p['sale_price']; $base = $p['base_price']; ?>
                    <a href="/product/<?= e($p['slug']) ?>" class="group overflow-hidden rounded-3xl bg-white/[0.06] p-3 transition hover:bg-white/10">
                        <div class="aspect-square overflow-hidden rounded-2xl bg-white/10"></div>
                        <h3 class="mt-3 line-clamp-1 text-sm font-semibold"><?= e($p['name']) ?></h3>
                        <p class="mt-1 text-sm font-bold tabular-nums"><?= money_fmt(money_to_cents((string)$eff)) ?>
                            <s class="ml-1 text-xs font-normal text-white/50"><?= money_fmt(money_to_cents((string)$base)) ?></s></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Section 7 — New arrivals -->
    <section class="px-4 pt-14 sm:px-6 lg:pt-20">
        <div class="mx-auto max-w-7xl">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">New arrivals</h2>
                <a href="/shop" class="text-sm font-semibold underline-offset-4 hover:underline">Shop all</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 lg:gap-6">
                <?php foreach (array_slice($new, 0, 8) as $p):
                    $eff = ($p['sale_price'] !== null && (float)$p['sale_price'] < (float)$p['base_price']) ? $p['sale_price'] : $p['base_price']; ?>
                    <article class="group overflow-hidden rounded-3xl border border-line bg-surface transition hover:shadow-md">
                        <a href="/product/<?= e($p['slug']) ?>">
                            <div class="aspect-square bg-stone-soft"></div>
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

    <!-- Section 8 — Typographic marquee (respect reduced motion via CSS) -->
    <section class="mt-16 overflow-hidden border-y border-line bg-surface py-6" aria-hidden="true">
        <p class="marquee whitespace-nowrap text-3xl font-bold tracking-tight text-ink/15 sm:text-5xl">
            DETAILS MAKE THE DESIGN &nbsp;&bull;&nbsp; MADE TO BE NOTICED &nbsp;&bull;&nbsp; DETAILS MAKE THE DESIGN &nbsp;&bull;&nbsp; MADE TO BE NOTICED
        </p>
    </section>
<?php });
