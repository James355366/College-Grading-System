<?php
require_once __DIR__ . '/_bootstrap.php';
$u = api_require_role('teacher');
require_csrf_or_die();

$classId = (int)($_POST['class_id'] ?? 0);
$studentId = (int)($_POST['student_id'] ?? 0);
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? null;

$allowedFields = ['quiz','oral','attendance','prelim','final_exam','performance_task','activity_output'];
if (!in_array($field, $allowedFields, true) || $value === null) {
    api_json(['ok' => false, 'message' => 'Invalid field.']);
}

if ($value !== '' && !valid_percentage($value)) {
    http_response_code(422);
    api_json(['ok' => false, 'message' => 'Enter a score from 0 to 100 with at most two decimal places, or leave it blank for incomplete.']);
}
$value = $value === '' ? null : (float)$value;

$pdo = get_db();

$pdo->beginTransaction();
// Ownership check: student must belong to a class owned by this teacher
$stmt = $pdo->prepare('SELECT s.*, c.teacher_id, c.class_standing, c.major_exams, c.performance_task AS weight_performance_task, c.activity_output AS weight_activity_output
                        FROM students s JOIN classes c ON c.id = s.class_id
                        WHERE s.id = ? AND s.class_id = ? FOR UPDATE');
$stmt->execute([$studentId, $classId]);
$row = $stmt->fetch();
if (!$row || (int)$row['teacher_id'] !== (int)$u['id']) {
    $pdo->rollBack();
    api_json(['ok' => false, 'message' => 'Student not found or access denied.']);
}

$upd = $pdo->prepare("UPDATE students SET `$field` = ? WHERE id = ?");
$upd->execute([$value, $studentId]);

$oldValue = $row[$field] === null ? null : (float)$row[$field];
$row[$field] = $value;
$weights = [
    'class_standing' => $row['class_standing'],
    'major_exams' => $row['major_exams'],
    'performance_task' => $row['weight_performance_task'],
    'activity_output' => $row['weight_activity_output'],
];
$grade = compute_grade($row, $weights);

if ($oldValue !== $value) {
    add_audit_log('UPDATE_SCORE', json_encode(['class_id' => $classId, 'student_id' => $studentId, 'field' => $field, 'old' => $oldValue, 'new' => $value]));
}
$pdo->commit();
require_once __DIR__ . '/../includes/roster.php';
api_json(['ok' => true, 'grade' => $grade, 'value' => $value, 'statistics' => class_statistics($pdo, [$classId])[$classId]]);
