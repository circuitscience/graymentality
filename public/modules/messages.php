<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth_functions.php';

function gm_messages_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function gm_messages_ensure_tables(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS user_messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            admin_id INT NULL,
            subject VARCHAR(180) NOT NULL DEFAULT 'Message',
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_unread (user_id, is_read, created_at),
            CONSTRAINT fk_user_messages_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_user_messages_admin
                FOREIGN KEY (admin_id) REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS admin_messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            admin_id INT NULL,
            user_message_id INT NULL,
            subject VARCHAR(180) NOT NULL DEFAULT 'User response',
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_admin_messages_created (created_at),
            KEY idx_admin_messages_user (user_id, created_at),
            CONSTRAINT fk_admin_messages_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_admin_messages_admin
                FOREIGN KEY (admin_id) REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    gm_messages_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (auth_session_has_timed_out()) {
    logout_user();
    gm_messages_json(['ok' => false, 'error' => 'session_timeout'], 401);
}

$authUser = check_auth();
if (!$authUser) {
    gm_messages_json(['ok' => false, 'error' => 'not_logged_in'], 401);
}

$db = get_db_connection();
gm_messages_ensure_tables($db);

$userId = (int)$authUser['id'];
$messageId = (int)($_POST['message_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));

if ($messageId <= 0 || !in_array($action, ['dismiss', 'reply'], true)) {
    gm_messages_json(['ok' => false, 'error' => 'bad_request'], 400);
}

$stmt = $db->prepare(
    "SELECT id, user_id, admin_id, subject
     FROM user_messages
     WHERE id = ? AND user_id = ?
     LIMIT 1"
);
$stmt->execute([$messageId, $userId]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($message)) {
    gm_messages_json(['ok' => false, 'error' => 'not_found'], 404);
}

if ($action === 'reply') {
    $body = trim((string)($_POST['body'] ?? ''));
    if ($body === '') {
        gm_messages_json(['ok' => false, 'error' => 'empty_reply'], 400);
    }

    $reply = $db->prepare(
        "INSERT INTO admin_messages (user_id, admin_id, user_message_id, subject, body)
         VALUES (?, ?, ?, ?, ?)"
    );
    $reply->execute([
        $userId,
        isset($message['admin_id']) ? (int)$message['admin_id'] : null,
        $messageId,
        'Re: ' . (string)$message['subject'],
        $body,
    ]);
}

$delete = $db->prepare("DELETE FROM user_messages WHERE id = ? AND user_id = ?");
$delete->execute([$messageId, $userId]);

gm_messages_json(['ok' => true]);
