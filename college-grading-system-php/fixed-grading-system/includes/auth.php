<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------------------------------------------------------------- CSRF */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool {
    return $token !== null && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF for AJAX/API requests (from header or POST field); dies with
 * a JSON 403 error if invalid.
 */
function require_csrf_or_die(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!verify_csrf($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
        exit;
    }
}

/* ------------------------------------------------------------- BASE URL */

/**
 * Detects the app's base URL path automatically (e.g. "" if installed at
 * the web root, or "/college-grading-system-php" if installed in a
 * subfolder). This is computed by comparing the project's filesystem path
 * to the server's document root, so it works no matter which script
 * (index.php, admin/dashboard.php, api/...) calls it.
 */
function base_url(): string {
    static $base = null;
    if ($base === null) {
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/'); // project root (parent of /includes)
        $base = ($docRoot !== '' && strpos($appRoot, $docRoot) === 0)
            ? substr($appRoot, strlen($docRoot))
            : '';
    }
    return $base;
}

/* --------------------------------------------------------------- SESSION */

function current_session(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): array {
    $u = current_session();
    if (!$u) {
        header('Location: ' . base_url() . '/index.php');
        exit;
    }
    return $u;
}

function require_role(string $role): array {
    $u = require_login();
    if ($u['role'] !== $role) {
        header('Location: ' . base_url() . '/index.php');
        exit;
    }
    return $u;
}

/* Escape helper for view templates */
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ----------------------------------------------------------------LOGIN */

function attempt_login(string $username, string $password): array {
    $pdo = get_db();
    $username = trim($username);

    // Try admin table first
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['user'] = [
            'role' => 'admin',
            'id' => (int)$admin['id'],
            'username' => $admin['username'],
            'name' => $admin['name'],
        ];
        session_regenerate_id(true);
        add_audit_log('LOGIN', 'Administrator logged in.');
        return ['ok' => true];
    }

    // Try teacher table
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $teacher = $stmt->fetch();
    if ($teacher && password_verify($password, $teacher['password_hash'])) {
        if ($teacher['status'] === 'disabled') {
            return ['ok' => false, 'message' => 'This teacher account has been disabled by the administrator.'];
        }
        $_SESSION['user'] = [
            'role' => 'teacher',
            'id' => (int)$teacher['id'],
            'username' => $teacher['username'],
            'name' => $teacher['name'],
        ];
        session_regenerate_id(true);
        add_audit_log('LOGIN', "Teacher {$teacher['name']} logged in.", 'teacher', $teacher['name']);
        return ['ok' => true];
    }

    return ['ok' => false, 'message' => 'Invalid username or password.'];
}

function do_logout(): void {
    $u = current_session();
    if ($u) {
        add_audit_log('LOGOUT', "{$u['name']} logged out.", $u['role'], $u['name']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path']);
    }
    session_destroy();
}

/* ------------------------------------------------------------ AUDIT LOG */

function add_audit_log(string $action, string $details, ?string $roleOverride = null, ?string $actorOverride = null): void {
    $pdo = get_db();
    $u = current_session();
    $actor = $actorOverride ?? ($u['name'] ?? $u['username'] ?? 'unknown');
    $role = $roleOverride ?? ($u['role'] ?? 'admin');
    $stmt = $pdo->prepare('INSERT INTO audit_logs (actor, role, action, details) VALUES (?, ?, ?, ?)');
    $stmt->execute([$actor, $role, $action, $details]);
}
