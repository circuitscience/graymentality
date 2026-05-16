<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth_functions.php';

function gm_passage_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    gm_passage_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (auth_session_has_timed_out()) {
    logout_user();
    gm_passage_json(['ok' => false, 'error' => 'session_timeout'], 401);
}

$authUser = check_auth();
if (!$authUser) {
    gm_passage_json(['ok' => false, 'error' => 'not_logged_in'], 401);
}

$passageId = (int)($_POST['passage_id'] ?? 0);
$eventType = trim((string)($_POST['event_type'] ?? ''));
$stepOrder = isset($_POST['step_order']) ? (int)$_POST['step_order'] : null;

if ($passageId <= 0 || !in_array($eventType, ['start', 'step', 'complete'], true)) {
    gm_passage_json(['ok' => false, 'error' => 'bad_request'], 400);
}

try {
    $db = get_db_connection();
    $stmt = $db->prepare(
        "INSERT INTO user_passage_events (user_id, passage_id, event_type, step_order)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([(int)$authUser['id'], $passageId, $eventType, $stepOrder]);
} catch (Throwable $e) {
    error_log('[passages.event] ' . $e->getMessage());
    gm_passage_json(['ok' => false, 'error' => 'log_failed'], 500);
}

gm_passage_json(['ok' => true]);
