<?php
require_once __DIR__ . '/_bootstrap.php';
$u = api_require_role('teacher');
require_csrf_or_die();

$classId = (int)($_POST['class_id'] ?? 0);
$w = [
    'class_standing'   => $_POST['class_standing'] ?? null,
    'major_exams'      => $_POST['major_exams'] ?? null,
    'performance_task' => $_POST['performance_task'] ?? null,
    'activity_output'  => $_POST['activity_output'] ?? null,
];

if (!is_valid_weights($w)) {
    api_json(['ok' => false, 'message' => 'Each weight must be a number from 0 to 100 with at most two decimal places; weights must total 100%.']);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
$stmt->execute([$classId, $u['id']]);
$cls = $stmt->fetch();
if (!$cls) api_json(['ok' => false, 'message' => 'Class not found or access denied.']);

$upd = $pdo->prepare('UPDATE classes SET class_standing=?, major_exams=?, performance_task=?, activity_output=? WHERE id=?');
$upd->execute([$w['class_standing'], $w['major_exams'], $w['performance_task'], $w['activity_output'], $classId]);

add_audit_log('UPDATE_CLASS_WEIGHTS', "Updated grading weights for class {$cls['code']}.");

api_json(['ok' => true]);
