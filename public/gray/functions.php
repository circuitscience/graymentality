<?php
declare(strict_types=1);

function gray_escape(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function gray_timezone(?string $name): DateTimeZone {
    try { return new DateTimeZone($name ?: 'America/Toronto'); }
    catch (Exception $e) { return new DateTimeZone('America/Toronto'); }
}
function gray_content(string $date): array {
    $items = require dirname(__DIR__, 2) . '/data/gray_content.php';
    $day = (int)(new DateTimeImmutable($date, new DateTimeZone('UTC')))->format('U');
    return $items[((int)floor($day / 86400) % count($items) + count($items)) % count($items)];
}
function gray_text(array $input, string $key, int $limit): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > $limit) {
        throw new InvalidArgumentException('Please shorten your ' . str_replace('_', ' ', $key) . ' entry.');
    }
    return trim($value);
}
function gray_choice(array $input, string $key, array $allowed, bool $optional = false): ?string {
    $value = $input[$key] ?? '';
    if ($optional && $value === '') { return null; }
    if (!is_string($value) || !in_array($value, $allowed, true)) {
        throw new InvalidArgumentException('Please choose a valid ' . str_replace('_', ' ', $key) . '.');
    }
    return $value;
}
function gray_validate_daily(array $input): array {
    $action = gray_text($input, 'action', 500);
    if ($action === '') { throw new InvalidArgumentException('Add your one correct action.'); }
    return [
        'reality' => gray_text($input, 'reality', 8000),
        'action' => $action,
        'body_choice' => gray_choice($input, 'body_choice', ['train','move','recover']),
        'drift_status' => gray_choice($input, 'drift_status', ['forward','maintaining','drifting']),
        'outcome' => gray_choice($input, 'outcome', ['done','partly','not_done','changed'], true),
        'reflection' => gray_text($input, 'reflection', 8000),
        'question_answer' => gray_text($input, 'question_answer', 8000),
    ];
}
function gray_csrf_valid(array $input, string $token): bool {
    return isset($input['csrf']) && is_string($input['csrf']) && hash_equals($token, $input['csrf']);
}
function gray_save_daily(PDO $db, int $userId, string $date, string $timezone, array $content, array $values): void {
    $stmt = $db->prepare('INSERT INTO gray_daily_entries
        (user_id, entry_date, timezone, concept_title, concept_text, question_text, reality, action, body_choice, drift_status, outcome, reflection, question_answer)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE reality=VALUES(reality), action=VALUES(action), body_choice=VALUES(body_choice),
        drift_status=VALUES(drift_status), outcome=VALUES(outcome), reflection=VALUES(reflection), question_answer=VALUES(question_answer)');
    $stmt->execute(array_merge([$userId, $date, $timezone], $content, array_values($values)));
}
function gray_daily(PDO $db, int $userId, string $date): array {
    $stmt = $db->prepare('SELECT * FROM gray_daily_entries WHERE user_id=? AND entry_date=?');
    $stmt->execute([$userId, $date]);
    return $stmt->fetch() ?: [];
}
