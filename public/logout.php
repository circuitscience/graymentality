<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';

logout_user();
header('Location: /index.php');
exit;
?>