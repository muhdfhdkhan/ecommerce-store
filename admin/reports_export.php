<?php
// admin/reports_export.php - CSV download for every report type (reports.export permission).
declare(strict_types=1);

require_once BASE_PATH . '/includes/admin_guard.php';
require_once BASE_PATH . '/modules/reports/service.php';

admin_require_permission($ADMIN, 'reports.export');

$from = (string)($_GET['from'] ?? gmdate('Y-m-01'));
$to = (string)($_GET['to'] ?? gmdate('Y-m-d'));
if (DateTimeImmutable::createFromFormat('Y-m-d', $from) === false
    || DateTimeImmutable::createFromFormat('Y-m-d', $to) === false) {
    $from = gmdate('Y-m-01');
    $to = gmdate('Y-m-d');
}
$type = array_key_exists(($_GET['type'] ?? ''), report_types()) ? (string)$_GET['type'] : 'sales';
$report = report_fetch($type, $from, $to);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_' . $type . '_' . $from . '_to_' . $to . '.csv"');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM so Excel opens it correctly
fputcsv($out, array_map(static fn($c) => $c[0], $report['columns']));
foreach ($report['rows'] as $r) {
    $line = [];
    foreach ($report['columns'] as [, $key]) {
        $line[] = isset($r[$key]) ? (string)$r[$key] : '';
    }
    fputcsv($out, $line);
}
fclose($out);
exit;
