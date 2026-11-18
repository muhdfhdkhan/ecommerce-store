<?php
// modules/store/pages/info.php — static pages: /about, /contact and CMS /page/{slug}.
// CMS content is stored unmodified-trusted from admin only; output escaped per paragraph
// (no raw HTML from DB — SECURITY.md §6.2).
declare(strict_types=1);

require_once BASE_PATH . '/modules/store/layout.php';

$cmsSlug = (string)($_GET['cms'] ?? '');

if ($cmsSlug !== '') {
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $cmsSlug)) {
        abort(404);
    }
    $st = db()->prepare("SELECT title, content FROM pages WHERE slug = :s AND status = 'published' LIMIT 1");
    $st->execute([':s' => $cmsSlug]);
    $page = $st->fetch();
    if (!$page) {
        abort(404);
    }
    $title = (string)$page['title'];
    $paragraphs = preg_split("/\r\n|\n|\r/", (string)$page['content']) ?: [];
    store_layout($title, function () use ($title, $paragraphs) { ?>
<div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:py-16">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl"><?= e($title) ?></h1>
    <div class="mt-6 space-y-4 text-[15px] leading-relaxed text-muted-ink">
        <?php foreach ($paragraphs as $para): ?>
            <?php if (trim($para) !== ''): ?><p><?= e($para) ?></p><?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php });
    return;
}

// Built-in static routes.
$which = (string)($_GET['which'] ?? 'about');

if ($which === 'contact') {
    $st = db()->prepare("SELECT setting_value FROM store_settings WHERE setting_key = :k LIMIT 1");
    $st->execute([':k' => 'store_email']);
    $email = (string)($st->fetchColumn() ?: 'hello@patchesandmotifs.test');
    store_layout('Contact us', function () use ($email) { ?>
<div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:py-16">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Contact us</h1>
    <div class="mt-6 space-y-4 text-[15px] leading-relaxed text-muted-ink">
        <p>Questions about an order, a custom patch run, or wholesale? We read every message.</p>
        <p>Email: <a class="font-semibold text-ink underline-offset-4 hover:underline" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
        <p>Hours: Monday&ndash;Saturday, 10:00&ndash;18:00 (PKT).</p>
    </div>
</div>
<?php });
    return;
}

store_layout('About us', function () { ?>
<div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:py-16">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">About Patches&amp;Motifs</h1>
    <div class="mt-6 space-y-4 text-[15px] leading-relaxed text-muted-ink">
        <p>Patches&amp;Motifs is a small studio making embroidered patches and motifs that finish a garment properly &mdash; from bridal sets and named labels to everyday accents.</p>
        <p>Every design is digitised in-house, stitched on quality backing, and checked by hand before it ships. Ready-to-ship pieces go out fast; made-to-order runs are scheduled with a clear lead time.</p>
        <p>Thank you for supporting craft that is made to be noticed.</p>
    </div>
</div>
<?php });
