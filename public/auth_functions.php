<?php
declare(strict_types=1);

/**
 * Authentication functions
 */

function get_db_connection()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3307';
    $dbname = getenv('DB_NAME') ?: 'jerry_bil_graymentality';
    $user = getenv('DB_USER') ?: 'jerry_bil_gm';
    $pass = getenv('DB_PASS') ?: '!GM263e11';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }

    return $pdo;
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
    $appDebug = strtolower((string)(getenv('APP_DEBUG') ?: ''));
    if (in_array($appDebug, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    $appEnv = strtolower((string)(getenv('APP_ENV') ?: 'development'));
    return $appEnv !== 'production';
}

function auth_public_base_url(): string
{
    foreach (['PUBLIC_BASE_URL', 'MAIN_DOMAIN_URL', 'BASE_URL', 'APP_URL'] as $key) {
        $value = trim((string)(getenv($key) ?: ''));
        if ($value !== '') {
            return rtrim($value, '/');
        }
    }

    return 'http://localhost:8088';
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
    string $captcha_answer = ''
): array {
    $db = get_db_connection();
    $username = trim($username);
    $email = normalize_email($email);

    if ($username === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'Username, email, and password are required.'];
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
    $stmt = $db->prepare(
        "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active, email_verified)
         VALUES (?, ?, ?, ?, ?, 1, TRUE, TRUE)"
    );
    $stmt->execute([$username, $email, $hash, trim($first_name), trim($last_name)]);

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

    if (auth_is_debug_mode()) {
        $result['reset_url'] = auth_public_base_url() . '/reset_password.php?token=' . urlencode($token);
    }

    return $result;
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
    session_destroy();
}

function check_auth(): ?array
{
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

    return $user;
}

function require_auth(): array
{
    $user = check_auth();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}
