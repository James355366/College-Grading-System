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

$newStatus = $t['status'] === 'active' ? 'disabled' : 'active';
$pdo->prepare('UPDATE teachers SET status = ? WHERE id = ?')->execute([$newStatus, $id]);

add_audit_log('TOGGLE_TEACHER_STATUS', "Set teacher \"{$t['username']}\" status to $newStatus.");

api_json(['ok' => true, 'status' => $newStatus]);
