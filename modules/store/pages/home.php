<?php
// modules/store/pages/home.php — storefront homepage placeholder (Phase 3 delivers
// the full homepage per DESIGN.md §7). Kept intentionally minimal.
declare(strict_types=1);

$storeName = 'Patches&amp;Motifs';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patches&amp;Motifs &mdash; Embroidery patches &amp; motifs</title>
    <meta name="description" content="Patches&amp;Motifs — embroidery patches, motifs and textile embellishments.">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-full bg-canvas text-ink antialiased">
    <main class="mx-auto flex min-h-full max-w-6xl flex-col items-center justify-center px-4 py-16 text-center sm:px-6">
        <span aria-hidden="true" class="grid h-14 w-14 place-items-center rounded-full bg-ink text-lg font-bold text-white">P&amp;M</span>
        <h1 class="mt-6 text-4xl font-bold tracking-tight sm:text-5xl">Patches&amp;Motifs</h1>
        <p class="mt-3 max-w-md text-base text-muted-ink">
            Embroidery that makes every detail matter. The storefront is taking shape &mdash; patches, motifs and more are on their way.
        </p>
        <a href="/admin" class="mt-8 inline-flex h-12 items-center rounded-full bg-ink px-6 text-base font-semibold text-white transition hover:bg-ink/90">
            Admin sign in
        </a>
    </main>
</body>
</html>
