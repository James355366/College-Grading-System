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

$value = max(0, min(100, (float)$value));

$pdo = get_db();

// Ownership check: student must belong to a class owned by this teacher
$stmt = $pdo->prepare('SELECT s.*, c.teacher_id, c.class_standing, c.major_exams, c.performance_task, c.activity_output
                        FROM students s JOIN classes c ON c.id = s.class_id
                        WHERE s.id = ? AND s.class_id = ?');
$stmt->execute([$studentId, $classId]);
$row = $stmt->fetch();
if (!$row || (int)$row['teacher_id'] !== (int)$u['id']) {
    api_json(['ok' => false, 'message' => 'Student not found or access denied.']);
}

$upd = $pdo->prepare("UPDATE students SET `$field` = ? WHERE id = ?");
$upd->execute([$value, $studentId]);

$row[$field] = $value;
$grade = compute_grade($row, $row);

api_json(['ok' => true, 'grade' => $grade]);
