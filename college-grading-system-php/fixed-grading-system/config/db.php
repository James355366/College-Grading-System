<?php
/**
 * Database configuration.
 * Update these four constants to match your MySQL environment.
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'college_grading_system');
define('DB_USER', 'root');
define('DB_PASS', '');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Please verify config/db.php and that the "college_grading_system" '
                . 'database has been imported from sql/schema.sql. (' . htmlspecialchars($e->getMessage()) . ')');
        }
    }
    return $pdo;
}
