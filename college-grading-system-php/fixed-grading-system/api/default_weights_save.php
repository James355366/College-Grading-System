<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_role('admin');
require_csrf_or_die();

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
$upd = $pdo->prepare('UPDATE default_weights SET class_standing=?, major_exams=?, performance_task=?, activity_output=? WHERE id=1');
$upd->execute([$w['class_standing'], $w['major_exams'], $w['performance_task'], $w['activity_output']]);

add_audit_log('UPDATE_DEFAULT_WEIGHTS',
    "Set default weights to CS:{$w['class_standing']} ME:{$w['major_exams']} PT:{$w['performance_task']} AO:{$w['activity_output']}.");

api_json(['ok' => true]);
