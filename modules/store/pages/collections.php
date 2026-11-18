<?php
// modules/store/pages/collections.php — active collections listing (FR-CAT-COL-*).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

$colls = db()->prepare(
    "SELECT title, slug, description FROM collections
     WHERE status = 'active'
       AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
       AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP())
     ORDER BY sort_order, id DESC"
);
$colls->execute();
$collections = $colls->fetchAll();

store_layout('Collections', function () use ($collections) { ?>
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-12">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Collections</h1>
    <p class="mt-1 text-sm text-muted-ink">Curated edits of our favourite patches and motifs.</p>

    <?php if (!$collections): ?>
        <div class="mt-8 rounded-3xl border border-line bg-surface p-10 text-center">
            <p class="text-lg font-semibold">No collections right now</p>
            <p class="mt-1 text-sm text-muted-ink">Check back soon &mdash; or browse the full shop.</p>
            <a href="/shop" class="mt-5 inline-flex h-11 items-center rounded-full bg-ink px-6 text-sm font-semibold text-white">View all products</a>
        </div>
    <?php else: ?>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($collections as $c): ?>
                <a href="/shop" class="group rounded-3xl border border-line bg-surface p-6 transition hover:shadow-md">
                    <h2 class="text-lg font-bold tracking-tight"><?= e($c['title']) ?></h2>
                    <?php if ((string)$c['description'] !== ''): ?>
                        <p class="mt-2 text-sm leading-relaxed text-muted-ink"><?= e((string)$c['description']) ?></p>
                    <?php endif; ?>
                    <span class="mt-4 inline-block text-sm font-semibold text-accent">Browse &rarr;</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php });
