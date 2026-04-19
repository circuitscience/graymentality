<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

function gm_front_controller_target(string $requestPath): ?string
{
    if ($requestPath === '/' || $requestPath === '/index' || $requestPath === '/index.php') {
        return __DIR__ . '/home.php';
    }

    $relativePath = ltrim($requestPath, '/');
    if ($relativePath === '') {
        return __DIR__ . '/home.php';
    }

    $candidates = [
        __DIR__ . DIRECTORY_SEPARATOR . $relativePath,
    ];

    if (!str_contains(basename($relativePath), '.')) {
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $relativePath . '.php';
    }

    $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $relativePath . DIRECTORY_SEPARATOR . 'index.php';

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$target = gm_front_controller_target(gm_request_path());

if ($target === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 Not Found';
    exit;
}

require $target;
