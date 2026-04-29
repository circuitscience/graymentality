<?php
declare(strict_types=1);

/**
 * Gray Mentality Landing bootstrap.
 *
 * Responsibilities:
 * - locate the project root
 * - load `.env`
 * - expose env helpers
 * - create a shared mysqli connection when DB settings are present
 */

function gm_bootstrap_load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        $existingValue = getenv($key);
        if (($existingValue !== false && $existingValue !== '') || (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') || (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '')) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function gm_bootstrap_env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
        return (string)$_SERVER[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

function gm_bootstrap_db_port(string $default = '3306'): string
{
    $host = strtolower(trim((string)gm_bootstrap_env('DB_HOST', '127.0.0.1')));
    $port = trim((string)gm_bootstrap_env('DB_PORT', ''));
    $hostPort = trim((string)gm_bootstrap_env('DB_HOST_PORT', ''));

    if (in_array($host, ['127.0.0.1', 'localhost'], true) && $hostPort !== '') {
        return $hostPort;
    }

    if ($port !== '') {
        return $port;
    }

    if ($hostPort !== '') {
        return $hostPort;
    }

    return $default;
}

function gm_request_path(): string
{
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($requestUri, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        return '/';
    }

    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    return $path === '' ? '/' : $path;
}

function gm_public_url(string $path = '/'): string
{
    $path = trim($path);

    if ($path === '') {
        return '/';
    }

    if (preg_match('~^(?:https?:)?//~i', $path) === 1) {
        return $path;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return $path;
}

function gm_bootstrap_root(): string
{
    $guesses = array_filter([
        gm_bootstrap_env('APP_ROOT'),
        __DIR__,
        '/var/www/graymentality',
    ]);

    foreach ($guesses as $guess) {
        $guess = rtrim((string)$guess, DIRECTORY_SEPARATOR);
        if ($guess !== '' && is_dir($guess)) {
            return $guess;
        }
    }

    return __DIR__;
}

function gm_bootstrap_env_file_path(string $root): string
{
    $explicitPath = trim((string)gm_bootstrap_env('GM_ENV_FILE', ''));
    if ($explicitPath === '') {
        $explicitPath = trim((string)gm_bootstrap_env('APP_ENV_FILE', ''));
    }

    if ($explicitPath !== '') {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $explicitPath) === 1) {
            return $explicitPath;
        }

        return $root . DIRECTORY_SEPARATOR . ltrim($explicitPath, "\\/");
    }

    $appEnv = strtolower(trim((string)gm_bootstrap_env('APP_ENV', '')));
    if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
        $localPath = $root . DIRECTORY_SEPARATOR . '.env.local';
        if (is_file($localPath)) {
            return $localPath;
        }
    }

    if (in_array($appEnv, ['staging', 'stage'], true)) {
        $stagingPath = $root . DIRECTORY_SEPARATOR . '.env.staging';
        if (is_file($stagingPath)) {
            return $stagingPath;
        }
    }

    return $root . DIRECTORY_SEPARATOR . '.env';
}

if (!defined('GM_LANDING_ROOT')) {
    define('GM_LANDING_ROOT', gm_bootstrap_root());
}

gm_bootstrap_load_env_file(gm_bootstrap_env_file_path(GM_LANDING_ROOT));

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        return gm_bootstrap_env($key, $default);
    }
}

if (!function_exists('gm_landing_configure_mysqli_connection')) {
    function gm_landing_configure_mysqli_connection(mysqli $conn, string $charset = 'utf8mb4', string $collation = 'utf8mb4_0900_ai_ci'): void
    {
        $conn->set_charset($charset);
        $conn->query("SET NAMES {$charset} COLLATE {$collation}");
    }
}

$dbHost = gm_bootstrap_env('DB_HOST');
$dbName = gm_bootstrap_env('DB_NAME');
$dbUser = gm_bootstrap_env('DB_USER');
$dbPass = gm_bootstrap_env('DB_PASS', '');
$dbPort = (int)gm_bootstrap_db_port('3306');
$dbCharset = gm_bootstrap_env('DB_CHARSET', 'utf8mb4');

$conn = null;

if ($dbHost !== null && $dbName !== null && $dbUser !== null) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($dbHost, $dbUser, (string)$dbPass, $dbName, $dbPort);

    if ($conn->connect_errno) {
        error_log(
            sprintf(
                '[gm-landing] db connect failed (%d): %s | host=%s db=%s user=%s',
                $conn->connect_errno,
                $conn->connect_error,
                $dbHost,
                $dbName,
                $dbUser
            )
        );
        $conn = null;
    } else {
        gm_landing_configure_mysqli_connection($conn, $dbCharset);
    }
}

if (!defined('GM_LANDING_DB_READY')) {
    define('GM_LANDING_DB_READY', $conn instanceof mysqli);
}
