<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function gm_smtp_read_response($socket): array
{
    $lines = [];

    while (true) {
        $line = fgets($socket, 8192);
        if ($line === false) {
            throw new RuntimeException('SMTP connection closed unexpectedly.');
        }

        $trimmed = rtrim($line, "\r\n");
        $lines[] = $trimmed;

        if (preg_match('/^\d{3}[- ]/', $trimmed) !== 1) {
            continue;
        }

        if ($trimmed[3] === ' ') {
            break;
        }
    }

    $code = (int)substr($lines[count($lines) - 1], 0, 3);

    return [$code, $lines];
}

function gm_smtp_expect($socket, array $expectedCodes, string $context): array
{
    [$code, $lines] = gm_smtp_read_response($socket);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException($context . ' failed: ' . implode(' | ', $lines));
    }

    return [$code, $lines];
}

function gm_smtp_send($socket, string $command, array $expectedCodes, string $context): array
{
    fwrite($socket, $command . "\r\n");
    return gm_smtp_expect($socket, $expectedCodes, $context);
}

function gm_smtp_normalize_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
        if ($line !== '' && str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}

function gm_smtp_send_message(
    string $host,
    int $port,
    string $encryption,
    ?string $username,
    ?string $password,
    string $fromAddress,
    string $recipientEmail,
    string $subject,
    string $bodyText,
    int $timeoutSeconds = 15
): void {
    $host = trim($host);
    if ($host === '') {
        throw new RuntimeException('MAIL_SMTP_HOST is not configured.');
    }

    $encryption = strtolower(trim($encryption));
    $transportTarget = $host . ':' . $port;
    $transport = $encryption === 'ssl'
        ? 'ssl://' . $transportTarget
        : 'tcp://' . $transportTarget;

    $socket = @stream_socket_client(
        $transport,
        $errno,
        $errstr,
        $timeoutSeconds,
        STREAM_CLIENT_CONNECT
    );
    if ($socket === false) {
        throw new RuntimeException(sprintf('Unable to connect to SMTP server %s: %s', $transportTarget, $errstr));
    }

    stream_set_timeout($socket, $timeoutSeconds);

    try {
        gm_smtp_expect($socket, [220], 'SMTP greeting');

        $localHost = (string)parse_url((string)gm_cron_env('MAIN_DOMAIN_URL', 'localhost'), PHP_URL_HOST);
        if ($localHost === '') {
            $localHost = 'localhost';
        }

        $ehlo = gm_smtp_send($socket, 'EHLO ' . $localHost, [250], 'EHLO');

        if ($encryption === 'tls') {
            $supportsStartTls = false;
            foreach ($ehlo[1] as $line) {
                if (stripos($line, 'STARTTLS') !== false) {
                    $supportsStartTls = true;
                    break;
                }
            }

            if (!$supportsStartTls) {
                throw new RuntimeException('SMTP server does not advertise STARTTLS.');
            }

            gm_smtp_send($socket, 'STARTTLS', [220], 'STARTTLS');
            $cryptoMethod = defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')
                ? STREAM_CRYPTO_METHOD_TLS_CLIENT
                : STREAM_CRYPTO_METHOD_ANY_CLIENT;
            if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                throw new RuntimeException('Unable to enable TLS on SMTP connection.');
            }
            $ehlo = gm_smtp_send($socket, 'EHLO ' . $localHost, [250], 'EHLO after STARTTLS');
        }

        $username = trim((string)($username ?? ''));
        $password = (string)($password ?? '');

        if ($username !== '' || $password !== '') {
            gm_smtp_send($socket, 'AUTH LOGIN', [334], 'SMTP AUTH LOGIN');
            gm_smtp_send($socket, base64_encode($username), [334], 'SMTP username');
            gm_smtp_send($socket, base64_encode($password), [235], 'SMTP password');
        }

        gm_smtp_send($socket, 'MAIL FROM:<' . $fromAddress . '>', [250], 'MAIL FROM');
        gm_smtp_send($socket, 'RCPT TO:<' . $recipientEmail . '>', [250, 251], 'RCPT TO');
        gm_smtp_send($socket, 'DATA', [354], 'DATA');

        $headers = [
            'From: ' . auth_mail_from_name() . ' <' . $fromAddress . '>',
            'To: <' . $recipientEmail . '>',
            'Subject: ' . $subject,
            'Date: ' . date(DATE_RFC2822),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Gray Mentality SMTP Runner',
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . gm_smtp_normalize_body($bodyText) . "\r\n.";
        fwrite($socket, $payload . "\r\n");
        gm_smtp_expect($socket, [250], 'SMTP message body');

        gm_smtp_send($socket, 'QUIT', [221], 'QUIT');
    } finally {
        fclose($socket);
    }
}

$projectRoot = gm_cron_project_root();

require_once $projectRoot . '/public/auth_functions.php';

$limit = 25;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $candidate = (int)substr($arg, 8);
        if ($candidate > 0) {
            $limit = $candidate;
        }
    }
}

$smtpHost = gm_cron_env('MAIL_SMTP_HOST', '');
$smtpPort = (int)gm_cron_env('MAIL_SMTP_PORT', '587');
$smtpEncryption = gm_cron_env('MAIL_SMTP_ENCRYPTION', 'tls') ?? 'tls';
$smtpUsername = gm_cron_env('MAIL_SMTP_USERNAME', '');
$smtpPassword = gm_cron_env('MAIL_SMTP_PASSWORD', '');
$smtpTimeout = (int)gm_cron_env('MAIL_SMTP_TIMEOUT', '15');
$fromAddress = auth_mail_from_address();
$fromName = auth_mail_from_name();

if (trim($smtpHost) === '') {
    fwrite(STDERR, "MAIL_SMTP_HOST is not configured.\n");
    exit(1);
}

$db = get_db_connection();
$stmt = $db->query(
    "SELECT id, recipient_email, subject, body_text, attempts
     FROM mail_queue
     WHERE status = 'pending'
       AND available_at <= NOW()
     ORDER BY id ASC
     LIMIT " . (int)$limit
);
$messages = $stmt ? $stmt->fetchAll() : [];

$sent = 0;
$failed = 0;

foreach ($messages as $message) {
    $recipient = (string)$message['recipient_email'];
    $subject = (string)$message['subject'];
    $bodyText = (string)$message['body_text'];
    $attempts = (int)$message['attempts'];
    $nextAttempts = $attempts + 1;

    try {
        gm_smtp_send_message(
            $smtpHost,
            $smtpPort,
            $smtpEncryption,
            $smtpUsername,
            $smtpPassword,
            $fromAddress,
            $recipient,
            $subject,
            $bodyText,
            $smtpTimeout
        );

        $update = $db->prepare(
            "UPDATE mail_queue
             SET status = 'sent',
                 attempts = ?,
                 last_attempt_at = NOW(),
                 sent_at = NOW(),
                 last_error = NULL
             WHERE id = ?"
        );
        $update->execute([$nextAttempts, (int)$message['id']]);
        $sent++;
    } catch (Throwable $e) {
        $isFinalFailure = $nextAttempts >= 3;
        $update = $db->prepare(
            "UPDATE mail_queue
             SET status = ?,
                 attempts = ?,
                 last_attempt_at = NOW(),
                 available_at = ?,
                 last_error = ?
             WHERE id = ?"
        );
        $status = $isFinalFailure ? 'failed' : 'pending';
        $availableAt = $isFinalFailure ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $update->execute([
            $status,
            $nextAttempts,
            $availableAt,
            substr($e->getMessage(), 0, 1000),
            (int)$message['id'],
        ]);
        $failed++;
    }
}

echo sprintf(
    "Processed %d message(s): %d sent, %d failed\n",
    count($messages),
    $sent,
    $failed
);
