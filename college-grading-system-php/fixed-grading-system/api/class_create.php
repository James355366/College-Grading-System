<?php
require_once __DIR__ . '/_bootstrap.php';
$u = api_require_role('teacher');
require_csrf_or_die();

$code = trim($_POST['code'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$section = trim($_POST['section'] ?? '');
$schoolYear = trim($_POST['school_year'] ?? '');
$semester = trim($_POST['semester'] ?? '1st Semester');

if ($code === '' || $subject === '' || $section === '') {
    api_json(['ok' => false, 'message' => 'Class code, subject and section are required.']);
}

$pdo = get_db();
$dw = $pdo->query('SELECT * FROM default_weights WHERE id=1')->fetch();

$ins = $pdo->prepare('INSERT INTO classes (code, subject, section, school_year, semester, teacher_id, class_standing, major_exams, performance_task, activity_output)
                       VALUES (?,?,?,?,?,?,?,?,?,?)');
$ins->execute([
    $code, $subject, $section, $schoolYear, $semester, $u['id'],
    $dw['class_standing'], $dw['major_exams'], $dw['performance_task'], $dw['activity_output'],
]);
$classId = $pdo->lastInsertId();

add_audit_log('ADD_CLASS', "Created class \"$code - $subject\".");

api_json(['ok' => true, 'class_id' => $classId]);
