<?php
declare(strict_types=1);

/**
 * Database connection helper
 */

function get_db_connection() {
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