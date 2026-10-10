<?php
// Run with a schema-management DB account; the web application needs DML only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();
$duplicates = $pdo->query('SELECT class_id, student_id, COUNT(*) AS copies FROM students GROUP BY class_id, student_id HAVING COUNT(*) > 1')->fetchAll();
if ($duplicates) {
    fwrite(STDERR, "Duplicate student IDs exist within a class. Resolve them before migrating; no records were changed.\n");
    exit(1);
}
$pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
    bucket CHAR(64) PRIMARY KEY, attempts INT NOT NULL, started_at DATETIME NOT NULL,
    INDEX idx_login_started (started_at)
) ENGINE=InnoDB');
$columns = $pdo->query('SHOW COLUMNS FROM students')->fetchAll(PDO::FETCH_UNIQUE);
foreach (['quiz', 'oral', 'attendance', 'prelim', 'final_exam', 'performance_task', 'activity_output'] as $column) {
    if ($columns[$column]['Null'] !== 'YES' || $columns[$column]['Default'] !== null) {
        $pdo->exec("ALTER TABLE students MODIFY `$column` DECIMAL(5,2) NULL DEFAULT NULL");
    }
}
$index = $pdo->query("SHOW INDEX FROM students WHERE Key_name = 'unique_class_student'")->fetch();
if (!$index) $pdo->exec('ALTER TABLE students ADD CONSTRAINT unique_class_student UNIQUE (class_id, student_id)');
echo "Migration complete; existing scores preserved.\n";
