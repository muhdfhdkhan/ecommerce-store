<?php
// modules/store/layout.php — shared storefront chrome (header/footer), DESIGN.md §6, §7 §10.
// Call store_layout(function() { ...page content... }) from each page.
declare(strict_types=1);

require_once BASE_PATH . '/modules/orders/cart_service.php';

function store_layout(string $title, callable $content, array $opts = []): void
{
    /** @var string $CSP_NONCE */
    global $CSP_NONCE;
    $cartCount = cart_view()['count'] ?? 0;
    $headerCompact = $opts['compact_header'] ?? false;

    // Brand + ticker settings (single query; missing keys fall back below).
    $brand = db()->query(
        "SELECT setting_key, setting_value FROM store_settings
         WHERE setting_key IN ('store_name','logo_path','ticker_text','ticker_active')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $storeName = trim((string)($brand['store_name'] ?? ''));
    if ($storeName === '') {
        $storeName = 'Patches&Motifs';
    }
    $logo = trim((string)($brand['logo_path'] ?? ''));
    $tickerActive = ($brand['ticker_active'] ?? '1') === '1';
    $tickerText = trim((string)($brand['ticker_text'] ?? ''));
    if ($tickerText === '') {
        $tickerText = 'Delivery All Over Pakistan | Free Delivery in Faisalabad';
    }
    // Duplicate content twice for a seamless CSS marquee loop.
    $tickerItems = array_values(array_filter(array_map('trim', explode('|', $tickerText))));
    if (!$tickerItems) { $tickerItems = [$tickerText]; }
    $tickerRun = implode('&nbsp;&nbsp;&bull;&nbsp;&nbsp;', array_map('e', array_merge($tickerItems, $tickerItems)));
    ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($opts['meta_title'] ?? $title) ?> — <?= e($storeName) ?></title>
<meta name="description" content="<?= e($opts['meta_description'] ?? 'Embroidery patches, motifs and textile embellishments by ' . $storeName . '.') ?>">
<?php
$ogTitle = (string)($opts['meta_title'] ?? $title);
$ogDescription = (string)($opts['meta_description'] ?? 'Embroidery patches, motifs and textile embellishments by ' . $storeName . '.');
$ogUrl = (string)($opts['canonical'] ?? (rtrim((string)config('url'), '/') . (strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/')));
$ogImage = trim((string)($opts['meta_image'] ?? ''));
$ogType = (string)($opts['og_type'] ?? 'website');
?>
<meta property="og:site_name" content="<?= e($storeName) ?>">
<meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDescription) ?>">
<meta property="og:url" content="<?= e($ogUrl) ?>">
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($ogDescription) ?>">
<?php if ($logo !== ''): ?>
<link rel="icon" type="image/jpeg" href="/uploads/<?= e($logo) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= asset_url('/assets/css/app.css') ?>">
<script type="module" src="<?= asset_url('/assets/js/store.js') ?>"></script>
</head>
<body class="min-h-full bg-canvas text-ink antialiased">
    <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur">
        <?php if ($tickerActive): ?>
        <!-- Fixed sliding ticker: shown on every page (text managed in Settings). -->
        <div class="overflow-hidden bg-ink py-1.5 text-white" aria-label="Delivery information">
            <p class="ticker whitespace-nowrap text-[13px] font-medium tracking-wide" data-ticker>
                <?= $tickerRun ?>
            </p>
        </div>
        <?php endif; ?>
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
            <a href="/" class="flex items-center gap-2.5" aria-label="<?= e($storeName) ?> home">
                <?php if ($logo !== ''): ?>
                    <img src="/uploads/<?= e($logo) ?>" alt="" class="h-9 w-9 rounded-full object-cover ring-1 ring-gold/50">
                <?php else: ?>
                    <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-[13px] font-bold tracking-tight text-white ring-1 ring-gold/60">P&amp;M</span>
                <?php endif; ?>
                <span class="text-[15px] font-bold tracking-tight"><?= e($storeName) ?></span>
            </a>
            <nav aria-label="Primary" class="ml-6 hidden items-center gap-1 lg:flex">
                <a href="/shop" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-gold-soft hover:text-gold-deep">Shop</a>
                <a href="/collections" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-gold-soft hover:text-gold-deep">Collections</a>
                <a href="/about" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-gold-soft hover:text-gold-deep">About</a>
                <a href="/contact" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-gold-soft hover:text-gold-deep">Contact</a>
                <a href="/track" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-gold-soft hover:text-gold-deep">Track order</a>
            </nav>
            <div class="ml-auto flex items-center gap-2">
                <!-- Search: expanding bar (GET /shop) next to the cart button. -->
                <button type="button" data-search-toggle aria-label="Search products" aria-expanded="false"
                        class="grid h-11 w-11 place-items-center rounded-full transition hover:bg-canvas">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/></svg>
                </button>
                <a href="/cart" class="relative grid h-11 w-11 place-items-center rounded-full transition hover:bg-canvas" aria-label="Cart (<?= (int)$cartCount ?> items)">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1.2 12.2a1.8 1.8 0 0 1-1.8 1.8H9a1.8 1.8 0 0 1-1.8-1.8L6 7Z"/><path d="M9 7a3 3 0 1 1 6 0"/></svg>
                    <span class="cart-count absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-ink px-1 text-[10px] font-bold text-white tabular-nums" data-cart-count><?= (int)$cartCount ?></span>
                </a>
                <a href="/account" class="hidden h-11 items-center rounded-full border border-line px-4 text-sm font-semibold transition hover:border-gold hover:bg-gold-soft hover:text-gold-deep sm:inline-flex">Account</a>
                <button type="button" data-store-menu-open aria-label="Open menu"
                        class="grid h-11 w-11 place-items-center rounded-full transition hover:bg-canvas lg:hidden">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
        <!-- Expanding search bar (hidden until the search button is tapped). -->
        <div data-search-bar class="hidden border-t border-line bg-surface">
            <form method="get" action="/shop" role="search" class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6">
                <input name="q" type="search" maxlength="80" placeholder="Search patches and motifs&hellip;"
                       class="h-11 w-full rounded-xl border border-line bg-canvas/60 px-4 text-sm" aria-label="Search products">
                <button class="inline-flex h-11 shrink-0 items-center rounded-xl bg-ink px-5 text-sm font-semibold text-white">Search</button>
            </form>
        </div>
    </header>

    <!-- Mobile drawer -->
    <div data-store-menu-backdrop class="fixed inset-0 z-40 hidden bg-ink/40 lg:hidden"></div>
    <aside data-store-menu class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full bg-surface p-5 transition-transform duration-200 lg:hidden">
        <div class="mb-6 flex items-center justify-between">
            <span class="text-base font-bold">Menu</span>
            <button type="button" data-store-menu-close aria-label="Close menu" class="grid h-11 w-11 place-items-center rounded-full hover:bg-canvas">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <nav aria-label="Mobile" class="space-y-1 text-[15px] font-medium">
            <a href="/" class="block rounded-xl px-3 py-3 hover:bg-canvas">Home</a>
            <a href="/shop" class="block rounded-xl px-3 py-3 hover:bg-canvas">Shop</a>
            <a href="/collections" class="block rounded-xl px-3 py-3 hover:bg-canvas">Collections</a>
            <a href="/account" class="block rounded-xl px-3 py-3 hover:bg-canvas">Account</a>
            <a href="/track" class="block rounded-xl px-3 py-3 hover:bg-canvas">Track order</a>
            <a href="/about" class="block rounded-xl px-3 py-3 hover:bg-canvas">About</a>
            <a href="/contact" class="block rounded-xl px-3 py-3 hover:bg-canvas">Contact</a>
        </nav>
    </aside>

    <main id="main">
        <?php
        // Announcement bar (managed in Admin → Website → Banners; stored in store_settings).
        $ann = db()->query(
            "SELECT setting_key, setting_value FROM store_settings
             WHERE setting_key IN ('announcement_text','announcement_active')"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        if (($ann['announcement_active'] ?? '0') === '1' && ($ann['announcement_text'] ?? '') !== ''): ?>
        <div class="bg-ink px-4 py-2 text-center text-sm font-medium text-white"><?= e((string)$ann['announcement_text']) ?></div>
        <?php endif; ?>
        <?php $content(); ?>
    </main>

    <footer class="mt-16 bg-ink text-white">
        <div class="h-px bg-gradient-to-r from-transparent via-gold to-transparent"></div>
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:py-16">
            <div class="grid gap-10 md:grid-cols-4">
                <div class="md:col-span-2">
                    <span class="text-lg font-bold tracking-tight"><?= e($storeName) ?></span>
                    <p class="mt-3 max-w-sm text-sm text-white/70">Embroidery that makes every detail matter. Patches, motifs and textile embellishments crafted to be noticed.</p>
                    <a href="/track" class="mt-5 inline-flex h-10 items-center rounded-full border border-gold/60 px-5 text-sm font-semibold text-gold transition hover:bg-gold hover:text-ink">Track your order</a>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gold">Shop</p>
                    <ul class="mt-3 space-y-2 text-sm text-white/70">
                        <li><a href="/shop" class="hover:text-gold">All products</a></li>
                        <li><a href="/collections" class="hover:text-gold">Collections</a></li>
                        <li><a href="/cart" class="hover:text-gold">Cart</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gold">Support</p>
                    <ul class="mt-3 space-y-2 text-sm text-white/70">
                        <li><a href="/track" class="hover:text-gold">Track an order</a></li>
                        <li><a href="/contact" class="hover:text-gold">Contact us</a></li>
                        <li><a href="/page/shipping-policy" class="hover:text-gold">Shipping</a></li>
                        <li><a href="/page/returns-policy" class="hover:text-gold">Returns</a></li>
                        <li><a href="/page/privacy-policy" class="hover:text-gold">Privacy</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-10 flex flex-wrap items-center justify-between gap-2 border-t border-gold/25 pt-6 text-xs text-white/50">
                <span>&copy; <?= e(gmdate('Y')) ?> <?= e($storeName) ?>. All rights reserved.</span>
                <span class="tracking-[0.18em] uppercase text-gold/70">Crafted in Pakistan</span>
            </div>
        </div>
    </footer>
</body>
</html>
<?php
}
