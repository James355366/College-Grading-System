<?php
require_once __DIR__ . '/_bootstrap.php';
$u = api_require_role('teacher');
require_csrf_or_die();

$classId = (int)($_POST['class_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$studentId = trim($_POST['student_id'] ?? '');

if ($name === '') api_json(['ok' => false, 'message' => 'Student name is required.']);

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
$stmt->execute([$classId, $u['id']]);
$cls = $stmt->fetch();
if (!$cls) api_json(['ok' => false, 'message' => 'Class not found or access denied.']);

if ($studentId === '') {
    $studentId = 'S-' . random_int(10000, 99999);
}

$ins = $pdo->prepare('INSERT INTO students (class_id, student_id, name) VALUES (?, ?, ?)');
$ins->execute([$classId, $studentId, $name]);

add_audit_log('ADD_STUDENT', "Added student \"$name\" to class {$cls['code']}.");

api_json(['ok' => true, 'id' => $pdo->lastInsertId()]);
