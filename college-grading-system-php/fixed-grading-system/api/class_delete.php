<?php
require_once __DIR__ . '/_bootstrap.php';
$u = api_require_role('teacher');
require_csrf_or_die();

$id = (int)($_POST['id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
$stmt->execute([$id, $u['id']]);
$cls = $stmt->fetch();
if (!$cls) api_json(['ok' => false, 'message' => 'Class not found or access denied.']);

$pdo->prepare('DELETE FROM classes WHERE id = ?')->execute([$id]);
add_audit_log('DELETE_CLASS', "Deleted class \"{$cls['code']}\".");

api_json(['ok' => true]);
