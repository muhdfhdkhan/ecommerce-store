<?php
// includes/admin_guard.php — admin shell: auth gate, permission model, layout, flash.
// Every admin page starts with:  require_once admin_guard;  (SECURITY.md §11.2)
declare(strict_types=1);

require_once BASE_PATH . '/includes/bootstrap.php';

if (!admin_session_valid()) {
    safe_redirect('/admin/login');
}
admin_touch_session();

/** Permission map per role (PROJECT_BRIEF §19). super_admin = wildcard. */
function role_permissions_for(string $role): array
{
    if ($role === SUPER_ADMIN_ROLE) {
        return ['*'];
    }
    $map = role_permission_map();
    return $map[$role] ?? ['dashboard.view'];
}

/** Current admin with permissions resolved. */
$ADMIN = null;
if (!empty($_SESSION['admin_id'])) {
    $st = db()->prepare(
        'SELECT u.id, u.name, u.email, r.name AS role
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         WHERE u.id = :id AND u.status = :s AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $st->execute([':id' => (int)$_SESSION['admin_id'], ':s' => 'active']);
    if ($row = $st->fetch()) {
        $role = (string)($row['role'] ?? '');
        $row['permissions'] = role_permissions_for($role);
        $ADMIN = $row;
    }
}
if ($ADMIN === null) {
    logout_admin();
    safe_redirect('/admin/login');
}

/** Flash helpers with output escaping. */
function admin_flash(): void
{
    if (!empty($_SESSION['flash_success'])) {
        echo '<div role="status" class="mb-4 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">'
            . e((string)$_SESSION['flash_success']) . '</div>';
        unset($_SESSION['flash_success']);
    }
    if (!empty($_SESSION['flash_error'])) {
        echo '<div role="alert" class="mb-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">'
            . e((string)$_SESSION['flash_error']) . '</div>';
        unset($_SESSION['flash_error']);
    }
}

/**
 * Admin shell layout (DESIGN.md §11): fixed sidebar desktop, drawer mobile,
 * compact top bar with user menu and sign out.
 */
function admin_layout(string $title, callable $content): void
{
    /** @var array $ADMIN */
    global $ADMIN;
    $initial = strtoupper(mb_substr((string)$ADMIN['name'], 0, 1));
    $role = role_label((string)$ADMIN['role']);
    $canUsers = admin_user_can($ADMIN, 'users.manage');
    $canLogs = admin_user_can($ADMIN, 'logs.view');
    ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — Patches&amp;Motifs Admin</title>
<link rel="stylesheet" href="<?= asset_url('/assets/css/app.css') ?>">
<script type="module" src="<?= asset_url('/assets/js/admin.js') ?>"></script>
</head>
<body class="min-h-full bg-admin-canvas text-ink antialiased">
<header class="sticky top-0 z-30 border-b border-line bg-surface">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
        <button type="button" data-drawer-open aria-label="Open menu" aria-expanded="false"
                class="grid h-11 w-11 place-items-center rounded-xl border border-line lg:hidden">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <a href="/admin/dashboard" class="flex items-center gap-2.5">
            <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-[13px] font-bold text-white">P&amp;M</span>
            <span class="text-base font-bold tracking-tight">Patches&amp;Motifs</span>
        </a>
        <span class="ml-1 hidden rounded-full bg-line/70 px-2.5 py-1 text-[11px] font-semibold text-muted-ink sm:inline">Admin</span>
        <div class="ml-auto flex items-center gap-3">
            <span class="hidden text-right sm:block">
                <span class="block text-sm font-semibold"><?= e($ADMIN['name']) ?></span>
                <span class="block text-[11px] text-muted-ink"><?= e($role) ?></span>
            </span>
            <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-full bg-accent font-bold text-white"><?= e($initial) ?></span>
            <a href="/admin/profile" class="hidden h-11 items-center rounded-full border border-line px-4 text-sm font-semibold hover:bg-canvas sm:inline-flex">My account</a>
            <form method="post" action="/admin/logout">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="inline-flex h-11 items-center rounded-full border border-line px-4 text-sm font-semibold hover:bg-canvas">Sign out</button>
            </form>
        </div>
    </div>
</header>

<div class="mx-auto flex max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:py-8">
    <div data-drawer-backdrop class="fixed inset-0 z-40 hidden bg-ink/40 lg:hidden"></div>
    <aside data-drawer class="admin-sidebar fixed inset-y-0 left-0 z-50 w-72 -translate-x-full overflow-y-auto border-r border-line bg-surface p-4 transition-transform duration-200 lg:sticky lg:top-24 lg:h-[calc(100vh-8rem)] lg:w-60 lg:shrink-0 lg:translate-x-0 lg:rounded-2xl lg:border">
        <nav aria-label="Admin navigation" class="space-y-1">
            <a href="/admin/dashboard" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition hover:bg-canvas">Overview</a>
            <a href="/admin/analytics" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Analytics</a>
            <p class="px-3 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wide text-muted-ink">Catalog</p>
            <a href="/admin/products" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Products</a>
            <a href="/admin/categories" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Categories</a>
            <a href="/admin/collections" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Collections</a>
            <p class="px-3 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wide text-muted-ink">Operations</p>
            <a href="/admin/orders" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Orders</a>
            <a href="/admin/inventory" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Inventory</a>
            <a href="/admin/customers" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Customers</a>
            <p class="px-3 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wide text-muted-ink">Business</p>
            <a href="/admin/promotions" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Promotions</a>
            <a href="/admin/suppliers" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Suppliers</a>
            <a href="/admin/purchasing" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Purchasing</a>
            <a href="/admin/payments" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Payments</a>
            <a href="/admin/reports" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Reports</a>
            <p class="px-3 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wide text-muted-ink">Website</p>
            <a href="/admin/banners" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Banners</a>
            <a href="/admin/cms" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">CMS pages</a>
            <a href="/admin/seo" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">SEO</a>
            <a href="/admin/settings" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Settings</a>
            <?php if ($canUsers || $canLogs): ?>
            <p class="px-3 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wide text-muted-ink">Administration</p>
            <?php if ($canUsers): ?>
            <a href="/admin/users" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Users</a>
            <?php endif; ?>
            <?php if ($canLogs): ?>
            <a href="/admin/activity" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">Activity log</a>
            <?php endif; ?>
            <?php endif; ?>
            <a href="/admin/profile" class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition hover:bg-canvas">My account</a>
        </nav>
    </aside>

    <main class="min-w-0 flex-1">
        <?php $content(); ?>
    </main>
</div>
</body>
</html>
<?php
}

/** Product form partial (collapsible groups per MVP §2.3). */
function admin_product_form(?array $p, array $variants, array $cats, bool $canEdit): void
{
    $id = $p['id'] ?? null;
    ?>
<div class="flex items-center gap-3">
    <a href="/admin/products" class="inline-flex h-11 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">&larr; Back</a>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl"><?= $id ? 'Edit product' : 'New product' ?></h1>
</div>

<form method="post" action="/admin/products<?= $id ? '?action=edit&id=' . (int)$id : '?action=new' ?>" enctype="multipart/form-data" class="mt-6 max-w-3xl space-y-6">
    <input type="hidden" name="do" value="save">
    <?= csrf_field() ?>

    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6" data-collapsible>
        <h2 class="font-bold">General</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-semibold">Name</label>
                <input id="name" name="name" required maxlength="180" value="<?= e($p['name'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div>
                <label for="sku" class="block text-sm font-semibold">SKU</label>
                <input id="sku" name="sku" required maxlength="64" value="<?= e($p['sku'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 font-mono uppercase">
            </div>
            <div>
                <label for="category_id" class="block text-sm font-semibold">Category</label>
                <select id="category_id" name="category_id" <?= $canEdit ? '' : 'disabled' ?> class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-3">
                    <option value="">— none —</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)($p['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="short_description" class="block text-sm font-semibold">Short description</label>
                <input id="short_description" name="short_description" maxlength="400" value="<?= e($p['short_description'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            </div>
            <div class="sm:col-span-2">
                <label for="description" class="block text-sm font-semibold">Full description</label>
                <textarea id="description" name="description" rows="4" maxlength="20000" <?= $canEdit ? '' : 'disabled' ?>
                          class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-4 py-3"><?= e($p['description'] ?? '') ?></textarea>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <h2 class="font-bold">Pricing &amp; status</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="price" class="block text-sm font-semibold">Regular price (PKR)</label>
                <input id="price" name="price" required inputmode="decimal" value="<?= e($p['base_price'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
            </div>
            <div>
                <label for="sale_price" class="block text-sm font-semibold">Sale price <span class="font-normal text-muted-ink">(optional)</span></label>
                <input id="sale_price" name="sale_price" inputmode="decimal" value="<?= e($p['sale_price'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                       class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4 tabular-nums">
            </div>
            <div>
                <label for="status" class="block text-sm font-semibold">Status</label>
                <select id="status" name="status" <?= $canEdit ? '' : 'disabled' ?> class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-3">
                    <?php foreach (['draft', 'active', 'archived'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($p['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-3 pt-2">
                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_featured" value="1" <?= !empty($p['is_featured']) ? 'checked' : '' ?> class="h-4 w-4 accent-[#30351B]" <?= $canEdit ? '' : 'disabled' ?>> Featured on homepage</label>
                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="made_to_order" value="1" id="mto-check" <?= !empty($p['made_to_order']) ? 'checked' : '' ?> class="h-4 w-4 accent-[#30351B]" <?= $canEdit ? '' : 'disabled' ?>> Made to order</label>
                <div id="lead-time-wrap" class="<?= !empty($p['made_to_order']) ? '' : 'hidden' ?>">
                    <label for="lead_time_days" class="block text-xs font-semibold text-muted-ink">Lead time (days)</label>
                    <input id="lead_time_days" name="lead_time_days" type="number" min="1" max="365" value="<?= e($p['lead_time_days'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>
                           class="mt-1 h-10 w-28 rounded-xl border border-line bg-canvas/60 px-3 tabular-nums">
                </div>
            </div>
        </div>
    </section>

    <?php if (!$id): ?>
    <!-- Create-only: upload images + first variant straight from the Add form. -->
    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <h2 class="font-bold">Images</h2>
        <p class="mt-1 text-xs text-muted-ink">Upload product photos now (JPG/PNG/WebP, up to 5 MB each, max 8). The first becomes the primary; you can manage more after saving.</p>
        <div class="mt-3 flex flex-wrap items-end gap-3">
            <label class="block text-sm">Choose files (multiple allowed)
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-image-input
                       class="mt-1 block w-full max-w-xs rounded-xl border border-line bg-surface px-3 py-2 text-sm">
            </label>
            <label class="block text-sm">Alt text (accessibility)
                <input name="alt_text" maxlength="255" class="mt-1 block w-56 rounded-xl border border-line bg-surface px-3 py-2 text-sm">
            </label>
        </div>
        <div data-image-preview class="mt-3 flex flex-wrap gap-2" aria-live="polite"></div>
    </section>

    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <h2 class="font-bold">First variant <span class="font-normal text-muted-ink">— size / length / color for patches &amp; motifs</span></h2>
        <p class="mt-1 text-xs text-muted-ink">Optional. If left blank, a variant is created from the product SKU with the regular price and zero stock. Add more variants after saving.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-5">
            <input name="variant_sku" placeholder="SKU (blank = product SKU)" maxlength="64"
                   class="h-11 rounded-xl border border-line bg-surface px-3 font-mono text-sm uppercase sm:col-span-2">
            <select name="variant_size" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm" aria-label="Size">
                <option value="">Size…</option>
                <optgroup label="Patch / motif size (inches)">
                    <?php foreach (['1"', '1.5"', '2"', '3"', '4"', '5"', '6"', '8"', '10"'] as $s): ?>
                        <option value="<?= e($s) ?>"><?= e($s) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="General">
                    <?php foreach (['Small', 'Medium', 'Large', 'One size'] as $s): ?>
                        <option value="<?= e($s) ?>"><?= e($s) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
            <input name="variant_length" placeholder="Length (e.g. Border 12&quot;)" maxlength="32" list="length-presets-new"
                   class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
            <datalist id="length-presets-new">
                <?php foreach (['Border 12"', 'Border 9"', 'Border 6"', 'Full length', 'Custom'] as $l): ?>
                    <option value="<?= e($l) ?>">
                <?php endforeach; ?>
            </datalist>
            <input name="variant_color" placeholder="Color" maxlength="32" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
        </div>
        <div class="mt-3">
            <label class="block text-xs font-semibold text-muted-ink">Variant price (PKR, blank = regular price)</label>
            <input name="variant_price" inputmode="decimal" class="mt-1 h-11 w-40 rounded-xl border border-line bg-surface px-3 text-sm tabular-nums">
        </div>
    </section>
    <?php endif; ?>

    <?php if ($id): ?>
    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6" id="images-section">
        <h2 class="font-bold">Images</h2>
        <?php $imgs = product_images_all((int)$id); ?>
        <?php if ($imgs): ?>
        <div class="mt-4 flex flex-wrap gap-4">
            <?php foreach ($imgs as $im): ?>
            <div class="w-36">
                <div class="aspect-square overflow-hidden rounded-2xl border border-line bg-stone-soft">
                    <img src="/uploads/<?= e($im['file_path']) ?>" alt="<?= e($im['alt_text'] ?: $p['name'] ?? 'Product image') ?>" loading="lazy" class="h-full w-full object-cover">
                </div>
                <?php if ($canEdit): ?>
                <div class="mt-2 flex items-center gap-2">
                    <?php if ($im['is_primary']): ?>
                        <span class="rounded-full bg-accent-soft px-2 py-0.5 text-[11px] font-semibold text-accent">Primary</span>
                    <?php else: ?>
                        <form method="post" action="/admin/products?action=edit&id=<?= (int)$id ?>">
                            <input type="hidden" name="do" value="primary_image">
                            <input type="hidden" name="image_id" value="<?= (int)$im['id'] ?>">
                            <?= csrf_field() ?>
                            <button class="text-xs font-semibold underline-offset-2 hover:underline">Make primary</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/products?action=edit&id=<?= (int)$id ?>" class="ml-auto">
                        <input type="hidden" name="do" value="delete_image">
                        <input type="hidden" name="image_id" value="<?= (int)$im['id'] ?>">
                        <?= csrf_field() ?>
                        <button class="text-xs font-semibold text-danger hover:underline">Remove</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="mt-3 text-sm text-muted-ink">No images yet.</p>
        <?php endif; ?>
        <?php if ($canEdit): ?>
        <form method="post" action="/admin/products?action=edit&id=<?= (int)$id ?>" enctype="multipart/form-data" class="mt-5 rounded-xl border border-line bg-canvas/40 p-4">
            <input type="hidden" name="do" value="upload_images">
            <?= csrf_field() ?>
            <p class="text-sm font-semibold">Add images</p>
            <p class="text-xs text-muted-ink">JPG, PNG or WebP, up to 5 MB each, max 8 per upload. Images are re-processed server-side.</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <label class="block text-sm">Choose files (multiple allowed)
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required data-image-input
                           class="mt-1 block w-full max-w-xs rounded-xl border border-line bg-surface px-3 py-2 text-sm">
                </label>
                <label class="block text-sm">Alt text (accessibility)
                    <input name="alt_text" maxlength="255" placeholder="<?= e($p['name'] ?? '') ?> patch"
                           class="mt-1 block w-56 rounded-xl border border-line bg-surface px-3 py-2 text-sm">
                </label>
                <button class="inline-flex h-11 items-center rounded-xl bg-accent px-5 text-sm font-semibold text-white hover:bg-accent/90">Upload</button>
            </div>
            <div data-image-preview class="mt-3 flex flex-wrap gap-2" aria-live="polite"></div>
        </form>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <h2 class="font-bold">Variants</h2>
        <ul class="mt-4 space-y-3">
            <?php foreach ($variants as $v): ?>
            <li class="flex flex-wrap items-center gap-3 rounded-xl border border-line p-3">
                <span class="font-mono text-sm font-semibold"><?= e($v['sku']) ?></span>
                <span class="text-sm text-muted-ink"><?= e(variant_label((string)$v['sku'], $v['size_label'] ?? null, $v['length_label'] ?? null, $v['color_label'] ?? null)) ?></span>
                <span class="ml-auto text-sm font-semibold tabular-nums"><?= money_fmt(money_to_cents((string)$v['price'])) ?></span>
                <span class="rounded-full px-3 py-1 text-xs font-semibold tabular-nums <?= $canEdit && (int)$v['available'] <= (5) ? 'bg-warning/10 text-warning' : 'bg-canvas text-muted-ink' ?>">
                    <?= (int)$v['available'] ?> in stock
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($canEdit): ?>
        <div class="mt-4 rounded-xl border border-line bg-canvas/40 p-4">
            <p class="text-sm font-semibold">Add variant <span class="font-normal text-muted-ink">— size / length / color for patches &amp; motifs</span></p>
            <div class="mt-3 grid gap-3 sm:grid-cols-5">
                <input name="variant_sku" placeholder="SKU" required maxlength="64" class="h-11 rounded-xl border border-line bg-surface px-3 font-mono text-sm uppercase sm:col-span-2">
                <select name="size_label" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm" aria-label="Size">
                    <option value="">Size…</option>
                    <optgroup label="Patch / motif size (inches)">
                        <?php foreach (['1"', '1.5"', '2"', '3"', '4"', '5"', '6"', '8"', '10"'] as $s): ?>
                            <option value="<?= e($s) ?>"><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="General">
                        <?php foreach (['Small', 'Medium', 'Large', 'One size'] as $s): ?>
                            <option value="<?= e($s) ?>"><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <input name="length_label" placeholder="Length (e.g. 12 in border)" maxlength="32" list="length-presets"
                       class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                <datalist id="length-presets">
                    <?php foreach (['Border 12"', 'Border 9"', 'Border 6"', 'Full length', 'Custom'] as $l): ?>
                        <option value="<?= e($l) ?>">
                    <?php endforeach; ?>
                </datalist>
                <input name="color_label" placeholder="Color" maxlength="32" class="h-11 rounded-xl border border-line bg-surface px-3 text-sm">
            </div>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold text-muted-ink">Price (PKR)</label>
                    <input name="variant_price" placeholder="Price" required inputmode="decimal" class="h-11 w-32 rounded-xl border border-line bg-surface px-3 text-sm tabular-nums">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-muted-ink">Initial stock</label>
                    <input name="variant_stock" type="number" min="0" max="1000000" value="0" class="h-11 w-28 rounded-xl border border-line bg-surface px-3 text-sm tabular-nums">
                </div>
                <button type="submit" name="do" value="add_variant" class="inline-flex h-11 items-center rounded-xl bg-accent px-5 text-sm font-semibold text-white hover:bg-accent/90">Add variant</button>
            </div>
            <p class="mt-2 text-xs text-muted-ink">Leave size/length/color empty when not applicable — the storefront shows what you set.</p>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" <?= $canEdit ? '' : 'disabled' ?> class="inline-flex h-12 items-center rounded-full bg-accent px-7 font-semibold text-white hover:bg-accent/90 disabled:opacity-40">Save product</button>
        <?php if ($id && admin_user_can($GLOBALS['ADMIN'], 'products.delete')): ?>
        <button type="submit" form="delete-product" class="inline-flex h-12 items-center rounded-full border border-danger/40 px-6 font-semibold text-danger hover:bg-danger/10">Archive</button>
        <?php endif; ?>
    </div>
</form>

<?php if ($id): ?>
<form id="delete-product" method="post" action="/admin/products?action=edit&id=<?= (int)$id ?>">
    <input type="hidden" name="do" value="delete">
    <?= csrf_field() ?>
</form>
<?php endif; ?>
    <?php
}
