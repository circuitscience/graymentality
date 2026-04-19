<?php
declare(strict_types=1);

function gm_cron_load_env_file(string $path): void
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
        if (
            ($existingValue !== false && $existingValue !== '') ||
            (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') ||
            (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '')
        ) {
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

function gm_cron_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && trim($value) !== '') {
        return trim($value);
    }

    return $default;
}

function gm_cron_bool_env(string $key, bool $default = false): bool
{
    $value = strtolower((string)gm_cron_env($key, $default ? '1' : '0'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function gm_cron_int_env(string $key, int $default): int
{
    return (int)gm_cron_env($key, (string)$default);
}

function gm_cron_db_port(int $default = 3306): int
{
    $host = strtolower(trim((string)gm_cron_env('DB_HOST', '127.0.0.1')));
    $port = trim((string)gm_cron_env('DB_PORT', ''));
    $hostPort = trim((string)gm_cron_env('DB_HOST_PORT', ''));

    if (in_array($host, ['127.0.0.1', 'localhost'], true) && $hostPort !== '') {
        return max(1, (int)$hostPort);
    }

    if ($port !== '') {
        return max(1, (int)$port);
    }

    if ($hostPort !== '') {
        return max(1, (int)$hostPort);
    }

    return $default;
}

function gm_cron_project_root(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    $root = dirname(__DIR__, 2);
    gm_cron_load_env_file($root . DIRECTORY_SEPARATOR . '.env');

    return $root;
}

function gm_cron_ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException(sprintf('Unable to create directory: %s', $path));
    }
}
