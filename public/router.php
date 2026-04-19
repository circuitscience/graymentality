<?php
declare(strict_types=1);

$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$requestPath = '/' . ltrim(str_replace('\\', '/', $requestPath), '/');

if ($requestPath !== '/') {
    $candidate = __DIR__ . $requestPath;
    if (is_file($candidate) && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php') {
        return false;
    }
}

require __DIR__ . '/index.php';
