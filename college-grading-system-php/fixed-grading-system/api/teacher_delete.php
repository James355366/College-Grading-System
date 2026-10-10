<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_role('admin');
require_csrf_or_die();

$id = (int)($_POST['id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM teachers WHERE id = ?');
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) api_json(['ok' => false, 'message' => 'Teacher not found.']);

// ON DELETE CASCADE removes their classes (and each class's students) automatically.
$pdo->prepare('DELETE FROM teachers WHERE id = ?')->execute([$id]);

add_audit_log('DELETE_TEACHER', "Deleted teacher account \"{$t['username']}\" and their classes.");

api_json(['ok' => true]);
