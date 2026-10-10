<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/grading.php';

header('Content-Type: application/json');

function api_require_login(): array {
    $u = current_session();
    if (!$u) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Not authenticated.']);
        exit;
    }
    return $u;
}

function api_require_role(string $role): array {
    $u = api_require_login();
    if ($u['role'] !== $role) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Forbidden.']);
        exit;
    }
    return $u;
}

function api_json($data): void {
    echo json_encode($data);
    exit;
}
