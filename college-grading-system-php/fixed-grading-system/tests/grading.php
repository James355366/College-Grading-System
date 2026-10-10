<?php
require_once __DIR__ . '/../includes/grading.php';
require_once __DIR__ . '/../includes/csv.php';
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$weights = ['class_standing' => 20, 'major_exams' => 40, 'performance_task' => 20, 'activity_output' => 20];
$fields = ['quiz','oral','attendance','prelim','final_exam','performance_task','activity_output'];
check(is_valid_weights($weights), 'Valid weights rejected');
foreach ([-1, 101, INF, NAN, 'broken', '', [], null, 20.001] as $value) {
    check(!valid_percentage($value), 'Invalid percentage accepted');
}
check(!is_valid_weights(['class_standing' => -10, 'major_exams' => 60, 'performance_task' => 20, 'activity_output' => 30]), 'Negative weights accepted');
check(!is_valid_weights(['class_standing' => 100]), 'Missing weights accepted');
foreach ([0 => 'failed', 75 => 'passed', 100 => 'passed'] as $score => $status) {
    $grade = compute_grade(array_fill_keys($fields, $score), $weights);
    check($grade['finalPercent'] === (float)$score && $grade['status'] === $status, 'Grade boundary wrong');
}
$missing = array_fill_keys($fields, 100);
$missing['quiz'] = null;
$grade = compute_grade($missing, $weights);
check($grade['status'] === 'incomplete' && $grade['gpa'] === null && $grade['passed'] === null, 'Missing score was treated as zero');
$out = fopen('php://temp', 'w+');
$values = ['=1+1', ' +1', "\t@SUM(1)", '-formula', 'O\'Neil, "Peña"', 86.73, 0];
write_csv_row($out, $values);
rewind($out);
$csv = fgetcsv($out, null, ',', '"', '');
check($csv === ["'=1+1", "' +1", "'\t@SUM(1)", "'-formula", 'O\'Neil, "Peña"', '86.73', '0'], 'CSV escaping/Unicode/number regression');
echo "PASS: grade boundaries, null versus zero, numeric validation, and safe CSV round trip\n";
