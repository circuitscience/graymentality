<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../auth_functions.php';

$authUser = require_auth();
$db = get_db_connection();

function gm_dashboard_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function gm_dashboard_user(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.last_login, u.logins,
                COALESCE(r.name, 'user') AS role_name,
                p.date_of_birth, p.gender, p.timezone
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         LEFT JOIN user_profiles p ON p.user_id = u.id
         WHERE u.id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : [];
}

function gm_dashboard_sql_identifier(string $value): string
{
    return '`' . str_replace('`', '``', $value) . '`';
}

function gm_dashboard_xfit_result(string $status, string $label): array
{
    return [
        'status' => $status,
        'label' => $label,
    ];
}

function gm_dashboard_xfit_connection(): ?PDO
{
    $host = trim((string)auth_env('XFIT_DB_HOST', ''));
    if ($host === '') {
        return null;
    }

    $dbname = trim((string)auth_env('XFIT_DB_NAME', 'jerrybil_xfit'));
    $user = trim((string)auth_env('XFIT_DB_USER', (string)auth_env('DB_USER', '')));
    $pass = (string)auth_env('XFIT_DB_PASS', (string)auth_env('DB_PASS', ''));
    $port = trim((string)auth_env('XFIT_DB_PORT', '3306'));
    $charset = trim((string)auth_env('XFIT_DB_CHARSET', (string)auth_env('DB_CHARSET', 'utf8mb4')));

    if ($dbname === '' || $user === '') {
        return null;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        error_log('[dashboard.xfit_db_connect] ' . $e->getMessage());
        return null;
    }
}

function gm_dashboard_xfit_user_number_from_db(PDO $db, string $xfitDb, string $email): array
{
    if ($xfitDb === '') {
        return gm_dashboard_xfit_result('unavailable', 'xFit info unavailable');
    }

    try {
        $tableStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'users'"
        );
        $tableStmt->execute([$xfitDb]);
        if ((int)$tableStmt->fetchColumn() === 0) {
            return gm_dashboard_xfit_result('unavailable', 'xFit info unavailable');
        }

        $columnStmt = $db->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'users'"
        );
        $columnStmt->execute([$xfitDb]);
        $columns = array_map('strval', $columnStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($columns === []) {
            return gm_dashboard_xfit_result('unavailable', 'xFit info unavailable');
        }

        $emailColumn = null;
        foreach (['email', 'user_email'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $emailColumn = $candidate;
                break;
            }
        }

        $numberColumn = null;
        foreach (['user_id', 'exfit_user_number', 'xfit_user_number', 'user_number', 'member_number', 'id'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $numberColumn = $candidate;
                break;
            }
        }

        if ($emailColumn === null || $numberColumn === null) {
            return gm_dashboard_xfit_result('unavailable', 'xFit info unavailable');
        }

        $dbName = gm_dashboard_sql_identifier($xfitDb);
        $emailField = gm_dashboard_sql_identifier($emailColumn);
        $numberField = gm_dashboard_sql_identifier($numberColumn);
        $stmt = $db->prepare(
            "SELECT {$numberField}
             FROM {$dbName}.`users`
             WHERE LOWER({$emailField}) = ?
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $value = $stmt->fetchColumn();

        $value = trim((string)($value !== false ? $value : ''));
        return $value !== ''
            ? gm_dashboard_xfit_result('member', $value)
            : gm_dashboard_xfit_result('not_member', 'Not a Member');
    } catch (Throwable $e) {
        error_log('[dashboard.xfit_user_lookup] ' . $e->getMessage());
        return gm_dashboard_xfit_result('unavailable', 'xFit info unavailable');
    }
}

function gm_dashboard_xfit_user_number(PDO $db, ?string $email): array
{
    $email = strtolower(trim((string)$email));
    if ($email === '') {
        return gm_dashboard_xfit_result('not_member', 'Not a Member');
    }

    $xfitDb = trim((string)auth_env('XFIT_DB_NAME', 'jerrybil_xfit'));
    $xfitConnection = gm_dashboard_xfit_connection();
    if ($xfitConnection instanceof PDO) {
        return gm_dashboard_xfit_user_number_from_db($xfitConnection, $xfitDb, $email);
    }

    return gm_dashboard_xfit_user_number_from_db($db, $xfitDb, $email);
}

function gm_dashboard_age(?string $dateOfBirth): ?int
{
    if (!$dateOfBirth) {
        return null;
    }

    try {
        return (int)(new DateTimeImmutable($dateOfBirth))->diff(new DateTimeImmutable('today'))->y;
    } catch (Throwable $e) {
        return null;
    }
}

function gm_dashboard_format_last_login(?string $value): string
{
    if (!$value) {
        return 'First session';
    }

    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('M j, Y g:ia');
    } catch (Throwable $e) {
        return $value;
    }
}

function gm_dashboard_featured_article(PDO $db): ?array
{
    try {
        $columnsStmt = $db->query("SHOW COLUMNS FROM general_articles");
        $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $columns = array_map('strval', is_array($columns) ? $columns : []);

        if ($columns === []) {
            return null;
        }

        $dateColumn = in_array('date', $columns, true) ? '`date`' : (in_array('created_at', $columns, true) ? 'created_at' : 'NULL');
        $imageColumn = in_array('image_url', $columns, true)
            ? 'image_url'
            : (in_array('image', $columns, true) ? 'image' : 'NULL');
        $categoryColumn = in_array('category', $columns, true) ? 'category' : "'General'";
        $idColumn = in_array('id', $columns, true) ? 'id' : 'NULL';

        $stmt = $db->query(
            "SELECT {$idColumn} AS id, {$dateColumn} AS `date`, author, title, `text`, {$imageColumn} AS image, {$categoryColumn} AS category
             FROM general_articles
             WHERE COALESCE(title, '') <> '' AND COALESCE(`text`, '') <> ''
             ORDER BY RAND()
             LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

function gm_dashboard_article_excerpt(?string $text, int $maxLength = 260): string
{
    $plain = trim((string)$text);
    $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
    $plain = trim($plain);

    if ($plain === '') {
        return '';
    }

    $sentences = preg_split('/(?<=[.!?])\s+/', $plain, 4, PREG_SPLIT_NO_EMPTY);
    $excerpt = '';
    foreach ($sentences ?: [] as $sentence) {
        $candidate = trim($excerpt . ' ' . $sentence);
        if ($excerpt !== '' && strlen($candidate) > $maxLength) {
            break;
        }
        $excerpt = $candidate;
        if (count($sentences) > 1 && substr_count($excerpt, '.') + substr_count($excerpt, '!') + substr_count($excerpt, '?') >= 2) {
            break;
        }
    }

    if ($excerpt === '') {
        $excerpt = $plain;
    }

    if (strlen($excerpt) > $maxLength) {
        $excerpt = rtrim(substr($excerpt, 0, $maxLength - 1));
        $lastSpace = strrpos($excerpt, ' ');
        if ($lastSpace !== false && $lastSpace > 80) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }
        $excerpt .= '...';
    }

    return $excerpt;
}

function gm_dashboard_format_article_date(?string $value): string
{
    if (!$value) {
        return 'Article';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable $e) {
        return $value;
    }
}

function gm_dashboard_article_image(?string $value): string
{
    $image = trim((string)$value);
    if ($image === '') {
        return '';
    }

    if (preg_match('#^(https?://|/|data:)#i', $image)) {
        return gm_public_url($image);
    }

    $publicRoot = dirname(__DIR__);
    $candidates = [
        $image,
        'assets/images/' . $image,
        'modules/assets/' . $image,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($publicRoot . '/' . ltrim($candidate, '/'))) {
            return '/' . ltrim($candidate, '/');
        }
    }

    return '/' . ltrim($image, '/');
}

function gm_dashboard_article_ref(array $article): string
{
    if (isset($article['id']) && (string)$article['id'] !== '') {
        return 'id-' . (string)$article['id'];
    }

    return 'key-' . substr(hash('sha256', implode('|', [
        (string)($article['date'] ?? ''),
        (string)($article['author'] ?? ''),
        (string)($article['title'] ?? ''),
    ])), 0, 16);
}

$user = gm_dashboard_user($db, (int)$authUser['id']);
$displayName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($user['username'] ?? $authUser['username'] ?? 'member');
}

$firstName = trim((string)($user['first_name'] ?? ''));
if ($firstName === '') {
    $firstName = (string)($user['username'] ?? 'there');
}

$xfitUserNumber = gm_dashboard_xfit_user_number($db, isset($user['email']) ? (string)$user['email'] : null);
$age = gm_dashboard_age(isset($user['date_of_birth']) ? (string)$user['date_of_birth'] : null);
$profileBits = array_filter([
    $age !== null ? $age . ' years' : '',
    isset($user['gender']) ? str_replace('_', ' ', (string)$user['gender']) : '',
    isset($user['timezone']) ? (string)$user['timezone'] : '',
]);

$labs = [
    [
        'title' => 'BMR',
        'label' => 'Calculator',
        'copy' => 'Estimate baseline calories and maintenance needs.',
        'route' => '/modules/bmr/index.php',
        'asset' => '/modules/assets/bmr.png',
        'icon' => 'B',
    ],
    [
        'title' => 'Weight Trend',
        'label' => 'Log',
        'copy' => 'Track bodyweight, calorie direction, and trend movement.',
        'route' => '/modules/weight_loss/index.php',
        'asset' => '/modules/assets/weight_loss.png',
        'icon' => 'W',
    ],
    [
        'title' => 'Protein Intake',
        'label' => 'Calculator',
        'copy' => 'Set daily protein targets and keep intake accountable.',
        'route' => '/modules/protein_intake/index.php',
        'asset' => '/modules/assets/protein.png',
        'icon' => 'P',
    ],
    [
        'title' => 'Creatine',
        'label' => 'Guide',
        'copy' => 'Supplement guidance and intake history.',
        'route' => '/modules/creatine/index.php',
        'asset' => '/modules/assets/ai/creatine.png',
        'icon' => 'C',
        'icon_asset' => '/modules/assets/icons/creatine.svg',
    ],
    [
        'title' => 'Hydration',
        'label' => 'Log',
        'copy' => 'Record water intake and daily hydration habits.',
        'route' => '/modules/hydration/index.php',
        'asset' => '/modules/assets/hydration.png',
        'icon' => 'H',
    ],
    [
        'title' => 'Sleep',
        'label' => 'Log',
        'copy' => 'Track sleep consistency and recovery context.',
        'route' => '/modules/sleep/index.php',
        'asset' => '/modules/assets/recovery.png',
        'icon' => 'S',
    ],
    [
        'title' => 'Recovery',
        'label' => 'Workflow',
        'copy' => 'Capture recovery notes, prompts, and reset sessions.',
        'route' => '/modules/sleep_recovery/index.php',
        'asset' => '/modules/assets/recovery1.png',
        'icon' => 'R',
    ],
    [
        'title' => 'Frame Potential',
        'label' => 'Assessment',
        'copy' => 'Review build indicators, leverage, and structural context.',
        'route' => '/modules/frame_potential/index.php',
        'asset' => '/modules/assets/frame_potential.png',
        'icon' => 'F',
    ],
    [
        'title' => 'Muscle Growth',
        'label' => 'Log',
        'copy' => 'Track bodyweight and growth signals over time.',
        'route' => '/modules/muscle_growth/index.php',
        'asset' => '/modules/assets/muscle_growth.png',
        'icon' => 'M',
    ],
];

$xfitModules = [
    [
        'title' => 'Workout Day',
        'copy' => 'Plan and structure the training session.',
        'route' => '/modules/workout_day/index.php',
    ],
    [
        'title' => 'Grip Strength',
        'copy' => 'Test grip, holds, and carryover capacity.',
        'route' => '/modules/grip_strength/index.php',
    ],
    [
        'title' => 'Learning Hub',
        'copy' => 'Open the current reference library.',
        'route' => '/modules/Library/learning_hub.php',
    ],
];

$mentalityItems = [
    [
        'title' => 'Reality First',
        'copy' => 'A place for direct assessment: what is true, what is drifting, and what needs action.',
    ],
    [
        'title' => 'Discipline Notes',
        'copy' => 'Short-form content, prompts, and decisions that turn intent into repeatable behavior.',
    ],
    [
        'title' => 'Recovery With Purpose',
        'copy' => 'Mental reset content that supports rest without turning rest into escape.',
    ],
];

$currentDate = (new DateTimeImmutable('now', new DateTimeZone('America/Toronto')))->format('F j, Y');
$lastLogin = gm_dashboard_format_last_login(isset($user['last_login']) ? (string)$user['last_login'] : null);
$visitNumber = max(0, (int)($user['logins'] ?? 0));
$featuredArticle = gm_dashboard_featured_article($db);
$featuredArticleExcerpt = $featuredArticle ? gm_dashboard_article_excerpt(isset($featuredArticle['text']) ? (string)$featuredArticle['text'] : '') : '';
$featuredArticleImage = $featuredArticle ? gm_dashboard_article_image(isset($featuredArticle['image']) ? (string)$featuredArticle['image'] : '') : '';
$featuredArticleUrl = $featuredArticle ? '/modules/Library/index.php?article=' . rawurlencode(gm_dashboard_article_ref($featuredArticle)) : '/modules/Library/index.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Gray Mentality</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        :root {
            --dash-bg: #050507;
            --dash-panel: rgba(14, 16, 22, 0.92);
            --dash-panel-2: rgba(20, 22, 30, 0.82);
            --dash-line: rgba(255, 255, 255, 0.14);
            --dash-line-hot: rgba(255, 106, 0, 0.58);
            --dash-text: #f4f1ea;
            --dash-muted: #aaa39b;
            --dash-soft: #7c7771;
            --dash-orange: #ff6a00;
            --dash-purple: #9b5cff;
            --dash-width: 1280px;
        }

        html { color-scheme: dark; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--dash-text);
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                radial-gradient(circle at 14% 8%, rgba(155, 92, 255, 0.22), transparent 30%),
                radial-gradient(circle at 86% 10%, rgba(255, 106, 0, 0.18), transparent 27%),
                linear-gradient(180deg, #050507 0%, #101116 50%, #050507 100%);
            background-size: 72px 72px, 72px 72px, auto, auto, auto;
            overflow-x: hidden;
        }

        a { color: inherit; text-decoration: none; }

        .dashboard {
            width: min(var(--dash-width), calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 44px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 14px 0 28px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            display: block;
            width: 42px;
            height: 42px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .brand-copy {
            display: grid;
            gap: 4px;
        }

        .brand span,
        .eyebrow,
        .card-label {
            color: var(--dash-orange);
            font-size: 0.76rem;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .brand strong {
            font-size: 1rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .chip,
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            border: 1px solid var(--dash-line);
            padding: 0 14px;
            color: var(--dash-text);
            background: rgba(255, 255, 255, 0.04);
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .button-primary {
            border-color: var(--dash-orange);
            background: var(--dash-orange);
            color: #07080b;
        }

        .passage-link-signal {
            position: relative;
            overflow: hidden;
        }

        .passage-link-signal::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            z-index: 1;
            width: 6px;
            height: 6px;
            background: var(--dash-purple);
            box-shadow:
                0 0 10px rgba(155, 92, 255, 0.95),
                0 0 20px rgba(155, 92, 255, 0.58);
            transform: translate(-50%, -50%);
            animation: passage-border-orbit 3s linear infinite;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(300px, 0.55fr);
            gap: 18px;
            align-items: stretch;
        }

        .panel {
            border: 1px solid var(--dash-line);
            background: var(--dash-panel);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
        }

        .hero-main {
            position: relative;
            min-height: 360px;
            padding: clamp(28px, 5vw, 56px);
            overflow: hidden;
        }

        .hero-main::after {
            content: "";
            position: absolute;
            right: clamp(18px, 4vw, 54px);
            bottom: clamp(18px, 4vw, 44px);
            width: min(300px, 38vw);
            aspect-ratio: 1;
            border: 1px solid rgba(255, 106, 0, 0.42);
            background:
                linear-gradient(135deg, rgba(255, 106, 0, 0.2), transparent 42%),
                linear-gradient(315deg, rgba(155, 92, 255, 0.2), transparent 46%),
                rgba(255, 255, 255, 0.035);
            clip-path: polygon(14% 0, 100% 0, 86% 100%, 0 100%);
            opacity: 0.78;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 760px;
        }

        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        h1 {
            margin-bottom: 18px;
            font-size: clamp(3rem, 9vw, 7.6rem);
            line-height: 0.88;
            letter-spacing: 0;
            text-transform: uppercase;
            overflow-wrap: normal;
        }

        .lead {
            max-width: 680px;
            color: var(--dash-muted);
            font-size: clamp(1rem, 1.6vw, 1.22rem);
            line-height: 1.65;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 26px;
        }

        .hero-side {
            padding: 24px;
            display: grid;
            gap: 14px;
            align-content: start;
        }

        .stat {
            border: 1px solid var(--dash-line);
            background: rgba(255, 255, 255, 0.04);
            padding: 14px;
        }

        .stat strong {
            display: block;
            margin-bottom: 6px;
            color: var(--dash-text);
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .stat span {
            color: var(--dash-muted);
            font-size: 0.92rem;
            line-height: 1.45;
        }

        .stat-value-unavailable {
            color: var(--dash-soft) !important;
        }

        .section {
            margin-top: 22px;
            padding: clamp(22px, 4vw, 34px);
        }

        .article-card {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
            gap: clamp(18px, 3vw, 34px);
            align-items: stretch;
            margin-top: 22px;
            overflow: hidden;
        }

        .article-media {
            min-height: 240px;
            background:
                linear-gradient(135deg, rgba(255, 106, 0, 0.18), transparent 42%),
                linear-gradient(315deg, rgba(155, 92, 255, 0.16), transparent 48%),
                rgba(255, 255, 255, 0.04);
        }

        .article-media img {
            display: block;
            width: 100%;
            height: 100%;
            min-height: 240px;
            object-fit: cover;
        }

        .article-body {
            padding: clamp(22px, 4vw, 38px);
            align-self: center;
        }

        .article-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
            color: var(--dash-soft);
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .article-card h2 {
            margin-bottom: 12px;
            font-size: clamp(1.8rem, 4vw, 3.8rem);
            line-height: 0.95;
            text-transform: uppercase;
        }

        .article-card p {
            max-width: 760px;
            margin-bottom: 0;
            color: var(--dash-muted);
            line-height: 1.6;
        }

        .article-actions {
            margin-top: 20px;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 18px;
            margin-bottom: 22px;
        }

        .section-head h2 {
            margin-bottom: 8px;
            font-size: clamp(1.8rem, 4vw, 3.6rem);
            line-height: 0.95;
            text-transform: uppercase;
        }

        .section-head p {
            margin-bottom: 0;
            max-width: 680px;
            color: var(--dash-muted);
            line-height: 1.55;
        }

        .labs-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .lab-card,
        .mental-card,
        .xfit-link {
            position: relative;
            min-height: 188px;
            border: 1px solid var(--dash-line);
            background: var(--dash-panel-2);
            overflow: hidden;
        }

        .lab-card {
            display: grid;
            align-content: end;
            padding: 16px;
        }

        .lab-card-icon {
            position: absolute;
            top: 16px;
            left: 16px;
            z-index: 1;
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(5, 5, 7, 0.68);
            color: var(--dash-text);
            font-size: 0.9rem;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
        }

        .lab-card-icon img {
            display: block;
            width: 24px;
            height: 24px;
        }

        .lab-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: var(--card-image);
            background-size: cover;
            background-position: center;
            opacity: 0.28;
            filter: grayscale(0.35) contrast(1.1);
            transition: transform 180ms ease, opacity 180ms ease;
        }

        .lab-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 0%, rgba(5, 5, 7, 0.9) 72%);
        }

        .lab-card:hover::before {
            transform: scale(1.04);
            opacity: 0.42;
        }

        .lab-card-content {
            position: relative;
            z-index: 1;
        }

        .lab-card h3,
        .mental-card h3,
        .xfit-link h3 {
            margin: 8px 0 8px;
            font-size: 1.35rem;
            line-height: 1.05;
            text-transform: uppercase;
        }

        .lab-card p,
        .mental-card p,
        .xfit-link p {
            margin-bottom: 0;
            color: var(--dash-muted);
            line-height: 1.48;
        }

        .split {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 18px;
        }

        .xfit-panel {
            min-height: 430px;
            padding: clamp(22px, 4vw, 38px);
            background:
                linear-gradient(135deg, rgba(255, 106, 0, 0.14), transparent 42%),
                linear-gradient(315deg, rgba(155, 92, 255, 0.12), transparent 48%),
                var(--dash-panel);
        }

        .xfit-panel h2,
        .mentality-panel h2 {
            font-size: clamp(2rem, 5vw, 4.8rem);
            line-height: 0.9;
            text-transform: uppercase;
        }

        .xfit-panel p,
        .mentality-panel > p {
            color: var(--dash-muted);
            line-height: 1.62;
        }

        .xfit-links {
            display: grid;
            gap: 10px;
            margin-top: 24px;
        }

        .xfit-link {
            display: block;
            min-height: 0;
            padding: 16px;
        }

        .mental-grid {
            display: grid;
            gap: 12px;
            margin-top: 22px;
        }

        .passage-invite {
            position: relative;
            display: grid;
            gap: 18px;
            margin-top: 24px;
            padding: clamp(18px, 3vw, 28px);
            border: 1px solid rgba(255, 106, 0, 0.46);
            background:
                linear-gradient(135deg, rgba(255, 106, 0, 0.12), transparent 42%),
                linear-gradient(315deg, rgba(155, 92, 255, 0.10), transparent 48%),
                rgba(255, 255, 255, 0.035);
            overflow: hidden;
        }

        .passage-invite::after {
            content: "";
            position: absolute;
            right: -34px;
            bottom: -34px;
            width: 116px;
            height: 116px;
            border: 1px solid rgba(155, 92, 255, 0.28);
            transform: rotate(45deg);
        }

        .passage-invite::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            z-index: 1;
            width: 7px;
            height: 7px;
            background: var(--dash-purple);
            box-shadow:
                0 0 10px rgba(155, 92, 255, 0.95),
                0 0 22px rgba(155, 92, 255, 0.62);
            transform: translate(-50%, -50%);
            animation: passage-border-orbit 3s linear infinite;
        }

        .passage-invite h3 {
            margin: 0;
            max-width: 14ch;
            font-size: clamp(1.55rem, 3vw, 2.8rem);
            line-height: 0.96;
            text-transform: uppercase;
        }

        .passage-invite p {
            max-width: 520px;
            margin: 0;
            color: var(--dash-muted);
            line-height: 1.55;
        }

        .passage-depths {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @keyframes passage-border-orbit {
            0% {
                left: 0;
                top: 0;
            }

            25% {
                left: 100%;
                top: 0;
            }

            50% {
                left: 100%;
                top: 100%;
            }

            75% {
                left: 0;
                top: 100%;
            }

            100% {
                left: 0;
                top: 0;
            }
        }

        .mental-card {
            min-height: 0;
            padding: 18px;
        }

        .mental-card:nth-child(2) {
            border-color: rgba(155, 92, 255, 0.42);
        }

        .mental-card:nth-child(3) {
            border-color: rgba(255, 106, 0, 0.42);
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding: 26px 0 0;
            color: var(--dash-soft);
            font-size: 0.86rem;
        }

        @media (max-width: 980px) {
            .hero,
            .article-card,
            .split {
                grid-template-columns: 1fr;
            }

            .labs-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .dashboard {
                width: min(100% - 22px, var(--dash-width));
                padding-top: 16px;
            }

            .topbar,
            .section-head {
                align-items: stretch;
                flex-direction: column;
            }

            .top-actions {
                justify-content: flex-start;
            }

            .hero-main {
                min-height: 0;
            }

            .hero-main::after {
                width: 180px;
                opacity: 0.36;
            }

            h1 {
                font-size: clamp(2.45rem, 16vw, 4.2rem);
            }

            .labs-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .passage-invite::before,
            .passage-link-signal::before {
                animation: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <header class="topbar">
            <a class="brand" href="/modules/index.php" aria-label="Gray Mentality dashboard">
                <img class="brand-logo" src="<?= gm_logo_url() ?>" alt="" aria-hidden="true">
                <span class="brand-copy">
                    <span>Gray Mentality</span>
                    <strong>Dashboard</strong>
                </span>
            </a>
            <nav class="top-actions" aria-label="Dashboard actions">
                <a class="chip" href="#labs">Labs</a>
                <a class="chip" href="/modules/Library/index.php">Library</a>
                <a class="chip" href="#xfit">xFit</a>
                <a class="chip" href="#mentality">Mentality</a>
                <a class="chip" href="/profile-setup">Profile</a>
                <a class="chip" href="/logout.php">Logout</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <div class="hero-main panel">
                    <div class="hero-content">
                        <p class="eyebrow"><?= gm_dashboard_h($currentDate) ?></p>
                        <h1>Welcome, <?= gm_dashboard_h($firstName) ?></h1>
                        <p class="lead">
                            This is the operating surface: body-composition labs, xFit access, and the mental side of Gray Mentality.
                            Start with the area that matters today.
                        </p>
                        <div class="hero-actions">
                            <a class="button button-primary" href="#labs">Open Labs</a>
                            <a class="button" href="#xfit">Enter xFit</a>
                            <a class="button passage-link-signal" href="/modules/passages/index.php">Today&apos;s Passage</a>
                        </div>
                    </div>
                </div>

                <aside class="hero-side panel">
                    <div class="stat">
                        <strong>Member</strong>
                        <span><?= gm_dashboard_h($displayName) ?></span>
                        <span><?= gm_dashboard_h((string)($user['email'] ?? '')) ?></span>
                    </div>
                    <div class="stat">
                        <strong>Exfit User Number</strong>
                        <span class="<?= ($xfitUserNumber['status'] ?? '') === 'unavailable' ? 'stat-value-unavailable' : '' ?>">
                            <?= gm_dashboard_h((string)($xfitUserNumber['label'] ?? 'xFit info unavailable')) ?>
                        </span>
                    </div>
                    <div class="stat">
                        <strong>Profile</strong>
                        <span><?= gm_dashboard_h($profileBits ? implode(' / ', $profileBits) : 'Profile initialized') ?></span>
                    </div>
                    <div class="stat">
                        <strong>Last login</strong>
                        <span><?= gm_dashboard_h($lastLogin) ?></span>
                        <span></br>Login number <?= gm_dashboard_h((string)$visitNumber) ?></span>
                    </div>
                    <div class="stat">
                        <strong>Sections</strong>
                        <span>Labs, xFit, and Mentality are separated so this portal can grow without becoming a mixed menu.</span>
                    </div>
                </aside>
            </section>

            <?php if ($featuredArticle): ?>
                <article class="article-card panel">
                    <div class="article-media">
                        <?php if ($featuredArticleImage !== ''): ?>
                            <img src="<?= gm_dashboard_h($featuredArticleImage) ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="article-body">
                        <p class="eyebrow">Featured Article</p>
                        <div class="article-meta">
                            <span><?= gm_dashboard_h(gm_dashboard_format_article_date(isset($featuredArticle['date']) ? (string)$featuredArticle['date'] : null)) ?></span>
                            <?php if (!empty($featuredArticle['author'])): ?>
                                <span><?= gm_dashboard_h((string)$featuredArticle['author']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h2><?= gm_dashboard_h((string)$featuredArticle['title']) ?></h2>
                        <?php if ($featuredArticleExcerpt !== ''): ?>
                            <p><?= gm_dashboard_h($featuredArticleExcerpt) ?></p>
                        <?php endif; ?>
                        <div class="article-actions">
                            <a class="button" href="<?= gm_dashboard_h($featuredArticleUrl) ?>">Read Article</a>
                        </div>
                    </div>
                </article>
            <?php endif; ?>

            <section class="section panel" id="labs">
                <div class="section-head">
                    <div>
                        <p class="eyebrow">Labs</p>
                        <h2>Body Composition</h2>
                        <p>
                            Calculators, trackers, and logs that deal with physical inputs: energy, weight, intake, recovery,
                            hydration, sleep, and measurable body-composition signals.
                        </p>
                    </div>
                    <a class="button" href="#top">Dashboard</a>
                </div>

                <div class="labs-grid">
                    <?php foreach ($labs as $lab): ?>
                        <a
                            class="lab-card"
                            href="<?= gm_dashboard_h($lab['route']) ?>"
                            style="--card-image: url('<?= gm_dashboard_h($lab['asset']) ?>');"
                        >
                            <span class="lab-card-icon" aria-hidden="true">
                                <?php if (isset($lab['icon_asset'])): ?>
                                    <img src="<?= gm_dashboard_h($lab['icon_asset']) ?>" alt="">
                                <?php else: ?>
                                    <?= gm_dashboard_h($lab['icon']) ?>
                                <?php endif; ?>
                            </span>
                            <div class="lab-card-content">
                                <span class="card-label"><?= gm_dashboard_h($lab['label']) ?></span>
                                <h3><?= gm_dashboard_h($lab['title']) ?></h3>
                                <p><?= gm_dashboard_h($lab['copy']) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="split" id="xfit">
                <div class="xfit-panel panel">
                    <p class="eyebrow">xFit</p>
                    <h2>Training Execution</h2>
                    <p>
                        xFit is the physical training side of the platform: session planning, strength tools, and the training
                        context that sits beside the Labs data. The dashboard keeps it separate so Gray Mentality remains the portal,
                        not just a fitness app.
                    </p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="<?= gm_dashboard_h((string)auth_env('XFIT_URL', 'https://xfit.graymentality.ca')) ?>">Go to xFit</a>
                        <a class="button" href="/modules/workout_day/index.php">Workout Day</a>
                    </div>

                    <div class="xfit-links">
                        <?php foreach ($xfitModules as $module): ?>
                            <a class="xfit-link" href="<?= gm_dashboard_h($module['route']) ?>">
                                <span class="card-label">xFit module</span>
                                <h3><?= gm_dashboard_h($module['title']) ?></h3>
                                <p><?= gm_dashboard_h($module['copy']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mentality-panel panel section" id="mentality">
                    <p class="eyebrow">Mentality</p>
                    <h2>Mental Work</h2>
                    <p>
                        Tenets, prompts, essays, decision logs, and quieter operating principles live here beside the physical work.
                    </p>

                    <article class="passage-invite">
                        <span class="card-label">Today&apos;s Descent</span>
                        <h3>5 Minutes Away From Automatic Thinking</h3>
                        <p>
                            Some days need a narrower doorway before the rest of the world gets loud again.
                        </p>
                        <div class="passage-depths">
                            <a class="button button-primary" href="/modules/passages/index.php?depth=deep">Enter</a>
                            <a class="button" href="/modules/passages/index.php?depth=short">Short</a>
                            <a class="button" href="/modules/passages/index.php?depth=long">Long</a>
                        </div>
                    </article>

                    <div class="mental-grid">
                        <?php foreach ($mentalityItems as $item): ?>
                            <article class="mental-card">
                                <span class="card-label">Gray Mentality</span>
                                <h3><?= gm_dashboard_h($item['title']) ?></h3>
                                <p><?= gm_dashboard_h($item['copy']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="hero-actions">
                        <a class="button button-primary" href="/start">Read the Tenets</a>
                        <a class="button" href="/modules/motivation/recovery/index.php">Recovery Prompts</a>
                        <a class="button" href="/modules/motivation/angry/index.php">High-Energy Focus</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer">
            <span>Gray Mentality dashboard</span>
            <span><?= gm_dashboard_h($currentDate) ?></span>
        </footer>
    </div>
</body>
</html>
