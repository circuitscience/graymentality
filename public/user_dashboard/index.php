<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth_functions.php';
$authUser = require_auth();

header('Location: /modules/index.php');
exit;
