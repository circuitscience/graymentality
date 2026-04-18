<?php
declare(strict_types=1);

/**
 * save_log.php
 * ------------------------------------------------------------
 * AJAX endpoint for Muscle Growth Daily Check-In
 *
 * Expects POST:
 *  - strength_progress (1–5) [required]
 *  - recovery_score    (1–5) [required]
 *  - soreness_score    (1–5) [required]
 *  - notes             (string) [optional]
 *
 * Writes to:
 *  - muscle_growth_logs (strength_progress, recovery_score, soreness_score, notes, created_at)
 *
 * Returns JSON:
 *  { ok: true, message: "Saved!" }
 *  { ok: false, message: "Error..." }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/config.php';

function respond(bool $ok, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed', 405);
}

// --- Fetch + validate inputs ---
$strength = $_POST['strength_progress'] ?? '';
$recovery = $_POST['recovery_score'] ?? '';
$soreness = $_POST['soreness_score'] ?? '';
$notes    = trim((string)($_POST['notes'] ?? ''));

if ($strength === '' || $recovery === '' || $soreness === '') {
    respond(false, 'Please complete all required fields.', 400);
}

if (!ctype_digit((string)$strength) || !ctype_digit((string)$recovery) || !ctype_digit((string)$soreness)) {
    respond(false, 'Invalid numeric values.', 400);
}

$strengthI = (int)$strength;
$recoveryI = (int)$recovery;
$sorenessI = (int)$soreness;

if ($strengthI < 1 || $strengthI > 5 || $recoveryI < 1 || $recoveryI > 5 || $sorenessI < 1 || $sorenessI > 5) {
    respond(false, 'Scores must be between 1 and 5.', 400);
}

// Optional: clamp notes length to prevent abuse
if (mb_strlen($notes) > 2000) {
    $notes = mb_substr($notes, 0, 2000);
}

// --- Insert ---
$sql = "INSERT INTO muscle_growth_logs
          (strength_progress, recovery_score, soreness_score, notes, created_at)
        VALUES
          (?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    respond(false, 'Database prepare failed.', 500);
}

$stmt->bind_param('iiis', $strengthI, $recoveryI, $sorenessI, $notes);

if (!$stmt->execute()) {
    $stmt->close();
    respond(false, 'Database insert failed.', 500);
}

$stmt->close();
respond(true, 'Check-in saved.');
