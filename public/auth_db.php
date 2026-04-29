<?php
declare(strict_types=1);

/**
 * Database connection helper
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

if (!function_exists('get_db_connection')) {
function get_db_connection() {
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
}

function sanitize_input(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generate_session_token(): string {
    return bin2hex(random_bytes(32));
}

function hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}
