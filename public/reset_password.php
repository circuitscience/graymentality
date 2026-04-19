<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/auth_functions.php';

$message = '';
$messageType = '';
$resetUrl = '';
$showResetForm = false;
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($token !== '') {
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($password === '' || $confirmPassword === '') {
            $message = 'Please enter and confirm your new password.';
            $messageType = 'error';
            $showResetForm = true;
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
            $showResetForm = true;
        } else {
            try {
                $result = complete_password_reset($token, $password);
            } catch (Throwable $e) {
                error_log('[auth.reset] ' . $e->getMessage());
                $result = ['success' => false, 'message' => 'Authentication database is not initialized.'];
            }
            if ($result['success']) {
                header('Location: /login.php?message=' . urlencode((string)$result['message']));
                exit;
            }

            $message = (string)$result['message'];
            $messageType = 'error';
            $showResetForm = true;
        }
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        try {
            $result = request_password_reset($email);
        } catch (Throwable $e) {
            error_log('[auth.reset] ' . $e->getMessage());
            $result = ['success' => false, 'message' => 'Authentication database is not initialized.'];
        }
        $message = (string)$result['message'];
        $messageType = 'success';

        if (!empty($result['reset_url'])) {
            $resetUrl = (string)$result['reset_url'];
        }
    }
} elseif ($token !== '') {
    $reset = lookup_password_reset_token($token);
    if ($reset) {
        $showResetForm = true;
    } else {
        $message = 'Invalid or expired reset token.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Gray Mentality</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        .auth-container {
            max-width: 400px;
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
            line-height: 1.4;
        }
        .error { background: rgba(255, 0, 0, 0.1); color: #ff6b6b; }
        .success { background: rgba(0, 255, 0, 0.1); color: #51cf66; }
        .debug-link {
            margin-top: 14px;
            padding: 12px;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--line);
            word-break: break-word;
        }
        .helper-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-top: 12px;
        }
        .helper-links a {
            color: var(--accent);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Reset Password</h1>
        <p style="color: var(--muted); margin-top: 0;">
            Use your email to request a reset. If you already have a token link, you can set a new password immediately.
        </p>

        <?php if ($message): ?>
            <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($showResetForm): ?>
            <form class="auth-form" method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="auth-submit">Update Password</button>
            </form>
        <?php else: ?>
            <form class="auth-form" method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" class="auth-submit">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <?php if ($resetUrl !== ''): ?>
            <div class="debug-link">
                <strong>Development reset link</strong>
                <div><a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?></a></div>
            </div>
        <?php endif; ?>

        <div class="helper-links">
            <a href="/login.php">Back to login</a>
            <a href="/register.php">Register instead</a>
        </div>
    </div>
</body>
</html>
