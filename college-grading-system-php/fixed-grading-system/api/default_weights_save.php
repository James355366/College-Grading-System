<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_role('admin');
require_csrf_or_die();

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
$upd = $pdo->prepare('UPDATE default_weights SET class_standing=?, major_exams=?, performance_task=?, activity_output=? WHERE id=1');
$upd->execute([$w['class_standing'], $w['major_exams'], $w['performance_task'], $w['activity_output']]);

add_audit_log('UPDATE_DEFAULT_WEIGHTS',
    "Set default weights to CS:{$w['class_standing']} ME:{$w['major_exams']} PT:{$w['performance_task']} AO:{$w['activity_output']}.");

api_json(['ok' => true]);
