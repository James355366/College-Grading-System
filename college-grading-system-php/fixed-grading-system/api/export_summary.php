<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/grading.php';
require_role('admin');

$pdo = get_db();
$classes = $pdo->query('SELECT c.*, t.name AS teacher_name FROM classes c LEFT JOIN teachers t ON t.id = c.teacher_id ORDER BY c.created_at DESC')->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="grading_system_summary_report.csv"');
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");
fputcsv($out, ['Class Code','Subject','Section','Teacher','School Year','Semester','Total Students','Passed','Failed','Pass Rate (%)']);

foreach ($classes as $c) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE class_id = ?');
    $stmt->execute([$c['id']]);
    $rows = $stmt->fetchAll();
    $passed = 0;
    foreach ($rows as $s) if (compute_grade($s, $c)['passed']) $passed++;
    $total = count($rows);
    $rate = $total ? round(($passed / $total) * 100) : 0;
    fputcsv($out, [$c['code'], $c['subject'], $c['section'], $c['teacher_name'] ?? '—', $c['school_year'], $c['semester'], $total, $passed, $total - $passed, $rate]);
}
fclose($out);
exit;
