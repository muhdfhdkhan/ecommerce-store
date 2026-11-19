<?php
// admin/reports.php — all report types with tabs + CSV export links (Phase 6/8).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';
require_once BASE_PATH . '/modules/reports/service.php';

admin_require_permission($ADMIN, 'reports.view');

$from = (string)($_GET['from'] ?? gmdate('Y-m-01'));
$to = (string)($_GET['to'] ?? gmdate('Y-m-d'));
if (DateTimeImmutable::createFromFormat('Y-m-d', $from) === false
    || DateTimeImmutable::createFromFormat('Y-m-d', $to) === false) {
    $from = gmdate('Y-m-01');
    $to = gmdate('Y-m-d');
}
$type = array_key_exists(($_GET['type'] ?? ''), report_types()) ? (string)$_GET['type'] : 'sales';
$report = report_fetch($type, $from, $to);

admin_layout('Reports', function () use ($from, $to, $type, $report) {
    admin_flash();
    ?>
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Reports</h1>
        <a href="/admin/reports/export?type=<?= e($type) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"
           class="ml-auto inline-flex h-11 items-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-canvas">Download CSV</a>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <?php foreach (report_types() as $t => $label): ?>
        <a href="/admin/reports?type=<?= $t ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"
           class="inline-flex h-11 items-center rounded-xl px-4 text-sm font-semibold <?= $type === $t ? 'bg-ink text-white' : 'border border-line hover:bg-canvas' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="/admin/reports" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div><label for="from" class="block text-xs font-semibold text-muted-ink">From</label>
            <input id="from" name="from" type="date" value="<?= e($from) ?>" class="mt-1 h-11 rounded-xl border border-line bg-surface px-3 text-sm"></div>
        <div><label for="to" class="block text-xs font-semibold text-muted-ink">To</label>
            <input id="to" name="to" type="date" value="<?= e($to) ?>" class="mt-1 h-11 rounded-xl border border-line bg-surface px-3 text-sm"></div>
        <button class="h-11 rounded-xl bg-ink px-5 text-sm font-semibold text-white">Apply</button>
        <p class="pb-1 text-xs text-muted-ink"><?= e($report['note']) ?></p>
    </form>

    <section class="mt-6 overflow-hidden rounded-2xl border border-line bg-surface">
        <div class="border-b border-line px-5 py-3"><h2 class="font-bold"><?= e($report['title']) ?></h2></div>
        <?php if (!$report['rows']): ?>
            <p class="p-8 text-center text-sm text-muted-ink">No data for this range.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs text-muted-ink">
                <?php foreach ($report['columns'] as [$label]): ?>
                    <th class="px-5 py-3"><?= e($label) ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody class="divide-y divide-line">
                <?php foreach ($report['rows'] as $r): ?>
                <tr>
                    <?php foreach ($report['columns'] as $col):
                        $label = (string)($col[0] ?? '');
                        $key   = (string)($col[1] ?? '');
                        $money = (bool)($col[2] ?? false);
                    ?>
                    <td class="px-5 py-2.5 <?= $money ? 'text-right font-semibold tabular-nums' : '' ?>">
                        <?= $money ? money_fmt(money_to_cents((string)($r[$key] ?? 0))) : e((string)($r[$key] ?? '')) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
<?php });
