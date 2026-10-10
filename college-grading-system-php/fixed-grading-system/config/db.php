<?php
function setting(string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === false ? $default : $value;
}

define('APP_ENV', setting('APP_ENV', 'development'));
define('DB_HOST', setting('DB_HOST', '127.0.0.1'));
define('DB_PORT', setting('DB_PORT', '3306'));
define('DB_NAME', setting('DB_NAME', 'college_grading_system'));
define('DB_USER', setting('DB_USER', 'root'));
define('DB_PASS', setting('DB_PASS'));

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        if (APP_ENV === 'production' && (DB_USER === 'root' || DB_PASS === '')) {
            throw new RuntimeException('Production requires a dedicated database user and password.');
        }
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Service temporarily unavailable. Please try again later.');
    }
}
