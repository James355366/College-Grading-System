<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/grading.php';
require_once __DIR__ . '/../includes/csv.php';
require_once __DIR__ . '/../includes/roster.php';
require_role('admin');

$pdo = get_db();
$classes = $pdo->query('SELECT c.*, t.name AS teacher_name FROM classes c LEFT JOIN teachers t ON t.id = c.teacher_id ORDER BY c.created_at DESC')->fetchAll();

$summaries = class_statistics($pdo, array_column($classes, 'id'));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="grading_system_summary_report.csv"');
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");
write_csv_row($out, ['Class Code','Subject','Section','Teacher','School Year','Semester','Total Students','Passed','Failed','Incomplete','Pass Rate (%)']);

foreach ($classes as $c) {
    $summary = $summaries[$c['id']];
    $total = $summary['total']; $passed = $summary['passed']; $rate = $summary['passRate'];
    write_csv_row($out, [$c['code'], $c['subject'], $c['section'], $c['teacher_name'] ?? '—', $c['school_year'], $c['semester'], $total, $passed, $summary['failed'], $summary['incomplete'], $rate]);
}
fclose($out);
exit;
