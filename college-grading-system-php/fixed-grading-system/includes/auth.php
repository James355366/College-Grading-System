<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => APP_ENV === 'production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
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

function verify_csrf($token): bool {
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF for AJAX/API requests (from header or POST field); dies with
 * a JSON 403 error if invalid.
 */
function require_csrf_or_die(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
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
    $u = $_SESSION['user'] ?? null;
    if (!$u) return null;
    $table = ($u['role'] ?? '') === 'admin' ? 'admins' : 'teachers';
    $sql = "SELECT id, username, name FROM $table WHERE id = ?";
    if ($table === 'teachers') $sql .= " AND status = 'active'";
    $stmt = get_db()->prepare($sql);
    $stmt->execute([$u['id']]);
    $account = $stmt->fetch();
    if (!$account) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    return $_SESSION['user'] = array_merge($u, $account);
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

    // Both IP and account limits persist across new sessions and concurrent requests.
    $retryAfter = login_throttle($pdo, $username);
    if ($retryAfter > 0) {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        return ['ok' => false, 'message' => 'Too many sign-in attempts. Please try again later.'];
    }
    if (APP_ENV === 'production' && in_array($password, ['admin123', 'teacher123'], true)) {
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }

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
        clear_login_attempts($pdo, $username);
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
        clear_login_attempts($pdo, $username);
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

// Reserve attempts atomically before password verification to prevent concurrent bypass.
function login_account_key(PDO $pdo, string $username): string {
    $stmt = $pdo->prepare('SELECT username FROM admins WHERE username = ? UNION SELECT username FROM teachers WHERE username = ?');
    $stmt->execute([$username, $username]);
    $canonical = $stmt->fetchColumn();
    return hash('sha256', 'account:' . strtolower($canonical === false ? $username : $canonical));
}

function login_throttle(PDO $pdo, string $username): int {
    $limits = [login_account_key($pdo, $username) => 5,
        hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) => 100];
    ksort($limits);
    $pdo->beginTransaction();
    try {
        $retry = 0;
        foreach ($limits as $key => $limit) {
            $stmt = $pdo->prepare('INSERT INTO login_attempts (bucket, attempts, started_at) VALUES (?, 1, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE attempts = IF(started_at <= UTC_TIMESTAMP() - INTERVAL 15 MINUTE, 1, attempts + 1),
                started_at = IF(started_at <= UTC_TIMESTAMP() - INTERVAL 15 MINUTE, UTC_TIMESTAMP(), started_at)');
            $stmt->execute([$key]);
            $stmt = $pdo->prepare('SELECT attempts, GREATEST(1, 900 - TIMESTAMPDIFF(SECOND, started_at, UTC_TIMESTAMP())) AS remaining FROM login_attempts WHERE bucket = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            if ((int)$row['attempts'] > $limit) $retry = max($retry, (int)$row['remaining']);
        }
        $pdo->commit();
        // Keep inactive buckets for at most one day.
        $pdo->exec('DELETE FROM login_attempts WHERE started_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
        return $retry;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function clear_login_attempts(PDO $pdo, string $username): void {
    $pdo->prepare('DELETE FROM login_attempts WHERE bucket = ?')->execute([login_account_key($pdo, $username)]);
}
