<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/auth_functions.php';

$authUser = require_auth();
if ((int)($authUser['role_id'] ?? 0) !== 10) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden';
    exit;
}

$db = get_db_connection();

function gm_admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function gm_admin_redirect(string $section, string $message = ''): never
{
    $query = ['section' => $section];
    if ($message !== '') {
        $query['message'] = $message;
    }

    header('Location: /admin/index.php?' . http_build_query($query));
    exit;
}

function gm_admin_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
    }

    return (string)$_SESSION['admin_csrf'];
}

function gm_admin_require_csrf(): void
{
    $token = (string)($_POST['csrf'] ?? '');
    if ($token === '' || !hash_equals(gm_admin_csrf_token(), $token)) {
        http_response_code(400);
        exit('Bad request.');
    }
}

function gm_admin_ensure_tables(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS shorts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            text TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            asset_url VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS general_articles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            author VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            text TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            category ENUM('general','mentality','physical') NOT NULL DEFAULT 'general'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS passages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            depth ENUM('short','deep','long') NOT NULL DEFAULT 'deep',
            category VARCHAR(80) NOT NULL DEFAULT 'general',
            status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS passage_steps (
            id INT PRIMARY KEY AUTO_INCREMENT,
            passage_id INT NOT NULL,
            step_order INT NOT NULL,
            step_type ENUM('interrupt','recognition','tension','observation','expansion','release') NOT NULL,
            body TEXT NOT NULL,
            cta_label VARCHAR(80) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_passage_step (passage_id, step_order),
            CONSTRAINT fk_admin_passage_steps_passage
                FOREIGN KEY (passage_id) REFERENCES passages(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS user_messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            admin_id INT NULL,
            subject VARCHAR(180) NOT NULL DEFAULT 'Message',
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_unread (user_id, is_read, created_at),
            CONSTRAINT fk_admin_user_messages_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_admin_user_messages_admin
                FOREIGN KEY (admin_id) REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS admin_messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            admin_id INT NULL,
            user_message_id INT NULL,
            subject VARCHAR(180) NOT NULL DEFAULT 'User response',
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_admin_messages_created (created_at),
            KEY idx_admin_messages_user (user_id, created_at),
            CONSTRAINT fk_admin_admin_messages_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_admin_admin_messages_admin
                FOREIGN KEY (admin_id) REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function gm_admin_rows(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

gm_admin_ensure_tables($db);
$csrf = gm_admin_csrf_token();

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    gm_admin_require_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'save_user') {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim((string)($_POST['username'] ?? ''));
            $email = normalize_email((string)($_POST['email'] ?? ''));
            $first = trim((string)($_POST['first_name'] ?? ''));
            $last = trim((string)($_POST['last_name'] ?? ''));
            $roleId = (int)($_POST['role_id'] ?? 1);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $emailVerified = isset($_POST['email_verified']) ? 1 : 0;
            $password = (string)($_POST['password'] ?? '');

            if ($username === '' || $email === '') {
                throw new RuntimeException('Username and email are required.');
            }

            if ($id > 0) {
                $stmt = $db->prepare(
                    "UPDATE users
                     SET username = ?, email = ?, first_name = ?, last_name = ?, role_id = ?, is_active = ?, email_verified = ?
                     WHERE id = ?"
                );
                $stmt->execute([$username, $email, $first, $last, $roleId, $isActive, $emailVerified, $id]);

                if ($password !== '') {
                    $passStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $passStmt->execute([hash_password($password), $id]);
                }
            } else {
                if ($password === '') {
                    throw new RuntimeException('Password is required for new users.');
                }

                $stmt = $db->prepare(
                    "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active, email_verified)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$username, $email, hash_password($password), $first, $last, $roleId, $isActive, $emailVerified]);
            }

            gm_admin_redirect('users', 'User saved.');
        }

        if ($action === 'delete_user') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && $id !== (int)$authUser['id']) {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
            }
            gm_admin_redirect('users', 'User deleted.');
        }

        if ($action === 'save_short') {
            $id = (int)($_POST['id'] ?? 0);
            $data = [
                trim((string)($_POST['title'] ?? '')),
                trim((string)($_POST['text'] ?? '')),
                trim((string)($_POST['asset_url'] ?? '')) ?: null,
            ];
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE shorts SET title = ?, text = ?, asset_url = ? WHERE id = ?");
                $stmt->execute([...$data, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO shorts (title, text, asset_url) VALUES (?, ?, ?)");
                $stmt->execute($data);
            }
            gm_admin_redirect('shorts', 'Short saved.');
        }

        if ($action === 'delete_short') {
            $stmt = $db->prepare("DELETE FROM shorts WHERE id = ?");
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
            gm_admin_redirect('shorts', 'Short deleted.');
        }

        if ($action === 'save_article') {
            $id = (int)($_POST['id'] ?? 0);
            $category = (string)($_POST['category'] ?? 'general');
            if (!in_array($category, ['general', 'mentality', 'physical'], true)) {
                $category = 'general';
            }
            $data = [
                trim((string)($_POST['author'] ?? 'Gray')),
                trim((string)($_POST['title'] ?? '')),
                trim((string)($_POST['text'] ?? '')),
                $category,
            ];
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE general_articles SET author = ?, title = ?, text = ?, category = ? WHERE id = ?");
                $stmt->execute([...$data, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO general_articles (author, title, text, category) VALUES (?, ?, ?, ?)");
                $stmt->execute($data);
            }
            gm_admin_redirect('articles', 'Article saved.');
        }

        if ($action === 'delete_article') {
            $stmt = $db->prepare("DELETE FROM general_articles WHERE id = ?");
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
            gm_admin_redirect('articles', 'Article deleted.');
        }

        if ($action === 'save_passage') {
            $id = (int)($_POST['id'] ?? 0);
            $depth = (string)($_POST['depth'] ?? 'deep');
            $status = (string)($_POST['status'] ?? 'active');
            if (!in_array($depth, ['short', 'deep', 'long'], true)) {
                $depth = 'deep';
            }
            if (!in_array($status, ['draft', 'active', 'archived'], true)) {
                $status = 'active';
            }
            $data = [
                trim((string)($_POST['title'] ?? '')),
                trim((string)($_POST['slug'] ?? '')),
                $depth,
                trim((string)($_POST['category'] ?? 'general')),
                $status,
            ];
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE passages SET title = ?, slug = ?, depth = ?, category = ?, status = ? WHERE id = ?");
                $stmt->execute([...$data, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO passages (title, slug, depth, category, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute($data);
            }
            gm_admin_redirect('passages', 'Passage saved.');
        }

        if ($action === 'delete_passage') {
            $stmt = $db->prepare("DELETE FROM passages WHERE id = ?");
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
            gm_admin_redirect('passages', 'Passage deleted.');
        }

        if ($action === 'save_passage_step') {
            $id = (int)($_POST['id'] ?? 0);
            $passageId = (int)($_POST['passage_id'] ?? 0);
            $stepType = (string)($_POST['step_type'] ?? 'interrupt');
            if (!in_array($stepType, ['interrupt', 'recognition', 'tension', 'observation', 'expansion', 'release'], true)) {
                $stepType = 'interrupt';
            }
            $data = [
                $passageId,
                max(1, (int)($_POST['step_order'] ?? 1)),
                $stepType,
                trim((string)($_POST['body'] ?? '')),
                trim((string)($_POST['cta_label'] ?? 'Continue')) ?: null,
            ];
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE passage_steps SET passage_id = ?, step_order = ?, step_type = ?, body = ?, cta_label = ? WHERE id = ?");
                $stmt->execute([...$data, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO passage_steps (passage_id, step_order, step_type, body, cta_label) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute($data);
            }
            gm_admin_redirect('passages', 'Passage step saved.');
        }

        if ($action === 'delete_passage_step') {
            $stmt = $db->prepare("DELETE FROM passage_steps WHERE id = ?");
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
            gm_admin_redirect('passages', 'Passage step deleted.');
        }

        if ($action === 'send_user_message') {
            $stmt = $db->prepare(
                "INSERT INTO user_messages (user_id, admin_id, subject, body)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                (int)($_POST['user_id'] ?? 0),
                (int)$authUser['id'],
                trim((string)($_POST['subject'] ?? 'Message')),
                trim((string)($_POST['body'] ?? '')),
            ]);
            gm_admin_redirect('messages', 'Message sent.');
        }
    } catch (Throwable $e) {
        gm_admin_redirect((string)($_POST['section'] ?? 'users'), $e->getMessage());
    }
}

$section = (string)($_GET['section'] ?? 'users');
if (!in_array($section, ['users', 'shorts', 'articles', 'passages', 'messages'], true)) {
    $section = 'users';
}

$notice = trim((string)($_GET['message'] ?? ''));
$users = gm_admin_rows($db, "SELECT id, username, email, first_name, last_name, role_id, is_active, email_verified, created_at FROM users ORDER BY id DESC LIMIT 100");
$shorts = gm_admin_rows($db, "SELECT id, title, text, asset_url, created_at FROM shorts ORDER BY id DESC LIMIT 50");
$articles = gm_admin_rows($db, "SELECT id, author, title, text, category, created_at FROM general_articles ORDER BY id DESC LIMIT 50");
$passages = gm_admin_rows($db, "SELECT id, title, slug, depth, category, status FROM passages ORDER BY id DESC LIMIT 50");
$steps = gm_admin_rows($db, "SELECT id, passage_id, step_order, step_type, body, cta_label FROM passage_steps ORDER BY passage_id DESC, step_order ASC");
$replies = gm_admin_rows(
    $db,
    "SELECT am.id, am.subject, am.body, am.created_at, u.username, u.email
     FROM admin_messages am
     JOIN users u ON u.id = am.user_id
     ORDER BY am.created_at DESC
     LIMIT 80"
);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Gray Mentality</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        .admin-shell { width: min(1280px, calc(100vw - 28px)); margin: 0 auto; padding: 24px 0 56px; }
        .admin-nav { display: flex; flex-wrap: wrap; gap: 8px; margin: 20px 0; }
        .admin-nav a, .admin-button {
            display: inline-flex; align-items: center; justify-content: center; min-height: 36px;
            border: 1px solid var(--line); background: rgba(255,255,255,.04); padding: 0 12px;
            color: var(--text); font-size: .74rem; font-weight: 900; letter-spacing: .1em; text-transform: uppercase;
        }
        .admin-nav a[aria-current="true"], .admin-button-primary { border-color: var(--accent); background: var(--accent); color: #090807; }
        .admin-panel { border: 1px solid var(--line); background: rgba(14,16,22,.92); padding: clamp(18px, 3vw, 30px); margin-top: 16px; }
        .admin-grid { display: grid; grid-template-columns: minmax(260px, .42fr) minmax(0, .58fr); gap: 16px; }
        .admin-card { border: 1px solid var(--line); background: rgba(255,255,255,.035); padding: 14px; }
        .admin-card + .admin-card { margin-top: 10px; }
        .admin-card h3 { margin: 0 0 8px; }
        .admin-form { display: grid; gap: 10px; }
        .admin-form label { display: grid; gap: 6px; color: var(--muted); font-size: .78rem; font-weight: 800; text-transform: uppercase; }
        .admin-form input, .admin-form select, .admin-form textarea {
            width: 100%; border: 1px solid var(--line); background: rgba(255,255,255,.045); color: var(--text); padding: 10px;
        }
        .admin-form textarea { min-height: 120px; resize: vertical; }
        .admin-form-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .admin-meta { color: var(--soft); font-size: .82rem; line-height: 1.45; }
        .admin-notice { border: 1px solid rgba(255,108,20,.46); padding: 12px; color: var(--text); background: rgba(255,108,20,.08); }
        @media (max-width: 900px) { .admin-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <main class="admin-shell">
        <header class="topbar">
            <a class="brand" href="/modules/index.php">
                <img class="site-logo" src="<?= gm_logo_url() ?>" alt="" aria-hidden="true">
                <span class="brand-name">Gray Mentality Admin</span>
            </a>
            <nav class="nav-pills"><a class="pill" href="/modules/index.php">Dashboard</a><a class="pill" href="/logout.php">Logout</a></nav>
        </header>

        <nav class="admin-nav" aria-label="Admin sections">
            <?php foreach (['users' => 'Users', 'shorts' => 'Shorts', 'articles' => 'Articles', 'passages' => 'Passages', 'messages' => 'Messages'] as $key => $label): ?>
                <a href="?section=<?= gm_admin_h($key) ?>" aria-current="<?= $section === $key ? 'true' : 'false' ?>"><?= gm_admin_h($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($notice !== ''): ?><p class="admin-notice"><?= gm_admin_h($notice) ?></p><?php endif; ?>

        <?php if ($section === 'users'): ?>
            <section class="admin-panel admin-grid">
                <form class="admin-form" method="post">
                    <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="section" value="users">
                    <h2>Create User</h2>
                    <label>Username <input name="username" required></label>
                    <label>Email <input name="email" type="email" required></label>
                    <label>Password <input name="password" type="password" required></label>
                    <label>First name <input name="first_name"></label>
                    <label>Last name <input name="last_name"></label>
                    <label>Role <input name="role_id" type="number" value="1"></label>
                    <label><span><input name="is_active" type="checkbox" checked> Active</span></label>
                    <label><span><input name="email_verified" type="checkbox" checked> Email verified</span></label>
                    <button class="admin-button admin-button-primary" type="submit">Create</button>
                </form>
                <div>
                    <?php foreach ($users as $row): ?>
                        <article class="admin-card">
                            <form class="admin-form" method="post">
                                <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>">
                                <input type="hidden" name="action" value="save_user">
                                <input type="hidden" name="section" value="users">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <h3>#<?= (int)$row['id'] ?> <?= gm_admin_h((string)$row['username']) ?></h3>
                                <label>Username <input name="username" value="<?= gm_admin_h((string)$row['username']) ?>"></label>
                                <label>Email <input name="email" value="<?= gm_admin_h((string)$row['email']) ?>"></label>
                                <label>Password <input name="password" type="password" placeholder="Leave blank to keep current"></label>
                                <label>First <input name="first_name" value="<?= gm_admin_h((string)($row['first_name'] ?? '')) ?>"></label>
                                <label>Last <input name="last_name" value="<?= gm_admin_h((string)($row['last_name'] ?? '')) ?>"></label>
                                <label>Role <input name="role_id" type="number" value="<?= (int)$row['role_id'] ?>"></label>
                                <label><span><input name="is_active" type="checkbox" <?= (int)$row['is_active'] === 1 ? 'checked' : '' ?>> Active</span></label>
                                <label><span><input name="email_verified" type="checkbox" <?= (int)$row['email_verified'] === 1 ? 'checked' : '' ?>> Email verified</span></label>
                                <div class="admin-form-row">
                                    <button class="admin-button admin-button-primary" type="submit">Save</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="section" value="users">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button class="admin-button" type="submit">Delete</button>
                            </form>
                                </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($section === 'shorts' || $section === 'articles'): ?>
            <?php $isArticles = $section === 'articles'; $rows = $isArticles ? $articles : $shorts; ?>
            <section class="admin-panel">
                <h2><?= $isArticles ? 'General Articles' : 'Shorts' ?></h2>
                <article class="admin-card">
                    <form class="admin-form" method="post">
                        <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>">
                        <input type="hidden" name="section" value="<?= gm_admin_h($section) ?>">
                        <input type="hidden" name="action" value="<?= $isArticles ? 'save_article' : 'save_short' ?>">
                        <?php if ($isArticles): ?><label>Author <input name="author" value="Gray"></label><?php endif; ?>
                        <label>Title <input name="title" required></label>
                        <?php if ($isArticles): ?><label>Category <select name="category"><option>general</option><option>mentality</option><option>physical</option></select></label><?php else: ?><label>Asset URL <input name="asset_url"></label><?php endif; ?>
                        <label>Text <textarea name="text" required></textarea></label>
                        <button class="admin-button admin-button-primary" type="submit">Create</button>
                    </form>
                </article>
                <?php foreach ($rows as $row): ?>
                    <article class="admin-card">
                        <form class="admin-form" method="post">
                            <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>">
                            <input type="hidden" name="section" value="<?= gm_admin_h($section) ?>">
                            <input type="hidden" name="action" value="<?= $isArticles ? 'save_article' : 'save_short' ?>">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <?php if ($isArticles): ?><label>Author <input name="author" value="<?= gm_admin_h((string)$row['author']) ?>"></label><?php endif; ?>
                            <label>Title <input name="title" value="<?= gm_admin_h((string)$row['title']) ?>"></label>
                            <?php if ($isArticles): ?><label>Category <select name="category"><?php foreach (['general','mentality','physical'] as $cat): ?><option <?= $row['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option><?php endforeach; ?></select></label><?php else: ?><label>Asset URL <input name="asset_url" value="<?= gm_admin_h((string)($row['asset_url'] ?? '')) ?>"></label><?php endif; ?>
                            <label>Text <textarea name="text"><?= gm_admin_h((string)$row['text']) ?></textarea></label>
                            <div class="admin-form-row"><button class="admin-button admin-button-primary" type="submit">Save</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Delete this item?')">
                            <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>">
                            <input type="hidden" name="section" value="<?= gm_admin_h($section) ?>">
                            <input type="hidden" name="action" value="<?= $isArticles ? 'delete_article' : 'delete_short' ?>">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button class="admin-button" type="submit">Delete</button>
                        </form></div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($section === 'passages'): ?>
            <section class="admin-panel">
                <h2>Passages</h2>
                <article class="admin-card">
                    <form class="admin-form" method="post">
                        <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>"><input type="hidden" name="section" value="passages"><input type="hidden" name="action" value="save_passage">
                        <label>Title <input name="title" required></label><label>Slug <input name="slug" required></label>
                        <label>Depth <select name="depth"><option>short</option><option selected>deep</option><option>long</option></select></label>
                        <label>Category <input name="category" value="general"></label><label>Status <select name="status"><option>draft</option><option selected>active</option><option>archived</option></select></label>
                        <button class="admin-button admin-button-primary" type="submit">Create Passage</button>
                    </form>
                </article>
                <?php foreach ($passages as $passage): ?>
                    <article class="admin-card">
                        <form class="admin-form" method="post">
                            <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>"><input type="hidden" name="section" value="passages"><input type="hidden" name="action" value="save_passage"><input type="hidden" name="id" value="<?= (int)$passage['id'] ?>">
                            <h3>#<?= (int)$passage['id'] ?> <?= gm_admin_h((string)$passage['title']) ?></h3>
                            <label>Title <input name="title" value="<?= gm_admin_h((string)$passage['title']) ?>"></label><label>Slug <input name="slug" value="<?= gm_admin_h((string)$passage['slug']) ?>"></label>
                            <label>Depth <select name="depth"><?php foreach (['short','deep','long'] as $d): ?><option <?= $passage['depth'] === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></label>
                            <label>Category <input name="category" value="<?= gm_admin_h((string)$passage['category']) ?>"></label><label>Status <select name="status"><?php foreach (['draft','active','archived'] as $s): ?><option <?= $passage['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></label>
                            <button class="admin-button admin-button-primary" type="submit">Save Passage</button>
                        </form>
                        <?php foreach ($steps as $step): if ((int)$step['passage_id'] !== (int)$passage['id']) continue; ?>
                            <form class="admin-form admin-card" method="post">
                                <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>"><input type="hidden" name="section" value="passages"><input type="hidden" name="action" value="save_passage_step"><input type="hidden" name="id" value="<?= (int)$step['id'] ?>"><input type="hidden" name="passage_id" value="<?= (int)$passage['id'] ?>">
                                <label>Order <input name="step_order" type="number" value="<?= (int)$step['step_order'] ?>"></label><label>Type <select name="step_type"><?php foreach (['interrupt','recognition','tension','observation','expansion','release'] as $t): ?><option <?= $step['step_type'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
                                <label>Body <textarea name="body"><?= gm_admin_h((string)$step['body']) ?></textarea></label><label>CTA <input name="cta_label" value="<?= gm_admin_h((string)($step['cta_label'] ?? '')) ?>"></label>
                                <button class="admin-button admin-button-primary" type="submit">Save Step</button>
                            </form>
                        <?php endforeach; ?>
                        <form class="admin-form admin-card" method="post">
                            <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>"><input type="hidden" name="section" value="passages"><input type="hidden" name="action" value="save_passage_step"><input type="hidden" name="passage_id" value="<?= (int)$passage['id'] ?>">
                            <h3>Add Step</h3><label>Order <input name="step_order" type="number" value="1"></label><label>Type <select name="step_type"><option>interrupt</option><option>recognition</option><option>tension</option><option>observation</option><option>expansion</option><option>release</option></select></label><label>Body <textarea name="body"></textarea></label><label>CTA <input name="cta_label" value="Continue"></label><button class="admin-button admin-button-primary" type="submit">Add Step</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($section === 'messages'): ?>
            <section class="admin-panel admin-grid">
                <form class="admin-form" method="post">
                    <input type="hidden" name="csrf" value="<?= gm_admin_h($csrf) ?>"><input type="hidden" name="section" value="messages"><input type="hidden" name="action" value="send_user_message">
                    <h2>Send User Message</h2>
                    <label>User <select name="user_id"><?php foreach ($users as $userRow): ?><option value="<?= (int)$userRow['id'] ?>">#<?= (int)$userRow['id'] ?> <?= gm_admin_h((string)$userRow['email']) ?></option><?php endforeach; ?></select></label>
                    <label>Subject <input name="subject" value="Message"></label><label>Message <textarea name="body" required></textarea></label>
                    <button class="admin-button admin-button-primary" type="submit">Send</button>
                </form>
                <div>
                    <h2>User Replies</h2>
                    <?php foreach ($replies as $reply): ?><article class="admin-card"><h3><?= gm_admin_h((string)$reply['subject']) ?></h3><p class="admin-meta"><?= gm_admin_h((string)$reply['email']) ?> / <?= gm_admin_h((string)$reply['created_at']) ?></p><p><?= nl2br(gm_admin_h((string)$reply['body'])) ?></p></article><?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
