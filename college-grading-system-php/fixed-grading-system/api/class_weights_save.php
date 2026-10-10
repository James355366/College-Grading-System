<?php
require_once __DIR__ . '/_bootstrap.php';
$u = api_require_role('teacher');
require_csrf_or_die();

$classId = (int)($_POST['class_id'] ?? 0);
$w = [
    'class_standing'   => (float)($_POST['class_standing'] ?? 0),
    'major_exams'      => (float)($_POST['major_exams'] ?? 0),
    'performance_task' => (float)($_POST['performance_task'] ?? 0),
    'activity_output'  => (float)($_POST['activity_output'] ?? 0),
];

if (!is_valid_weights($w)) {
    api_json(['ok' => false, 'message' => 'Weights must total exactly 100%.']);
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
