<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$authUser = require_auth();
