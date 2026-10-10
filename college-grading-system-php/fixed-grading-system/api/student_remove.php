<?php
require_once __DIR__ . '/_bootstrap.php';
$u = api_require_role('teacher');
require_csrf_or_die();

$id = (int)($_POST['id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare('SELECT s.*, c.teacher_id, c.code FROM students s JOIN classes c ON c.id = s.class_id WHERE s.id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || (int)$row['teacher_id'] !== (int)$u['id']) {
    api_json(['ok' => false, 'message' => 'Student not found or access denied.']);
}

$pdo->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
add_audit_log('REMOVE_STUDENT', "Removed student \"{$row['name']}\" from class {$row['code']}.");

api_json(['ok' => true]);
