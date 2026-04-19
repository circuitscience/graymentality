<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (auth_session_has_timed_out()) {
    logout_user();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'reason' => 'timeout',
        'redirect' => auth_login_url([
            'reason' => 'timeout',
            'message' => auth_timeout_message(),
        ]),
    ]);
    exit;
}

$user = check_auth();
if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'reason' => 'auth_required',
        'redirect' => auth_login_url([
            'reason' => 'auth_required',
            'message' => auth_login_message_for_reason('auth_required'),
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
