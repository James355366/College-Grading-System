<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_role('admin');
require_csrf_or_die();

$id = trim($_POST['id'] ?? '');
$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$email = trim($_POST['email'] ?? '');
$department = trim($_POST['department'] ?? '');

if ($username === '' || $name === '' || ($id === '' && $password === '')) {
    api_json(['ok' => false, 'message' => 'Username, password and name are required.']);
}

$pdo = get_db();

// Check for duplicate username (excluding self when editing)
$dupStmt = $id === ''
    ? $pdo->prepare('SELECT id FROM teachers WHERE LOWER(username) = LOWER(?)')
    : $pdo->prepare('SELECT id FROM teachers WHERE LOWER(username) = LOWER(?) AND id != ?');
$dupParams = $id === '' ? [$username] : [$username, $id];
$dupStmt->execute($dupParams);
if ($dupStmt->fetch()) {
    api_json(['ok' => false, 'message' => 'Username already exists.']);
}

if ($id === '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO teachers (username, password_hash, name, email, department, status) VALUES (?,?,?,?,?,\'active\')');
    $ins->execute([$username, $hash, $name, $email, $department]);
    add_audit_log('ADD_TEACHER', "Added teacher account \"$username\" ($name).");
} else {
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE teachers SET username=?, password_hash=?, name=?, email=?, department=? WHERE id=?');
        $upd->execute([$username, $hash, $name, $email, $department, $id]);
    } else {
        $upd = $pdo->prepare('UPDATE teachers SET username=?, name=?, email=?, department=? WHERE id=?');
        $upd->execute([$username, $name, $email, $department, $id]);
    }
    add_audit_log('UPDATE_TEACHER', "Updated teacher account \"$username\".");
}

api_json(['ok' => true]);
