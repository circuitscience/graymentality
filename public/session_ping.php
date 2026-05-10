<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (auth_session_has_timed_out()) {
    $next = auth_safe_next_path(isset($_REQUEST['next']) ? (string)$_REQUEST['next'] : null, (string)($_SERVER['HTTP_REFERER'] ?? '/modules/index.php'));
    logout_user();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'reason' => 'timeout',
        'redirect' => auth_login_url([
            'reason' => 'timeout',
            'message' => auth_timeout_message(),
            'next' => $next,
        ]),
    ]);
    exit;
}

$user = check_auth();
if (!$user) {
    $next = auth_safe_next_path(isset($_REQUEST['next']) ? (string)$_REQUEST['next'] : null, (string)($_SERVER['HTTP_REFERER'] ?? '/modules/index.php'));
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'reason' => 'auth_required',
        'redirect' => auth_login_url([
            'reason' => 'auth_required',
            'message' => auth_login_message_for_reason('auth_required'),
            'next' => $next,
        ]),
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'user' => [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
    ],
    'serverTime' => time(),
]);
