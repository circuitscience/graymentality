<?php
declare(strict_types=1);

/**
 * Authentication functions
 */

require_once __DIR__ . '/../bootstrap.php';

if (!function_exists('auth_env')) {
    function auth_env(string $key, ?string $default = null): ?string
    {
        if (function_exists('gm_bootstrap_env')) {
            return gm_bootstrap_env($key, $default);
        }

        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
    }
}

if (!function_exists('auth_db_port')) {
    function auth_db_port(): string
    {
        $port = trim((string)auth_env('DB_PORT', ''));
        $hostPort = trim((string)auth_env('DB_HOST_PORT', ''));

        if ($port !== '') {
            return $port;
        }

        if ($hostPort !== '') {
            return $hostPort;
        }

        return '3306';
    }
}

if (!function_exists('auth_db_required_env')) {
    function auth_db_required_env(string $key): string
    {
        $value = trim((string)auth_env($key, ''));
        if ($value === '') {
            throw new RuntimeException("Missing required database setting: {$key}");
        }

        return $value;
    }
}

function get_db_connection()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = auth_db_required_env('DB_HOST');
    $port = auth_db_port();
    $dbname = auth_db_required_env('DB_NAME');
    $user = auth_db_required_env('DB_USER');
    $pass = (string)auth_env('DB_PASS', '');
    $charset = auth_env('DB_CHARSET', 'utf8mb4') ?: 'utf8mb4';

    $socket = trim((string)auth_env('DB_SOCKET', ''));
    $dsn = $socket !== ''
        ? "mysql:unix_socket={$socket};dbname={$dbname};charset={$charset}"
        : "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('[auth.db] ' . $e->getMessage());
        throw new RuntimeException('Database connection failed.');
    }

    return $pdo;
}

function auth_ensure_policy_columns(PDO $db): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $columns = [
        'policy_acknowledged_at' => "ALTER TABLE users ADD COLUMN policy_acknowledged_at TIMESTAMP NULL DEFAULT NULL AFTER email_verified",
        'policy_version' => "ALTER TABLE users ADD COLUMN policy_version VARCHAR(32) DEFAULT NULL AFTER policy_acknowledged_at",
        'policy_ip_address' => "ALTER TABLE users ADD COLUMN policy_ip_address VARCHAR(45) DEFAULT NULL AFTER policy_version",
        'policy_user_agent' => "ALTER TABLE users ADD COLUMN policy_user_agent VARCHAR(255) DEFAULT NULL AFTER policy_ip_address",
    ];

    foreach ($columns as $column => $alterSql) {
        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$column]);

        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec($alterSql);
        }
    }
}

function auth_ensure_profile_table(PDO $db): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $db->exec(
        "CREATE TABLE IF NOT EXISTS user_profiles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            date_of_birth DATE NULL,
            gender VARCHAR(32) NULL,
            timezone VARCHAR(64) NULL,
            onboarding_completed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_user_profiles_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function sanitize_input(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function generate_session_token(): string
{
    return bin2hex(random_bytes(32));
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function auth_is_debug_mode(): bool
{
    $appDebug = strtolower((string)auth_env('APP_DEBUG', ''));
    if (in_array($appDebug, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    $appEnv = strtolower((string)auth_env('APP_ENV', 'development'));
    return $appEnv !== 'production';
}

function auth_public_base_url(): string
{
    foreach (['PUBLIC_BASE_URL', 'MAIN_DOMAIN_URL', 'BASE_URL', 'APP_URL'] as $key) {
        $value = trim((string)auth_env($key, ''));
        if ($value !== '') {
            return rtrim($value, '/');
        }
    }

    return 'http://localhost:8088';
}

function auth_mail_from_address(): string
{
    $value = trim((string)auth_env('MAIL_FROM', ''));
    if ($value !== '') {
        return $value;
    }

    $host = (string)parse_url(auth_public_base_url(), PHP_URL_HOST);
    if ($host !== '') {
        return 'no-reply@' . $host;
    }

    return 'no-reply@graymentality.localhost';
}

function auth_mail_from_name(): string
{
    $value = trim((string)auth_env('MAIL_FROM_NAME', ''));
    if ($value !== '') {
        return $value;
    }

    return 'Gray Mentality';
}

function auth_policy_version(): string
{
    return '2026-05-03';
}

function auth_idle_timeout_seconds(): int
{
    $value = (int)auth_env('AUTH_IDLE_TIMEOUT_SECONDS', '900');
    return max(60, min(86400, $value));
}

function auth_warning_timeout_seconds(): int
{
    $idleTimeout = auth_idle_timeout_seconds();
    $value = (int)auth_env('AUTH_IDLE_WARNING_SECONDS', '60');
    return max(15, min($idleTimeout - 5, $value));
}

function auth_session_keepalive_seconds(): int
{
    $value = (int)auth_env('AUTH_SESSION_KEEPALIVE_SECONDS', '300');
    return max(60, min(3600, $value));
}

function auth_timeout_message(): string
{
    return 'Your session expired due to inactivity. Please log in again.';
}

function auth_login_url(array $params = []): string
{
    $query = http_build_query(array_filter(
        $params,
        static fn ($value): bool => $value !== null && $value !== ''
    ));

    return '/login.php' . ($query !== '' ? '?' . $query : '');
}

function auth_profile_setup_url(): string
{
    return '/profile-setup';
}

function auth_redirect_to_profile_setup(): never
{
    header('Location: ' . auth_profile_setup_url());
    exit;
}

function auth_login_message_for_reason(string $reason): string
{
    return match ($reason) {
        'timeout' => auth_timeout_message(),
        'logged_out' => 'You have been logged out.',
        default => 'Please log in to continue.',
    };
}

function auth_redirect_to_login(string $reason = 'auth_required'): never
{
    header('Location: ' . auth_login_url([
        'reason' => $reason,
        'message' => auth_login_message_for_reason($reason),
    ]));
    exit;
}

function auth_is_json_request(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json')) {
        return true;
    }

    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return $requestedWith === 'xmlhttprequest';
}

function auth_asset_url(string $filename): string
{
    $path = __DIR__ . '/assets/' . $filename;
    $version = is_file($path) ? (string)filemtime($path) : (string)time();
    return '/assets/' . rawurlencode($filename) . '?v=' . rawurlencode($version);
}

function auth_timeout_modal_head_markup(): string
{
    $config = [
        'idleTimeoutSeconds' => auth_idle_timeout_seconds(),
        'warningSeconds' => auth_warning_timeout_seconds(),
        'keepaliveSeconds' => auth_session_keepalive_seconds(),
        'keepaliveUrl' => '/session_ping.php',
        'logoutUrl' => '/logout.php?reason=timeout',
        'loginUrl' => auth_login_url([
            'reason' => 'timeout',
            'message' => auth_timeout_message(),
        ]),
        'message' => auth_timeout_message(),
    ];

    $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    if (!is_string($json)) {
        $json = '{}';
    }

    return implode("\n", [
        '<link rel="stylesheet" href="' . htmlspecialchars(auth_asset_url('session-timeout.css'), ENT_QUOTES, 'UTF-8') . '">',
        '<script>window.GM_SESSION_GUARD = Object.assign({}, window.GM_SESSION_GUARD || {}, ' . $json . ');</script>',
        '<script defer src="' . htmlspecialchars(auth_asset_url('session-timeout.js'), ENT_QUOTES, 'UTF-8') . '"></script>',
    ]);
}

function auth_timeout_modal_body_markup(): string
{
    return <<<HTML
<div class="gm-session-timeout" id="gm-session-timeout" aria-hidden="true">
    <div class="gm-session-timeout__backdrop"></div>
    <div class="gm-session-timeout__dialog" role="dialog" aria-modal="true" aria-labelledby="gm-session-timeout-title">
        <p class="gm-session-timeout__eyebrow">Session guard</p>
        <h2 id="gm-session-timeout-title">Still there?</h2>
        <p class="gm-session-timeout__copy">
            Your session is about to expire because this page has been idle.
        </p>
        <p class="gm-session-timeout__countdown">
            Automatic logout in <strong data-gm-session-countdown>60</strong>s.
        </p>
        <div class="gm-session-timeout__actions">
            <button type="button" class="gm-session-timeout__button gm-session-timeout__button--primary" data-gm-session-stay>
                Stay signed in
            </button>
            <button type="button" class="gm-session-timeout__button gm-session-timeout__button--ghost" data-gm-session-logout>
                Log out now
            </button>
        </div>
    </div>
</div>
HTML;
}

function auth_timeout_modal_output_buffer(string $buffer): string
{
    if (
        stripos($buffer, '<html') === false &&
        stripos($buffer, '<!doctype html') === false
    ) {
        return $buffer;
    }

    if (stripos($buffer, 'id="gm-session-timeout"') !== false) {
        return $buffer;
    }

    $headMarkup = auth_timeout_modal_head_markup();
    if (preg_match('~</head>~i', $buffer) === 1) {
        $buffer = preg_replace('~</head>~i', $headMarkup . "\n</head>", $buffer, 1) ?? $buffer;
    } else {
        $buffer = $headMarkup . "\n" . $buffer;
    }

    $bodyMarkup = auth_timeout_modal_body_markup();
    if (preg_match('~</body>~i', $buffer) === 1) {
        $buffer = preg_replace('~</body>~i', $bodyMarkup . "\n</body>", $buffer, 1) ?? $buffer;
    } else {
        $buffer .= "\n" . $bodyMarkup;
    }

    return $buffer;
}

function auth_register_timeout_modal(): void
{
    static $registered = false;

    if ($registered || PHP_SAPI === 'cli') {
        return;
    }

    $registered = true;
    ob_start('auth_timeout_modal_output_buffer');
}

function auth_session_has_timed_out(): bool
{
    $lastActivity = (int)($_SESSION['last_activity_at'] ?? 0);
    if ($lastActivity <= 0) {
        return false;
    }

    return (time() - $lastActivity) >= auth_idle_timeout_seconds();
}

function auth_mark_activity(): void
{
    $_SESSION['last_activity_at'] = time();
}

function auth_user_profile(int $userId): ?array
{
    $db = get_db_connection();
    auth_ensure_profile_table($db);

    $stmt = $db->prepare(
        "SELECT id, user_id, date_of_birth, gender, timezone, onboarding_completed_at, created_at, updated_at
         FROM user_profiles
         WHERE user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();

    return is_array($profile) ? $profile : null;
}

function auth_profile_is_complete(int $userId): bool
{
    $profile = auth_user_profile($userId);

    return is_array($profile)
        && trim((string)($profile['date_of_birth'] ?? '')) !== ''
        && trim((string)($profile['gender'] ?? '')) !== ''
        && trim((string)($profile['onboarding_completed_at'] ?? '')) !== '';
}

function auth_save_user_profile(int $userId, string $dateOfBirth, string $gender, string $timezone): array
{
    $dateOfBirth = trim($dateOfBirth);
    $gender = trim($gender);
    $timezone = trim($timezone);
    $allowedGenders = ['male', 'female', 'non_binary', 'prefer_not_to_say'];

    if ($dateOfBirth === '' || $gender === '') {
        return ['success' => false, 'message' => 'Date of birth and gender are required.'];
    }

    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
    $birthErrors = DateTimeImmutable::getLastErrors();
    if (
        !$birthDate
        || (is_array($birthErrors) && ((int)$birthErrors['warning_count'] > 0 || (int)$birthErrors['error_count'] > 0))
        || $birthDate > new DateTimeImmutable('today')
    ) {
        return ['success' => false, 'message' => 'Enter a valid date of birth.'];
    }

    $age = (int)$birthDate->diff(new DateTimeImmutable('today'))->y;
    if ($age < 13 || $age > 120) {
        return ['success' => false, 'message' => 'Enter an age between 13 and 120.'];
    }

    if (!in_array($gender, $allowedGenders, true)) {
        return ['success' => false, 'message' => 'Choose a valid gender option.'];
    }

    if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
        $timezone = 'America/Toronto';
    }

    $db = get_db_connection();
    auth_ensure_profile_table($db);
    $stmt = $db->prepare(
        "INSERT INTO user_profiles (user_id, date_of_birth, gender, timezone, onboarding_completed_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            date_of_birth = VALUES(date_of_birth),
            gender = VALUES(gender),
            timezone = VALUES(timezone),
            onboarding_completed_at = COALESCE(onboarding_completed_at, NOW())"
    );
    $stmt->execute([$userId, $dateOfBirth, $gender, $timezone]);

    return ['success' => true, 'message' => 'Profile saved.'];
}

function queue_mail_message(string $recipientEmail, string $subject, string $bodyText): bool
{
    $recipientEmail = normalize_email($recipientEmail);
    $subject = trim($subject);
    $bodyText = trim($bodyText);

    if ($recipientEmail === '' || $subject === '' || $bodyText === '') {
        return false;
    }

    try {
        $db = get_db_connection();
        $stmt = $db->prepare(
            "INSERT INTO mail_queue (recipient_email, subject, body_text, status, attempts, available_at)
             VALUES (?, ?, ?, 'pending', 0, NOW())"
        );
        $stmt->execute([$recipientEmail, $subject, $bodyText]);
        return true;
    } catch (Throwable $e) {
        error_log('[auth.mail_queue] ' . $e->getMessage());
        return false;
    }
}

function login_user(string $email, string $password, string $captcha_answer = ''): array
{
    $db = get_db_connection();
    $email = normalize_email($email);

    if ($email === '' || $password === '') {
        return ['success' => false, 'message' => 'Email and password are required.'];
    }

    $stmt = $db->prepare(
        "SELECT id, username, email, password_hash, first_name, last_name, role_id, is_active, email_verified
         FROM users
         WHERE email = ?
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !verify_password($password, (string)$user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    if (!(bool)$user['is_active']) {
        return ['success' => false, 'message' => 'Account is deactivated.'];
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    session_regenerate_id(true);

    $sessionToken = generate_session_token();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

    $stmt = $db->prepare(
        "INSERT INTO auth_sessions (user_id, session_token, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        (int)$user['id'],
        $sessionToken,
        $ipAddress,
        $userAgent,
        $expiresAt,
    ]);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['email'] = (string)$user['email'];
    $_SESSION['first_name'] = (string)$user['first_name'];
    $_SESSION['last_name'] = (string)$user['last_name'];
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['email_verified'] = (bool)$user['email_verified'];
    $_SESSION['session_token'] = $sessionToken;
    $_SESSION['user_data'] = [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'email' => (string)$user['email'],
        'first_name' => (string)$user['first_name'],
        'last_name' => (string)$user['last_name'],
        'role_id' => (int)$user['role_id'],
        'email_verified' => (bool)$user['email_verified'],
    ];
    auth_mark_activity();

    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([(int)$user['id']]);

    return ['success' => true, 'message' => 'Login successful.'];
}

function register_user(
    string $username,
    string $email,
    string $password,
    string $first_name,
    string $last_name,
    string $captcha_answer = '',
    bool $policy_acknowledged = false
): array {
    $db = get_db_connection();
    auth_ensure_policy_columns($db);
    $username = trim($username);
    $email = normalize_email($email);

    if ($username === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'Username, email, and password are required.'];
    }

    if (!$policy_acknowledged) {
        return ['success' => false, 'message' => 'You must acknowledge the site policies and terms before registering.'];
    }

    if (strlen($username) < 3 || strlen($password) < 8) {
        return ['success' => false, 'message' => 'Username must be at least 3 characters, password at least 8.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists.'];
    }

    $hash = hash_password($password);
    $policyVersion = auth_policy_version();
    $policyIpAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $policyUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stmt = $db->prepare(
        "INSERT INTO users (
            username,
            email,
            password_hash,
            first_name,
            last_name,
            role_id,
            is_active,
            email_verified,
            policy_acknowledged_at,
            policy_version,
            policy_ip_address,
            policy_user_agent
        )
         VALUES (?, ?, ?, ?, ?, 1, TRUE, TRUE, NOW(), ?, ?, ?)"
    );
    $stmt->execute([
        $username,
        $email,
        $hash,
        trim($first_name),
        trim($last_name),
        $policyVersion,
        $policyIpAddress,
        $policyUserAgent,
    ]);

    return ['success' => true, 'message' => 'Registration successful. You can now log in.'];
}

function lookup_password_reset_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $db = get_db_connection();
    $stmt = $db->prepare(
        "SELECT email, token, expires_at
         FROM password_resets
         WHERE token = ?
           AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function request_password_reset(string $email): array
{
    $db = get_db_connection();
    $email = normalize_email($email);
    $result = [
        'success' => true,
        'message' => 'If the email exists, a reset link has been created.',
    ];

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $result;
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return $result;
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $stmt = $db->prepare(
        "INSERT INTO password_resets (email, token, expires_at)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)"
    );
    $stmt->execute([$email, $token, $expiresAt]);

    $resetUrl = auth_public_base_url() . '/reset_password.php?token=' . urlencode($token);
    $subject = 'Gray Mentality password reset';
    $bodyText = implode("\n", [
        'A password reset was requested for your Gray Mentality account.',
        '',
        'Reset link: ' . $resetUrl,
        'This link expires in 1 hour.',
        '',
        'If you did not request this change, you can ignore this message.',
    ]);
    queue_mail_message($email, $subject, $bodyText);

    if (auth_is_debug_mode()) {
        $result['reset_url'] = $resetUrl;
    }

    return $result;
}

function change_user_password(string $currentPassword, string $newPassword): array
{
    $currentPassword = (string)$currentPassword;
    $newPassword = (string)$newPassword;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return ['success' => false, 'message' => 'You must be logged in to change your password.'];
    }

    if ($currentPassword === '' || $newPassword === '') {
        return ['success' => false, 'message' => 'Current password and new password are required.'];
    }

    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'New password must be at least 8 characters.'];
    }

    $db = get_db_connection();
    $stmt = $db->prepare(
        "SELECT id, password_hash
         FROM users
         WHERE id = ? AND is_active = TRUE
         LIMIT 1"
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !verify_password($currentPassword, (string)$user['password_hash'])) {
        return ['success' => false, 'message' => 'Current password is incorrect.'];
    }

    $hash = hash_password($newPassword);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
    $stmt->execute([$hash, (int)$user['id']]);

    $stmt = $db->prepare("DELETE FROM auth_sessions WHERE user_id = ?");
    $stmt->execute([(int)$user['id']]);

    return ['success' => true, 'message' => 'Password updated. Please log in again.'];
}

function complete_password_reset(string $token, string $password): array
{
    $token = trim($token);
    if ($token === '' || $password === '') {
        return ['success' => false, 'message' => 'Token and password are required.'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    $db = get_db_connection();
    $reset = lookup_password_reset_token($token);
    if (!$reset) {
        return ['success' => false, 'message' => 'Invalid or expired reset token.'];
    }

    $email = (string)$reset['email'];
    $hash = hash_password($password);

    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ? LIMIT 1");
    $stmt->execute([$hash, $email]);

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $stmt = $db->prepare("DELETE FROM auth_sessions WHERE user_id = ?");
        $stmt->execute([(int)$user['id']]);
    }

    $stmt = $db->prepare("DELETE FROM password_resets WHERE email = ? OR token = ?");
    $stmt->execute([$email, $token]);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);

    return ['success' => true, 'message' => 'Password updated. You can log in now.'];
}

function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (isset($_SESSION['session_token'])) {
        $db = get_db_connection();
        $stmt = $db->prepare("DELETE FROM auth_sessions WHERE session_token = ?");
        $stmt->execute([$_SESSION['session_token']]);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?: '/',
            $params['domain'] ?: '',
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_destroy();
}

function check_auth(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return null;
    }

    $db = get_db_connection();
    $stmt = $db->prepare(
        "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role_id, s.expires_at
         FROM users u
         JOIN auth_sessions s ON u.id = s.user_id
         WHERE u.id = ? AND s.session_token = ? AND s.expires_at > NOW() AND u.is_active = TRUE"
    );
    $stmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
    $user = $stmt->fetch();

    if (!$user) {
        logout_user();
        return null;
    }

    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $refresh = $db->prepare("UPDATE auth_sessions SET expires_at = ? WHERE session_token = ?");
    $refresh->execute([$expiresAt, $_SESSION['session_token']]);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['email'] = (string)$user['email'];
    $_SESSION['first_name'] = (string)$user['first_name'];
    $_SESSION['last_name'] = (string)$user['last_name'];
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['user_data'] = [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'email' => (string)$user['email'],
        'first_name' => (string)$user['first_name'],
        'last_name' => (string)$user['last_name'],
        'role_id' => (int)$user['role_id'],
    ];
    auth_mark_activity();

    return $user;
}

function require_auth(bool $requireCompletedProfile = true): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (auth_session_has_timed_out()) {
        logout_user();
        auth_redirect_to_login('timeout');
    }

    $user = check_auth();
    if (!$user) {
        auth_redirect_to_login('auth_required');
    }

    if ($requireCompletedProfile && !auth_profile_is_complete((int)$user['id'])) {
        auth_redirect_to_profile_setup();
    }

    auth_register_timeout_modal();
    return $user;
}
