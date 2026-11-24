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

    // Brand + ticker + footer social settings (single query; missing keys fall back below).
    $brand = db()->query(
        "SELECT setting_key, setting_value FROM store_settings
         WHERE setting_key IN ('store_name','logo_path','ticker_text','ticker_active',
                               'social_facebook_url','social_instagram_url','whatsapp_number','store_phone')"
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

    // Footer social links (Admin → Settings → Social media & WhatsApp).
    $socialUrl = static function (string $value): string {
        $value = trim($value);
        if ($value === '' || safe_url($value) === '#') {
            return '';
        }
        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $value : '';
    };
    $facebookUrl = $socialUrl((string)($brand['social_facebook_url'] ?? ''));
    $instagramUrl = $socialUrl((string)($brand['social_instagram_url'] ?? ''));

    // WhatsApp button: settings number first, store contact phone as a fallback.
    $whatsappLabel = trim((string)($brand['whatsapp_number'] ?? ''));
    if ($whatsappLabel === '') {
        $whatsappLabel = trim((string)($brand['store_phone'] ?? ''));
    }
    $whatsappDigits = (string)preg_replace('/\D+/', '', $whatsappLabel);
    $whatsappUrl = (strlen($whatsappDigits) >= 8 && strlen($whatsappDigits) <= 15)
        ? 'https://wa.me/' . $whatsappDigits
        : '';
    if ($whatsappUrl === '') {
        $whatsappLabel = '';
    }
    $hasSocial = $facebookUrl !== '' || $instagramUrl !== '' || $whatsappUrl !== '';
    ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($opts['meta_title'] ?? $title) ?> — <?= e($storeName) ?></title>
<meta name="description" content="<?= e($opts['meta_description'] ?? 'Embroidery patches, motifs and textile embellishments by ' . $storeName . '.') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
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
        <div class="mb-6 flex items-center justify-between gap-3">
            <a href="/" class="flex min-w-0 items-center gap-2.5" aria-label="<?= e($storeName) ?> home">
                <?php if ($logo !== ''): ?>
                    <img src="/uploads/<?= e($logo) ?>" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-gold/50">
                <?php else: ?>
                    <span aria-hidden="true" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-ink text-[13px] font-bold tracking-tight text-white ring-1 ring-gold/60">P&amp;M</span>
                <?php endif; ?>
                <span class="truncate text-[15px] font-bold tracking-tight"><?= e($storeName) ?></span>
            </a>
            <button type="button" data-store-menu-close aria-label="Close menu" class="grid h-11 w-11 shrink-0 place-items-center rounded-full hover:bg-canvas">
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

                    <?php if ($hasSocial): ?>
                    <div class="mt-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-gold">Follow us</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <?php if ($facebookUrl !== ''): ?>
                            <a href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Facebook"
                               class="grid h-11 w-11 place-items-center rounded-full border border-white/25 text-white/80 transition hover:border-gold hover:bg-gold hover:text-ink">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.44 18.63.07 12 .07S0 5.44 0 12.07c0 5.99 4.39 10.95 10.13 11.85v-8.38H7.08v-3.47h3.05V9.43c0-3.01 1.79-4.67 4.53-4.67 1.31 0 2.69.24 2.69.24v2.95h-1.52c-1.49 0-1.96.93-1.96 1.88v2.25h3.33l-.53 3.47h-2.8v8.38C19.62 23.02 24 18.06 24 12.07Z"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php if ($instagramUrl !== ''): ?>
                            <a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Instagram"
                               class="grid h-11 w-11 place-items-center rounded-full border border-white/25 text-white/80 transition hover:border-gold hover:bg-gold hover:text-ink">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php if ($whatsappUrl !== ''): ?>
                            <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Chat with us on WhatsApp at <?= e($whatsappLabel) ?>"
                               class="inline-flex h-11 items-center gap-2.5 rounded-full border-2 border-whatsapp/80 bg-whatsapp/10 pl-3.5 pr-4 text-sm font-semibold text-whatsapp transition hover:bg-whatsapp hover:text-ink">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.47-2.39-1.48-.88-.79-1.48-1.76-1.66-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.91-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.08.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35M12.05 21.79a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26C2.17 6.44 6.6 2 12.05 2c2.64 0 5.12 1.03 6.99 2.9a9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.44 9.9-9.88 9.9m8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.9c0 2.09.54 4.14 1.58 5.94L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.69 1.45c6.55 0 11.89-5.34 11.89-11.9 0-3.18-1.24-6.17-3.48-8.41Z"/></svg>
                                <span class="tabular-nums"><?= e($whatsappLabel) ?></span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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
            <p class="mt-3 text-xs text-white/50">
                Developed by
                <a href="https://nationaltechforge.netlify.app/contact" target="_blank" rel="noopener noreferrer"
                   class="font-semibold text-gold/80 underline-offset-4 transition hover:text-gold hover:underline">National Tech Forge</a>
            </p>
        </div>
    </footer>
</body>
</html>
<?php
}
