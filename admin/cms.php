<?php
// admin/cms.php — CMS pages CRUD (Phase 7). Content is stored plain text and
// escaped per-paragraph on render — no raw HTML from DB (SECURITY.md §6.2).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    admin_require_permission($ADMIN, 'cms.manage');

    $do = (string)($_POST['do'] ?? '');

    if ($do === 'create' || $do === 'update') {
        $title = trim(mb_substr((string)($_POST['title'] ?? ''), 0, 160));
        $content = mb_substr((string)($_POST['content'] ?? ''), 0, 50000);
        $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
        $metaTitle = mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 160) ?: null;
        $metaDesc = mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 300) ?: null;

        if ($do === 'create') {
            $slug = slugify($title);
            $dupe = db()->prepare('SELECT id FROM pages WHERE slug = :s');
            $dupe->execute([':s' => $slug]);
            if ($dupe->fetch()) { $slug .= '-' . bin2hex(random_bytes(2)); }
            if ($title === '') {
                $_SESSION['flash_error'] = 'Title is required.';
            } else {
                db()->prepare(
                    'INSERT INTO pages (title, slug, content, status, meta_title, meta_description)
                     VALUES (:t, :s, :c, :st, :mt, :md)'
                )->execute([':t' => $title, ':s' => $slug, ':c' => $content, ':st' => $status, ':mt' => $metaTitle, ':md' => $metaDesc]);
                audit('page_created', 'page', (int)db()->lastInsertId(), null, ['title' => $title]);
                $_SESSION['flash_success'] = 'Page created at /page/' . $slug;
            }
        } else {
            $pid = int_in($_POST['page_id'] ?? null);
            if ($pid && $title !== '') {
                db()->prepare(
                    'UPDATE pages SET title = :t, content = :c, status = :st, meta_title = :mt, meta_description = :md WHERE id = :id'
                )->execute([':t' => $title, ':c' => $content, ':st' => $status, ':mt' => $metaTitle, ':md' => $metaDesc, ':id' => $pid]);
                audit('page_updated', 'page', $pid);
                $_SESSION['flash_success'] = 'Page updated.';
            }
        }
    }

    if ($do === 'toggle' || $do === 'delete') {
        $pid = int_in($_POST['page_id'] ?? null);
        if ($pid) {
            if ($do === 'toggle') {
                db()->prepare("UPDATE pages SET status = IF(status = 'published', 'draft', 'published') WHERE id = :id")->execute([':id' => $pid]);
                $_SESSION['flash_success'] = 'Page status updated.';
            } else {
                db()->prepare('DELETE FROM pages WHERE id = :id')->execute([':id' => $pid]);
                $_SESSION['flash_success'] = 'Page deleted.';
            }
            audit('page_' . $do, 'page', $pid);
        }
    }
    safe_redirect('/admin/cms');
}

admin_layout('CMS pages', function () use ($ADMIN) {
    $canManage = admin_user_can($ADMIN, 'cms.manage');
    $pages = db()->query('SELECT id, title, slug, status, updated_at FROM pages ORDER BY id DESC LIMIT 100')->fetchAll();

    admin_flash();
    ?>
    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">CMS pages</h1>
    <p class="mt-1 text-sm text-muted-ink">Published pages appear at <code class="font-mono">/page/{slug}</code>. Content renders as plain-text paragraphs.</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
        <div class="overflow-hidden rounded-2xl border border-line bg-surface">
            <div class="divide-y divide-line">
                <?php foreach ($pages as $p): ?>
                <div class="flex flex-wrap items-center gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold"><?= e($p['title']) ?></p>
                        <p class="text-xs text-muted-ink">/page/<?= e($p['slug']) ?> · updated <?= e($p['updated_at']) ?></p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $p['status'] === 'published' ? 'bg-success/10 text-success' : 'bg-line text-muted-ink' ?>"><?= e($p['status']) ?></span>
                    <a href="/page/<?= e($p['slug']) ?>" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">View</a>
                    <?php if ($canManage): ?>
                    <a href="/admin/cms?edit=<?= (int)$p['id'] ?>" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">Edit</a>
                    <form method="post" data-confirm>
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="delete">
                        <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                        <button class="inline-flex h-10 items-center rounded-xl border border-danger/40 px-4 text-sm font-semibold text-danger hover:bg-danger/10">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (!$pages): ?><p class="p-8 text-center text-sm text-muted-ink">No pages yet.</p><?php endif; ?>
            </div>
        </div>

        <?php if ($canManage): ?>
        <?php
        $editId = int_in($_GET['edit'] ?? null);
        $editing = null;
        if ($editId) {
            $st = db()->prepare('SELECT id, title, slug, content, status, meta_title, meta_description FROM pages WHERE id = :id');
            $st->execute([':id' => $editId]);
            $editing = $st->fetch() ?: null;
        }
        ?>
        <form method="post" class="rounded-2xl border border-line bg-surface p-5">
            <input type="hidden" name="do" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?><input type="hidden" name="page_id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
            <?= csrf_field() ?>
            <h2 class="font-bold"><?= $editing ? 'Edit page' : 'New page' ?></h2>
            <label for="ptitle" class="mt-3 block text-sm font-semibold">Title</label>
            <input id="ptitle" name="title" required maxlength="160" value="<?= e($editing['title'] ?? '') ?>" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            <label for="pcontent" class="mt-3 block text-sm font-semibold">Content <span class="font-normal text-muted-ink">(blank line = new paragraph)</span></label>
            <textarea id="pcontent" name="content" rows="10" class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-4 py-3 text-sm"><?= e($editing['content'] ?? '') ?></textarea>
            <label for="pstatus" class="mt-3 block text-sm font-semibold">Status</label>
            <select id="pstatus" name="status" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-3">
                <option value="draft" <?= ($editing['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
            <label for="pmt" class="mt-3 block text-sm font-semibold">SEO meta title</label>
            <input id="pmt" name="meta_title" maxlength="160" value="<?= e($editing['meta_title'] ?? '') ?>" class="mt-1.5 h-12 w-full rounded-xl border border-line bg-canvas/60 px-4">
            <label for="pmd" class="mt-3 block text-sm font-semibold">SEO meta description</label>
            <textarea id="pmd" name="meta_description" rows="2" maxlength="300" class="mt-1.5 w-full rounded-xl border border-line bg-canvas/60 px-4 py-2 text-sm"><?= e($editing['meta_description'] ?? '') ?></textarea>
            <button class="mt-4 h-11 w-full rounded-xl bg-accent font-semibold text-white hover:bg-accent/90"><?= $editing ? 'Save changes' : 'Create page' ?></button>
        </form>
        <?php endif; ?>
    </div>
<?php });
