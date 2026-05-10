<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/auth_functions.php';

$message = trim((string)($_GET['message'] ?? ''));
$messageType = $message !== '' ? 'success' : '';
$next = auth_safe_next_path(isset($_GET['next']) ? (string)$_GET['next'] : null, '/modules/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $next = auth_safe_next_path(isset($_POST['next']) ? (string)$_POST['next'] : $next, '/modules/index.php');

    try {
        $result = login_user($email, $password);
    } catch (Throwable $e) {
        error_log('[auth.login] ' . $e->getMessage());
        $result = ['success' => false, 'message' => 'Authentication database is not initialized.'];
    }
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';

    if ($result['success']) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $target = $userId > 0 && !auth_profile_is_complete($userId)
            ? auth_profile_setup_url()
            : $next;

        header('Location: ' . $target);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gray Mentality</title>
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
        .auth-container h1 {
            margin: 0 0 18px;
            font-size: clamp(2rem, 12vw, 3rem);
            line-height: 1;
            overflow-wrap: normal;
            word-break: keep-all;
        }
        .auth-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }
        .auth-title h1 {
            margin: 0;
        }
        .auth-title-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
            flex: 0 0 auto;
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
        .auth-links {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: -4px;
        }
        .auth-links a {
            color: var(--accent);
            text-decoration: none;
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
        <div class="auth-title">
            <img class="auth-title-logo" src="<?= gm_logo_url() ?>" alt="" aria-hidden="true">
            <h1>Login</h1>
        </div>
        <?php if ($message): ?>
            <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <form class="auth-form" method="post">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="auth-links">
                <a href="/reset_password.php">Forgot password?</a>
                <a href="/register.php">Need an account? Register</a>
            </div>
            <button type="submit" class="auth-submit">Login</button>
        </form>
        <p><a href="/index.php">Back to Home</a></p>
    </div>
    <script>
        try {
            localStorage.removeItem('gm.session.logout');
            localStorage.removeItem('gm.session.lastActivity');
        } catch (error) {
            // Ignore browsers that block storage access.
        }
    </script>
</body>
</html>
