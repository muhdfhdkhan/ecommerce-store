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
    $storeName = 'Patches&Motifs';
    $headerCompact = $opts['compact_header'] ?? false;
    ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= e($storeName) ?></title>
<meta name="description" content="<?= e($opts['meta_description'] ?? 'Embroidery patches, motifs and textile embellishments by ' . $storeName . '.') ?>">
<link rel="stylesheet" href="/assets/css/app.css">
<script type="module" src="/assets/js/store.js"></script>
</head>
<body class="min-h-full bg-canvas text-ink antialiased">
    <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
            <a href="/" class="flex items-center gap-2.5" aria-label="<?= e($storeName) ?> home">
                <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-[13px] font-bold tracking-tight text-white">P&amp;M</span>
                <span class="text-[15px] font-bold tracking-tight">Patches&amp;Motifs</span>
            </a>
            <nav aria-label="Primary" class="ml-6 hidden items-center gap-1 lg:flex">
                <a href="/shop" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-canvas">Shop</a>
                <a href="/collections" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-canvas">Collections</a>
                <a href="/about" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-canvas">About</a>
                <a href="/contact" class="rounded-full px-4 py-2 text-sm font-medium text-ink transition hover:bg-canvas">Contact</a>
            </nav>
            <div class="ml-auto flex items-center gap-2">
                <a href="/cart" class="relative grid h-11 w-11 place-items-center rounded-full transition hover:bg-canvas" aria-label="Cart (<?= (int)$cartCount ?> items)">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1.2 12.2a1.8 1.8 0 0 1-1.8 1.8H9a1.8 1.8 0 0 1-1.8-1.8L6 7Z"/><path d="M9 7a3 3 0 1 1 6 0"/></svg>
                    <span class="cart-count absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-ink px-1 text-[10px] font-bold text-white tabular-nums" data-cart-count><?= (int)$cartCount ?></span>
                </a>
                <a href="/account" class="hidden h-11 items-center rounded-full border border-line px-4 text-sm font-semibold transition hover:bg-canvas sm:inline-flex">Account</a>
                <button type="button" data-store-menu-open aria-label="Open menu"
                        class="grid h-11 w-11 place-items-center rounded-full transition hover:bg-canvas lg:hidden">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
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
            <a href="/about" class="block rounded-xl px-3 py-3 hover:bg-canvas">About</a>
            <a href="/contact" class="block rounded-xl px-3 py-3 hover:bg-canvas">Contact</a>
        </nav>
    </aside>

    <main id="main"><?php $content(); ?></main>

    <footer class="mt-16 bg-ink text-white">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:py-16">
            <div class="grid gap-10 md:grid-cols-4">
                <div class="md:col-span-2">
                    <span class="text-lg font-bold tracking-tight">Patches&amp;Motifs</span>
                    <p class="mt-3 max-w-sm text-sm text-white/70">Embroidery that makes every detail matter. Patches, motifs and textile embellishments crafted to be noticed.</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white/90">Shop</p>
                    <ul class="mt-3 space-y-2 text-sm text-white/70">
                        <li><a href="/shop" class="hover:text-white">All products</a></li>
                        <li><a href="/collections" class="hover:text-white">Collections</a></li>
                        <li><a href="/cart" class="hover:text-white">Cart</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white/90">Support</p>
                    <ul class="mt-3 space-y-2 text-sm text-white/70">
                        <li><a href="/contact" class="hover:text-white">Contact us</a></li>
                        <li><a href="/page/shipping-policy" class="hover:text-white">Shipping</a></li>
                        <li><a href="/page/returns-policy" class="hover:text-white">Returns</a></li>
                        <li><a href="/page/privacy-policy" class="hover:text-white">Privacy</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-10 border-t border-white/15 pt-6 text-xs text-white/50">
                &copy; <?= e(gmdate('Y')) ?> Patches&amp;Motifs. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>
<?php
}
