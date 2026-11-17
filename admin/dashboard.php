<?php
// admin/dashboard.php â€” authenticated admin area. Phase 1 placeholder shell.
// Every admin page: authenticate, then authorize (SECURITY.md Â§11.2).
declare(strict_types=1);

require_once BASE_PATH . '/includes/bootstrap.php';

/** @var string $CSP_NONCE */

if (!admin_session_valid()) {
    safe_redirect('/admin/login');
}
admin_touch_session();

$adminName = (string)($_SESSION['admin_name'] ?? 'Admin');
$initial   = strtoupper(mb_substr($adminName, 0, 1));
$storeName = 'Patches&amp;Motifs';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Dashboard &mdash; Patches&amp;Motifs Admin</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script type="module" src="/assets/js/admin.js"></script>
</head>
<body class="min-h-full bg-canvas text-ink antialiased">
    <!-- Top bar -->
    <header class="sticky top-0 z-30 border-b border-line bg-surface">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
            <button type="button" data-drawer-open aria-label="Open menu"
                    class="grid h-11 w-11 place-items-center rounded-xl border border-line lg:hidden">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-[13px] font-bold tracking-tight text-white">P&amp;M</span>
            <span class="text-base font-bold tracking-tight">Patches&amp;Motifs</span>
            <span class="ml-1 hidden rounded-full bg-line/70 px-2.5 py-1 text-[11px] font-semibold text-muted-ink sm:inline">Admin</span>

            <div class="ml-auto flex items-center gap-3">
                <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-full bg-accent font-bold text-white"><?= e($initial) ?></span>
                <form method="post" action="/admin/logout">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="inline-flex h-11 items-center rounded-full border border-line px-4 text-sm font-semibold transition hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:py-8">
        <!-- Sidebar (desktop) / drawer (mobile) -->
        <div data-drawer-backdrop class="fixed inset-0 z-40 hidden bg-ink/40 lg:hidden"></div>
        <aside data-drawer class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full border-r border-line bg-surface p-4 transition-transform duration-200 lg:sticky lg:top-24 lg:h-[calc(100vh-8rem)] lg:w-60 lg:shrink-0 lg:translate-x-0 lg:rounded-2xl lg:border">
            <nav aria-label="Admin navigation" class="space-y-1">
                <a href="/admin/dashboard" aria-current="page" class="flex h-11 items-center gap-3 rounded-xl bg-canvas px-3 text-sm font-semibold">Overview</a>
                <span class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm text-muted-ink">Catalog</span>
                <span class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm text-muted-ink">Orders</span>
                <span class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm text-muted-ink">Customers</span>
                <span class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm text-muted-ink">Reports</span>
                <span class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm text-muted-ink">Settings</span>
            </nav>
        </aside>

        <!-- Main -->
        <main class="min-w-0 flex-1">
            <!-- KPI cards (placeholders while Phase 1 completes) -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <div class="rounded-2xl border border-line bg-surface p-4">
                    <p class="text-xs font-medium text-muted-ink">Sales</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">&mdash;</p>
                </div>
                <div class="rounded-2xl border border-line bg-surface p-4">
                    <p class="text-xs font-medium text-muted-ink">Orders</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">&mdash;</p>
                </div>
                <div class="rounded-2xl border border-line bg-surface p-4">
                    <p class="text-xs font-medium text-muted-ink">Pending</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">&mdash;</p>
                </div>
                <div class="rounded-2xl border border-line bg-surface p-4">
                    <p class="text-xs font-medium text-muted-ink">Low stock</p>
                    <p class="mt-2 text-xl font-bold tabular-nums">&mdash;</p>
                </div>
            </div>

            <!-- Welcome card -->
            <section class="mt-6 rounded-3xl border border-line bg-surface p-6 sm:p-8">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Welcome, <?= e($adminName) ?> &#128075;</h1>
                <p class="mt-2 max-w-xl text-sm text-muted-ink sm:text-base">
                    You are signed in to the <strong class="text-ink"><?= $storeName ?></strong> admin portal.
                    Catalog, orders and store management modules will appear here as each phase is delivered.
                </p>
            </section>

            <p class="mt-6 text-center text-xs text-muted-ink lg:text-left">
                KPI definitions and widgets follow DESIGN.md &sect;11 and respect role permissions once modules are active.
            </p>
        </main>
    </div>
</body>
</html>
