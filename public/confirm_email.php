<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/auth_functions.php';

$token = (string)($_GET['token'] ?? '');

try {
    $result = confirm_user_email($token);
} catch (Throwable $e) {
    error_log('[auth.confirm_email.page] ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Unable to confirm this email right now. Please try again.'];
}

$message = (string)$result['message'];
$messageType = $result['success'] ? 'success' : 'error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Email - Gray Mentality</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        .auth-container {
            max-width: 440px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(13, 17, 26, 0.9), rgba(8, 10, 15, 0.9));
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        .auth-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }
        .auth-title h1 {
            margin: 0;
            font-size: clamp(2rem, 10vw, 3rem);
            line-height: 1;
        }
        .auth-title-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
            flex: 0 0 auto;
        }
        .auth-links {
            margin-top: 18px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <main class="auth-container">
        <div class="auth-title">
            <img class="auth-title-logo" src="/assets/GM60x60.png" alt="Gray Mentality">
            <h1>Confirm Email</h1>
        </div>

        <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="auth-links">
            <a href="/login.php">Log in</a>
            <a href="/register.php">Register</a>
        </div>
    </main>
</body>
</html>
