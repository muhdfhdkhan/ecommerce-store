<?php
// admin/activity.php — audit trail: admin actions, admin sign-ins and login-screen visits.
// SECURITY.md §29 (audit trail), §9.2 (login telemetry), §11.2 (RBAC).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';

admin_require_permission($ADMIN, 'logs.view');

const ACTIVITY_PAGE_SIZE = 40;

/** Reformat an audit JSON blob for display (never re-encoded into HTML). */
function activity_json_pretty(?string $json): string
{
    if ($json === null || $json === '') {
        return '';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return (string)$json;
    }
    return (string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** Human wording for an action key. */
function activity_label(string $action): string
{
    $special = [
        'po_created' => 'Purchase order created',
        'po_order'   => 'Purchase order marked ordered',
        'po_receive' => 'Purchase order received',
        'cms_saved'  => 'CMS page saved',
        'seo_saved'  => 'SEO settings saved',
    ];
    if (isset($special[$action])) {
        return $special[$action];
    }
    return ucfirst(str_replace('_', ' ', $action));
}

admin_layout('Activity log', function () use ($ADMIN) {
    $tab = (string)($_GET['tab'] ?? 'activity');
    if (!in_array($tab, ['activity', 'logins'], true)) {
        $tab = 'activity';
    }

    $q      = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
    $userId = int_in($_GET['user'] ?? null);
    $from   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? (string)$_GET['from'] : '';
    $to     = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? (string)$_GET['to'] : '';
    $page   = max(1, int_in($_GET['page'] ?? 1, 1, 5000) ?? 1);
    $offset = ($page - 1) * ACTIVITY_PAGE_SIZE;

    $admins = db()->query(
        'SELECT id, name, email FROM users WHERE deleted_at IS NULL ORDER BY name'
    )->fetchAll();

    // ---------------- Activity feed ----------------
    if ($tab === 'activity') {
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(a.action LIKE :q OR a.entity LIKE :q OR a.ip LIKE :q OR u.name LIKE :q OR u.email LIKE :q)';
            $params[':q'] = $like;
        }
        if ($userId !== null) {
            $where[] = 'a.user_id = :uid';
            $params[':uid'] = $userId;
        }
        if ($from !== '') {
            $where[] = 'a.created_at >= :from';
            $params[':from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = 'a.created_at <= :to';
            $params[':to'] = $to . ' 23:59:59';
        }
        $whereSql = implode(' AND ', $where);

        $count = db()->prepare("SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $whereSql");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $st = db()->prepare(
            "SELECT a.id, a.user_id, a.action, a.entity, a.entity_id, a.before_json, a.after_json,
                    a.ip, a.user_agent, a.created_at, u.name AS user_name, u.email AS user_email
             FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE $whereSql
             ORDER BY a.id DESC
             LIMIT " . ACTIVITY_PAGE_SIZE . " OFFSET $offset"
        );
        $st->execute($params);
        $rows = $st->fetchAll();

        $today = (int)db()->query(
            'SELECT COUNT(*) FROM activity_logs WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY'
        )->fetchColumn();
        $logins = (int)db()->query(
            "SELECT COUNT(*) FROM login_attempts
             WHERE scope = 'admin_login' AND success = 1 AND attempted_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY"
        )->fetchColumn();
    } else {
        $where = ["la.scope = 'admin_login'"];
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(la.identifier LIKE :q OR la.ip LIKE :q)';
            $params[':q'] = $like;
        }
        if ($from !== '') {
            $where[] = 'la.attempted_at >= :from';
            $params[':from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = 'la.attempted_at <= :to';
            $params[':to'] = $to . ' 23:59:59';
        }
        $whereSql = implode(' AND ', $where);

        $count = db()->prepare("SELECT COUNT(*) FROM login_attempts la WHERE $whereSql");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $st = db()->prepare(
            "SELECT la.id, la.identifier, la.ip, la.success, la.attempted_at
             FROM login_attempts la
             WHERE $whereSql
             ORDER BY la.id DESC
             LIMIT " . ACTIVITY_PAGE_SIZE . " OFFSET $offset"
        );
        $st->execute($params);
        $rows = $st->fetchAll();

        $today = (int)db()->query(
            "SELECT COUNT(*) FROM login_attempts WHERE scope = 'admin_login' AND attempted_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY"
        )->fetchColumn();
        $logins = (int)db()->query(
            "SELECT COUNT(*) FROM login_attempts
             WHERE scope = 'admin_login' AND success = 1 AND attempted_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY"
        )->fetchColumn();
    }

    $pages = max(1, (int)ceil($total / ACTIVITY_PAGE_SIZE));
    $queryBase = static function (array $overrides) use ($tab, $q, $userId, $from, $to): string {
        $args = array_merge([
            'tab' => $tab,
            'q' => $q,
            'user' => $userId,
            'from' => $from,
            'to' => $to,
        ], $overrides);
        $args = array_filter($args, static fn($v) => $v !== '' && $v !== null);
        return '/admin/activity?' . http_build_query($args);
    };

    admin_flash();
    ?>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Activity log</h1>
            <p class="mt-1 text-sm text-muted-ink">Everything that happens inside the admin panel, plus sign-in attempts and login-screen visits.</p>
        </div>
        <div class="flex gap-4 text-xs text-muted-ink tabular-nums">
            <span><strong class="block text-base font-bold text-ink"><?= $today ?></strong>last 24h</span>
            <span><strong class="block text-base font-bold text-ink"><?= $logins ?></strong>sign-ins / 7d</span>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="Log type">
        <a href="<?= e($queryBase(['tab' => 'activity', 'page' => null])) ?>" role="tab" aria-selected="<?= $tab === 'activity' ? 'true' : 'false' ?>"
           class="inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold <?= $tab === 'activity' ? 'bg-ink text-white' : 'border border-line hover:bg-canvas' ?>">Admin actions</a>
        <a href="<?= e($queryBase(['tab' => 'logins', 'page' => null])) ?>" role="tab" aria-selected="<?= $tab === 'logins' ? 'true' : 'false' ?>"
           class="inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold <?= $tab === 'logins' ? 'bg-ink text-white' : 'border border-line hover:bg-canvas' ?>">Sign-in attempts</a>
    </div>

    <form method="get" class="mt-4 rounded-2xl border border-line bg-surface p-4">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="grid gap-3 <?= $tab === 'activity' ? 'sm:grid-cols-2 lg:grid-cols-5' : 'sm:grid-cols-3' ?>">
            <div class="lg:col-span-2">
                <label for="q" class="block text-xs font-semibold text-muted-ink">Search</label>
                <input id="q" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="<?= $tab === 'activity' ? 'Action, entity, admin or IP' : 'Email or IP' ?>"
                       class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
            </div>
            <?php if ($tab === 'activity'): ?>
            <div>
                <label for="user" class="block text-xs font-semibold text-muted-ink">Admin</label>
                <select id="user" name="user" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
                    <option value="">Anyone</option>
                    <?php foreach ($admins as $a): ?>
                    <option value="<?= (int)$a['id'] ?>" <?= $userId === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label for="from" class="block text-xs font-semibold text-muted-ink">From</label>
                <input id="from" name="from" type="date" value="<?= e($from) ?>" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
            </div>
            <div>
                <label for="to" class="block text-xs font-semibold text-muted-ink">To</label>
                <input id="to" name="to" type="date" value="<?= e($to) ?>" class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/60 px-3">
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-ink px-5 text-sm font-semibold text-white hover:bg-ink/90">Filter</button>
            <a href="/admin/activity?tab=<?= e($tab) ?>" class="inline-flex h-10 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">Reset</a>
            <span class="ml-auto text-xs text-muted-ink tabular-nums"><?= $total ?> result<?= $total === 1 ? '' : 's' ?></span>
        </div>
    </form>

    <div class="mt-4 overflow-hidden rounded-2xl border border-line bg-surface">
        <?php if ($tab === 'activity'): ?>
        <ul class="divide-y divide-line">
            <?php foreach ($rows as $r):
                $before = activity_json_pretty($r['before_json'] ?? null);
                $after  = activity_json_pretty($r['after_json'] ?? null);
            ?>
            <li class="p-4">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="font-semibold"><?= e(activity_label((string)$r['action'])) ?></span>
                    <?php if (!empty($r['entity'])): ?>
                    <span class="rounded-full bg-canvas px-2 py-0.5 text-[11px] font-semibold text-muted-ink"><?= e((string)$r['entity']) ?><?= $r['entity_id'] !== null ? ' #' . (int)$r['entity_id'] : '' ?></span>
                    <?php endif; ?>
                    <span class="ml-auto text-xs text-muted-ink tabular-nums"><?= e((string)$r['created_at']) ?> UTC</span>
                </div>
                <p class="mt-1 text-xs text-muted-ink">
                    <?php if ($r['user_name'] !== null): ?>
                        <?= e((string)$r['user_name']) ?> &middot; <?= e((string)$r['user_email']) ?>
                    <?php else: ?>
                        Guest / system
                    <?php endif; ?>
                    <?php if (!empty($r['ip'])): ?> &middot; <span class="font-mono"><?= e((string)$r['ip']) ?></span><?php endif; ?>
                </p>
                <?php if ($before !== '' || $after !== ''): ?>
                <details class="mt-2">
                    <summary class="cursor-pointer text-xs font-semibold text-muted-ink hover:text-ink">Show changes</summary>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <?php if ($before !== ''): ?>
                        <pre class="max-h-56 overflow-auto rounded-xl bg-canvas p-3 text-[11px] leading-relaxed text-muted-ink"><?= e($before) ?></pre>
                        <?php endif; ?>
                        <?php if ($after !== ''): ?>
                        <pre class="max-h-56 overflow-auto rounded-xl bg-canvas p-3 text-[11px] leading-relaxed text-muted-ink"><?= e($after) ?></pre>
                        <?php endif; ?>
                    </div>
                </details>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
            <li class="p-10 text-center text-sm text-muted-ink">No activity matches these filters.</li>
            <?php endif; ?>
        </ul>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted-ink">
                <tr>
                    <th class="px-4 py-3">When (UTC)</th>
                    <th class="px-4 py-3">Identifier</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">Result</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="px-4 py-3 text-xs tabular-nums text-muted-ink"><?= e((string)$r['attempted_at']) ?></td>
                    <td class="px-4 py-3 font-mono text-xs"><?= e((string)$r['identifier']) ?></td>
                    <td class="px-4 py-3 font-mono text-xs text-muted-ink"><?= e((string)($r['ip'] ?? '—')) ?></td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $r['success'] ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' ?>">
                            <?= $r['success'] ? 'Success' : 'Failed' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                <tr><td colspan="4" class="px-4 py-10 text-center text-muted-ink">No sign-in attempts match these filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <nav aria-label="Pagination" class="mt-4 flex items-center justify-between text-sm">
        <?php if ($page > 1): ?>
        <a href="<?= e($queryBase(['page' => $page - 1])) ?>" class="inline-flex h-10 items-center rounded-xl border border-line px-4 font-semibold hover:bg-canvas">&larr; Newer</a>
        <?php else: ?><span></span><?php endif; ?>
        <span class="text-xs text-muted-ink tabular-nums">Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?>
        <a href="<?= e($queryBase(['page' => $page + 1])) ?>" class="inline-flex h-10 items-center rounded-xl border border-line px-4 font-semibold hover:bg-canvas">Older &rarr;</a>
        <?php else: ?><span></span><?php endif; ?>
    </nav>
    <?php endif; ?>
<?php });
