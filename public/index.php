<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

function gm_front_controller_normalize_path(string $requestPath): ?string
{
    $requestPath = '/' . ltrim(str_replace('\\', '/', $requestPath), '/');
    if ($requestPath !== '/') {
        $requestPath = rtrim($requestPath, '/');
    }

    $parts = [];
    foreach (explode('/', $requestPath) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        $decodedPart = rawurldecode($part);
        if ($decodedPart === '..' || str_contains($decodedPart, '/') || str_contains($decodedPart, '\\')) {
            return null;
        }

        $parts[] = $decodedPart;
    }

    return '/' . implode('/', $parts);
}

function gm_front_controller_is_within_public(string $target): bool
{
    $publicRoot = realpath(__DIR__);
    $targetPath = realpath($target);

    return is_string($publicRoot)
        && is_string($targetPath)
        && str_starts_with($targetPath, $publicRoot . DIRECTORY_SEPARATOR);
}

function gm_front_controller_resolve_php(string $requestPath): ?string
{
    $relativePath = ltrim($requestPath, '/');
    $candidates = [
        __DIR__ . DIRECTORY_SEPARATOR . $relativePath,
    ];

    if ($relativePath === '') {
        $candidates = [__DIR__ . '/home.php'];
    } elseif (!str_contains(basename($relativePath), '.')) {
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $relativePath . '.php';
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $relativePath . DIRECTORY_SEPARATOR . 'index.php';
    }

    foreach ($candidates as $candidate) {
        if (
            is_file($candidate)
            && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) === 'php'
            && gm_front_controller_is_within_public($candidate)
            && realpath($candidate) !== realpath(__FILE__)
        ) {
            return $candidate;
        }
    }

    return null;
}

function gm_front_controller_route_target(string $requestPath): ?array
{
    $publicRoutes = [
        '/' => 'home.php',
        '/index' => 'home.php',
        '/index.php' => 'home.php',
        '/home' => 'home.php',
        '/home.php' => 'home.php',
        '/login' => 'login.php',
        '/login.php' => 'login.php',
        '/register' => 'register.php',
        '/register.php' => 'register.php',
        '/reset_password' => 'reset_password.php',
        '/reset_password.php' => 'reset_password.php',
        '/privacy' => 'legal.php',
        '/privacy.php' => 'legal.php',
        '/terms' => 'legal.php',
        '/terms.php' => 'legal.php',
        '/acceptable-use' => 'legal.php',
        '/acceptable-use.php' => 'legal.php',
        '/cookie-policy' => 'legal.php',
        '/cookie-policy.php' => 'legal.php',
        '/accessibility' => 'legal.php',
        '/accessibility.php' => 'legal.php',
        '/contact' => 'legal.php',
        '/contact.php' => 'legal.php',
        '/logout' => 'logout.php',
        '/logout.php' => 'logout.php',
        '/session_ping' => 'session_ping.php',
        '/session_ping.php' => 'session_ping.php',
    ];

    if (isset($publicRoutes[$requestPath])) {
        return [
            'target' => __DIR__ . DIRECTORY_SEPARATOR . $publicRoutes[$requestPath],
            'auth' => false,
        ];
    }

    foreach (['/modules', '/user_dashboard', '/change_password'] as $protectedPrefix) {
        if ($requestPath === $protectedPrefix || str_starts_with($requestPath, $protectedPrefix . '/') || str_starts_with($requestPath, $protectedPrefix . '.php')) {
            $target = gm_front_controller_resolve_php($requestPath);
            if ($target !== null) {
                return [
                    'target' => $target,
                    'auth' => true,
                ];
            }
        }
    }

    return null;
}

$requestPath = gm_front_controller_normalize_path(gm_request_path());
$route = $requestPath === null ? null : gm_front_controller_route_target($requestPath);

if ($route === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 Not Found';
    exit;
}

if ($route['auth']) {
    require_once __DIR__ . '/auth_functions.php';
    $authUser = require_auth();
}

require $route['target'];
