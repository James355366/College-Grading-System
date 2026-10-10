<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/grading.php';
require_once __DIR__ . '/../includes/csv.php';
require_once __DIR__ . '/../includes/roster.php';
$u = require_role('teacher');

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'csv';

$stmt = $pdo->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
$stmt->execute([$id, $u['id']]);
$cls = $stmt->fetch();
if (!$cls) { http_response_code(404); die('Class not found.'); }

$stmt = $pdo->prepare('SELECT * FROM students WHERE class_id = ? ORDER BY id ASC');
$stmt->execute([$id]);
$students = $stmt->fetchAll();

$head = ['#','Student ID','Student Name','Quiz','Oral','Attendance','Class Standing','Prelim','Final',
         'Major Exams','Lecture Grade','Performance Task','Activity Output','Lab Grade','Final %','GPA','Remarks'];

$rows = [];
$i = 1;
foreach ($students as $s) {
    $g = compute_grade($s, $cls);
    $rows[] = [
        $i++, $s['student_id'], $s['name'], $s['quiz'], $s['oral'], $s['attendance'],
        $g['classStanding'], $s['prelim'], $s['final_exam'], $g['majorExams'], $g['lectureGrade'],
        $g['performanceTask'], $g['activityOutput'], $g['labGrade'], $g['finalPercent'],
        ($g['gpa'] === null ? '' : number_format($g['gpa'], 2)), strtoupper($g['status']),
    ];
}

$filenameBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $cls['code']) . '_gradesheet';

if ($format === 'pdf') {
    // Lightweight dependency-free "PDF": a printable HTML page the browser can Print > Save as PDF.
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html><html><head><meta charset="utf-8"><title><?= h($filenameBase) ?></title>
    <style>
      body{font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#111;margin:24px;}
      h1{font-size:16px;margin:0 0 4px;} p.meta{font-size:11px;color:#444;margin:0 0 16px;}
      table{border-collapse:collapse;width:100%;} th,td{border:1px solid #999;padding:4px 6px;text-align:center;}
      th{background:#334155;color:#fff;} td:nth-child(3){text-align:left;}
      .pass{color:#166534;font-weight:bold;} .fail{color:#991b1b;font-weight:bold;}
      @media print { .noprint{display:none;} }
    </style></head><body>
    <button class="noprint" onclick="window.print()" style="margin-bottom:12px;padding:8px 14px;">Print / Save as PDF</button>
    <h1>College Grading System — Grade Sheet</h1>
    <p class="meta"><?= h($cls['code']) ?> | <?= h($cls['subject']) ?> | Section: <?= h($cls['section']) ?> | <?= h($cls['semester']) ?>, S.Y. <?= h($cls['school_year']) ?></p>
    <table>
      <thead><tr><?php foreach ($head as $col) echo '<th>' . h($col) . '</th>'; ?></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($r as $idx => $val): ?>
            <td class="<?= $idx === 16 ? ($val === 'PASSED' ? 'pass' : ($val === 'FAILED' ? 'fail' : '')) : '' ?>"><?= h($val) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="17">No students.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </body></html>
    <?php
    exit;
}

// Default: CSV (opens natively in Excel)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
write_csv_row($out, ["Class Code", $cls['code']]);
write_csv_row($out, ["Subject", $cls['subject']]);
write_csv_row($out, ["Section", $cls['section']]);
write_csv_row($out, ["School Year", $cls['school_year']]);
write_csv_row($out, ["Semester", $cls['semester']]);
write_csv_row($out, []);
write_csv_row($out, $head);
foreach ($rows as $r) write_csv_row($out, $r);
fclose($out);
exit;
