<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';

$reason = trim((string)($_REQUEST['reason'] ?? 'logged_out'));
if (!in_array($reason, ['logged_out', 'timeout', 'auth_required'], true)) {
    $reason = 'logged_out';
}

$redirect = auth_login_url([
    'reason' => $reason,
    'message' => auth_login_message_for_reason($reason),
]);

logout_user();

if (auth_is_json_request() || strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'redirect' => $redirect,
    ]);
    exit;
}

header('Location: ' . $redirect);
exit;
