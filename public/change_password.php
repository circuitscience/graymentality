<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';
$authUser = require_auth();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($newPassword !== $confirmPassword) {
        $message = 'New password and confirmation do not match.';
        $messageType = 'error';
    } else {
        try {
            $result = change_user_password($currentPassword, $newPassword);
        } catch (Throwable $e) {
            error_log('[auth.change_password] ' . $e->getMessage());
            $result = ['success' => false, 'message' => 'Authentication database is not initialized.'];
        }

        if ($result['success']) {
            logout_user();
            header('Location: /login.php?message=' . urlencode((string)$result['message']));
            exit;
        }

        $message = (string)$result['message'];
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Gray Mentality</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        .auth-container {
            max-width: 420px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(13, 17, 26, 0.9), rgba(8, 10, 15, 0.9));
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group label {
            font-size: 0.9rem;
            color: var(--text);
        }
        .form-group input {
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
        }
        .auth-submit {
            padding: 12px;
            border: none;
            background: var(--accent);
            color: #000;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }
        .message {
            padding: 10px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
        }
        .error { background: rgba(255, 0, 0, 0.1); color: #ff6b6b; }
        .success { background: rgba(0, 255, 0, 0.1); color: #51cf66; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Change Password</h1>
        <p style="color: var(--muted); margin-top: 0;">
            Update the password for your current session. This will invalidate existing sessions and send you back to login.
        </p>

        <?php if ($message): ?>
            <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post">
            <div class="form-group">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="auth-submit">Update Password</button>
        </form>

        <p style="margin-top: 16px;">
            <a href="/modules/index.php">Back to dashboard</a>
        </p>
    </div>
</body>
</html>
