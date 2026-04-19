<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../auth_functions.php';

$authUser = require_auth();
$db = get_db_connection();

function gm_modules_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function gm_modules_schema_map(PDO $db): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [
        'tables' => [],
        'columns' => [],
    ];

    try {
        $stmt = $db->query(
            "SELECT LOWER(TABLE_NAME) AS table_name, LOWER(COLUMN_NAME) AS column_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, ORDINAL_POSITION"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $table = (string)($row['table_name'] ?? '');
            $column = (string)($row['column_name'] ?? '');
            if ($table === '' || $column === '') {
                continue;
            }

            $cache['tables'][$table] = true;
            $cache['columns'][$table] ??= [];
            $cache['columns'][$table][] = $column;
        }
    } catch (Throwable $e) {
        error_log('[modules.index.schema] ' . $e->getMessage());
    }

    return $cache;
}

function gm_modules_table_exists(array $schema, string $table): bool
{
    return isset($schema['tables'][strtolower($table)]);
}

function gm_modules_columns_for(array $schema, string $table): array
{
    $table = strtolower($table);
    return $schema['columns'][$table] ?? [];
}

function gm_modules_first_column(array $columns, array $candidates): ?string
{
    $lookup = array_fill_keys(array_map('strtolower', $columns), true);
    foreach ($candidates as $candidate) {
        $candidate = strtolower($candidate);
        if (isset($lookup[$candidate])) {
            return $candidate;
        }
    }

    return null;
}

function gm_modules_detect_subscription_source(array $schema): ?array
{
    $candidates = [
        ['table' => 'module_subscriptions', 'user_columns' => ['user_id', 'member_id', 'account_id'], 'module_columns' => ['module_key', 'module_slug', 'module_name', 'module_id'], 'label' => 'module_subscriptions'],
        ['table' => 'user_module_subscriptions', 'user_columns' => ['user_id', 'member_id', 'account_id'], 'module_columns' => ['module_key', 'module_slug', 'module_name', 'module_id'], 'label' => 'user_module_subscriptions'],
        ['table' => 'module_enrollments', 'user_columns' => ['user_id', 'member_id', 'account_id'], 'module_columns' => ['module_key', 'module_slug', 'module_name', 'module_id'], 'label' => 'module_enrollments'],
        ['table' => 'module_memberships', 'user_columns' => ['user_id', 'member_id', 'account_id'], 'module_columns' => ['module_key', 'module_slug', 'module_name', 'module_id'], 'label' => 'module_memberships'],
        ['table' => 'user_subscriptions', 'user_columns' => ['user_id', 'member_id', 'account_id'], 'module_columns' => ['module_key', 'module_slug', 'module_name', 'module_id'], 'label' => 'user_subscriptions'],
        ['table' => 'subscriptions', 'user_columns' => ['user_id', 'member_id', 'account_id'], 'module_columns' => ['module_key', 'module_slug', 'module_name', 'module_id'], 'label' => 'subscriptions'],
    ];

    foreach ($candidates as $candidate) {
        if (!gm_modules_table_exists($schema, $candidate['table'])) {
            continue;
        }

        $columns = gm_modules_columns_for($schema, $candidate['table']);
        $userColumn = gm_modules_first_column($columns, $candidate['user_columns']);
        $moduleColumn = gm_modules_first_column($columns, $candidate['module_columns']);

        if ($userColumn && $moduleColumn) {
            return [
                'table' => $candidate['table'],
                'label' => $candidate['label'],
                'user_column' => $userColumn,
                'module_column' => $moduleColumn,
            ];
        }
    }

    return null;
}

function gm_modules_current_user_profile(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.last_login,
                u.created_at, COALESCE(r.name, 'user') AS role_name
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

function gm_modules_member_synopsis(PDO $db, array $schema, int $currentUserId): array
{
    $subscriptionSource = gm_modules_detect_subscription_source($schema);
    $rows = [];
    $mode = 'roster';
    $sourceLabel = 'Active roster fallback';
    $headline = 'Module subscriptions have not been migrated yet. Showing the live member roster from auth tables until the xFit ledger lands here.';

    if ($subscriptionSource) {
        $table = $subscriptionSource['table'];
        $userColumn = $subscriptionSource['user_column'];
        $moduleColumn = $subscriptionSource['module_column'];
        $mode = 'subscriptions';
        $sourceLabel = $subscriptionSource['label'];
        $headline = 'Subscription rows are available in this database. Members are grouped by the modules they are attached to.';

        $sql = "
            SELECT
                u.id,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                u.last_login,
                COALESCE(r.name, 'user') AS role_name,
                COUNT(*) AS module_count,
                GROUP_CONCAT(DISTINCT CAST(ms.`{$moduleColumn}` AS CHAR) ORDER BY ms.`{$moduleColumn}` SEPARATOR ' · ') AS module_list
            FROM `{$table}` ms
            JOIN users u ON u.id = ms.`{$userColumn}`
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.is_active = TRUE
            GROUP BY u.id, u.username, u.first_name, u.last_name, u.email, u.last_login, role_name
            ORDER BY module_count DESC, u.last_login DESC, u.created_at DESC
        ";
        $stmt = $db->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } else {
        $stmt = $db->query(
            "SELECT
                u.id,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                u.last_login,
                COALESCE(r.name, 'user') AS role_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = TRUE
             ORDER BY CASE WHEN u.id = " . (int)$currentUserId . " THEN 0 ELSE 1 END, u.last_login DESC, u.created_at DESC"
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    $roleCounts = [];
    $activeCount = 0;
    foreach ($rows as $row) {
        $role = strtolower((string)($row['role_name'] ?? 'user'));
        $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
        $activeCount++;
    }

    return [
        'mode' => $mode,
        'source_label' => $sourceLabel,
        'headline' => $headline,
        'rows' => $rows,
        'count' => $activeCount,
        'role_counts' => $roleCounts,
    ];
}

function gm_modules_module_catalog(): array
{
    return [
        [
            'group' => 'Body Comp',
            'title' => 'BMR',
            'subtitle' => 'Baseline calories and maintenance',
            'description' => 'Resting burn, maintenance estimate, and goal intake snapshots.',
            'route' => '/modules/bmr/index.php',
            'asset' => '/modules/assets/bmr.png',
            'tables' => ['bmr_logs'],
        ],
        [
            'group' => 'Body Comp',
            'title' => 'Weight Trend',
            'subtitle' => 'Track change over time',
            'description' => 'Weight loss tracker, calorie trend, and progress logs.',
            'route' => '/modules/weight_loss/index.php',
            'asset' => '/modules/assets/weight_loss.png',
            'tables' => ['calories_log', 'user_calorie_profiles'],
        ],
        [
            'group' => 'Body Comp',
            'title' => 'Protein Intake',
            'subtitle' => 'Daily protein targets',
            'description' => 'Macro planning and intake tracking for lean mass support.',
            'route' => '/modules/protein_intake/index.php',
            'asset' => '/modules/assets/protein.png',
            'tables' => ['protein_logs', 'protein_intake_logs'],
        ],
        [
            'group' => 'Body Comp',
            'title' => 'Creatine',
            'subtitle' => 'Supplement logging and guidance',
            'description' => 'Simple creatine guidance and intake history.',
            'route' => '/modules/creatine/index.php',
            'asset' => '/modules/assets/creatine.png',
            'tables' => ['creatine_logs'],
        ],
        [
            'group' => 'Recovery',
            'title' => 'Hydration',
            'subtitle' => 'Water intake and fluid status',
            'description' => 'Track water intake and daily hydration habits.',
            'route' => '/modules/hydration/index.php',
            'asset' => '/modules/assets/hydration.png',
            'tables' => ['hydration_logs'],
        ],
        [
            'group' => 'Recovery',
            'title' => 'Sleep',
            'subtitle' => 'Sleep entry and recovery',
            'description' => 'Sleep tracking for consistency, rest, and recovery patterns.',
            'route' => '/modules/sleep/index.php',
            'asset' => '/modules/assets/recovery.png',
            'tables' => ['sleep_logs'],
        ],
        [
            'group' => 'Recovery',
            'title' => 'Sleep & Recovery',
            'subtitle' => 'Full recovery workflow',
            'description' => 'Recovery prompts, session notes, and sleep-linked context.',
            'route' => '/modules/sleep_recovery/index.php',
            'asset' => '/modules/assets/recovery1.png',
            'tables' => ['sleep_recovery_logs', 'recovery_sessions', 'recovery_prompts'],
        ],
        [
            'group' => 'Recovery',
            'title' => 'Motivation Recovery',
            'subtitle' => 'Reset the pace without losing momentum',
            'description' => 'Motivation prompts designed to support recovery-phase consistency.',
            'route' => '/modules/motivation/recovery/index.php',
            'asset' => '/modules/assets/recovery2.png',
            'tables' => [],
        ],
        [
            'group' => 'Strength',
            'title' => 'Frame Potential',
            'subtitle' => 'Physique frame and leverage',
            'description' => 'A structural overview of build potential and growth indicators.',
            'route' => '/modules/frame_potential/index.php',
            'asset' => '/modules/assets/frame_potential.png',
            'tables' => ['frame_potential_logs'],
        ],
        [
            'group' => 'Strength',
            'title' => 'Muscle Growth',
            'subtitle' => 'Growth tracking and training signals',
            'description' => 'Bodyweight, training, and growth logs for long-term progress.',
            'route' => '/modules/muscle_growth/index.php',
            'asset' => '/modules/assets/muscle_growth.png',
            'tables' => ['muscle_growth_logs'],
        ],
        [
            'group' => 'Strength',
            'title' => 'Grip Strength',
            'subtitle' => 'Hands, forearms, and carryover',
            'description' => 'Grip logging, hold capacity, and carryover tracking.',
            'route' => '/modules/grip_strength/index.php',
            'asset' => '/modules/assets/grip.png',
            'tables' => ['grip_logs'],
        ],
        [
            'group' => 'Strength',
            'title' => 'Workout Day',
            'subtitle' => 'Session planner and training notes',
            'description' => 'Plan workouts, review session structure, and keep the day organized.',
            'route' => '/modules/workout_day/index.php',
            'asset' => '/modules/assets/dumbellcurlman.png',
            'tables' => [],
        ],
        [
            'group' => 'Strength',
            'title' => 'Angry Motivation',
            'subtitle' => 'High-energy focus',
            'description' => 'Fast access motivation for hard training blocks and reset moments.',
            'route' => '/modules/motivation/angry/index.php',
            'asset' => '/modules/assets/dumbellcurlman2.png',
            'tables' => ['motivation_sessions', 'motivation_chants', 'motivation_session_chants', 'motivation_session_tracks', 'audio_tracks'],
        ],
        [
            'group' => 'Education',
            'title' => 'Learning Hub',
            'subtitle' => 'Reference and platform notes',
            'description' => 'Guides, background context, and learning materials for the platform.',
            'route' => '/modules/Library/learning_hub.php',
            'asset' => '/modules/assets/library.png',
            'tables' => [],
        ],
    ];
}

function gm_modules_module_status(array $module, array $schema): array
{
    if (empty($module['tables'])) {
        return [
            'state' => 'static',
            'label' => 'Static content',
            'detail' => 'No migrated storage required.',
        ];
    }

    $missing = [];
    foreach ($module['tables'] as $table) {
        if (!gm_modules_table_exists($schema, $table)) {
            $missing[] = $table;
        }
    }

    if ($missing) {
        return [
            'state' => 'pending',
            'label' => 'Import pending',
            'detail' => 'Missing: ' . implode(', ', $missing),
        ];
    }

    return [
        'state' => 'ready',
        'label' => 'Ready',
        'detail' => 'All expected tables exist here.',
    ];
}

function gm_modules_format_last_login(?string $value): string
{
    if (!$value) {
        return 'Never';
    }

    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('M j, Y g:ia');
    } catch (Throwable $e) {
        return $value;
    }
}

$schema = gm_modules_schema_map($db);
$profile = gm_modules_current_user_profile($db, (int)$authUser['id']);
$memberSynopsis = gm_modules_member_synopsis($db, $schema, (int)$authUser['id']);
$modules = gm_modules_module_catalog();

$moduleStats = [
    'ready' => 0,
    'pending' => 0,
    'static' => 0,
];

foreach ($modules as &$module) {
    $module['status'] = gm_modules_module_status($module, $schema);
    $moduleStats[$module['status']['state']] = ($moduleStats[$module['status']['state']] ?? 0) + 1;
}
unset($module);

$news = [
    [
        'title' => 'Hero-first portal is live',
        'copy' => 'Logged-in users land here first so news, member status, and module entry points sit in one place.',
    ],
    [
        'title' => 'Migration watch is explicit',
        'copy' => 'The module tables still live in xFit. Each card now tells you whether the import is ready or pending.',
    ],
    [
        'title' => 'Touch layout is menu-first',
        'copy' => 'Mobile users get a stacked launcher instead of a carousel, which keeps navigation obvious and thumb-friendly.',
    ],
];
$salesFeature = [
    'eyebrow' => 'Home gym setups',
    'title' => 'From compact starter corners to full custom training rooms.',
    'copy' => 'We plan home gym setups around the space you actually have and the budget you want to keep. We measure the room, define what you need, source equipment around your goals, and search both new inventory and strong local second-hand finds before we present a package for your approval.',
    'follow_up' => 'Once you sign off, we set an install date, bring the equipment in, assemble the room, and show you how to use it safely so the setup is ready to train from day one.',
    'steps' => [
        'Measure the dedicated space and set the budget.',
        'Build the equipment package around your training needs.',
        'Source a mix of new and experienced equipment locally.',
        'Install the room and walk you through safe use.',
    ],
];
// Set this to a public asset path when the sales image is ready, e.g. '/modules/assets/home_gym_setup.jpg'.
$salesImage = '';

$currentDate = (new DateTimeImmutable('now', new DateTimeZone('America/Toronto')))->format('F j, Y');
$displayName = trim((string)($profile['first_name'] ?? '') . ' ' . (string)($profile['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($profile['username'] ?? $authUser['username'] ?? 'user');
}

$roleName = (string)($profile['role_name'] ?? 'user');
$lastLogin = gm_modules_format_last_login(isset($profile['last_login']) ? (string)$profile['last_login'] : null);
$moduleSummaryText = $moduleStats['ready'] . ' ready, ' . $moduleStats['pending'] . ' pending, ' . $moduleStats['static'] . ' static';
$subscriptionModeText = $memberSynopsis['mode'] === 'subscriptions'
    ? 'Subscription source detected'
    : 'Fallback roster in use';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modules Portal | Gray Mentality</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        :root {
            --portal-bg: #050609;
            --portal-surface: rgba(11, 15, 22, 0.80);
            --portal-surface-2: rgba(13, 18, 28, 0.92);
            --portal-line: rgba(255, 255, 255, 0.12);
            --portal-line-strong: rgba(255, 255, 255, 0.20);
            --portal-text: #f1f4f8;
            --portal-muted: #acb5c3;
            --portal-soft: #7e8897;
            --portal-accent: #f28c28;
            --portal-accent-2: #7dd3fc;
            --portal-good: #39d98a;
            --portal-warn: #f59e0b;
            --portal-bad: #f97373;
            --portal-shadow: 0 24px 70px rgba(0, 0, 0, 0.42);
            --portal-radius-xl: 30px;
            --portal-radius-lg: 22px;
            --portal-radius-md: 16px;
            --portal-radius-sm: 999px;
            --portal-width: 1320px;
        }

        html { color-scheme: dark; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--portal-text);
            font-family: "Segoe UI", "Trebuchet MS", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background:
                radial-gradient(1000px 700px at 12% 14%, rgba(126, 99, 255, 0.24), transparent 58%),
                radial-gradient(900px 620px at 88% 10%, rgba(242, 140, 40, 0.18), transparent 45%),
                radial-gradient(800px 560px at 82% 88%, rgba(125, 211, 252, 0.12), transparent 46%),
                linear-gradient(180deg, #040507 0%, #090b10 42%, #040507 100%);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(to right, rgba(255, 255, 255, 0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 84px 84px;
            opacity: 0.24;
            mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.35), transparent 90%);
        }

        a { color: inherit; text-decoration: none; }
        code { color: #fde68a; }

        .portal-shell {
            width: min(var(--portal-width), calc(100vw - 32px));
            margin: 0 auto;
            padding: 18px 0 40px;
            position: relative;
        }

        .topbar {
            position: sticky;
            top: 14px;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 16px 18px;
            border-radius: var(--portal-radius-xl);
            border: 1px solid var(--portal-line);
            background: linear-gradient(180deg, rgba(13, 17, 26, 0.80), rgba(9, 11, 16, 0.62));
            backdrop-filter: blur(18px);
            box-shadow: var(--portal-shadow);
        }

        .brand {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .brand-kicker {
            font-size: 0.68rem;
            letter-spacing: 0.34em;
            text-transform: uppercase;
            color: var(--portal-muted);
        }

        .brand-name {
            font-size: clamp(1.02rem, 2vw, 1.22rem);
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .topbar-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .chip,
        .filter-chip,
        .action-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 16px;
            border-radius: var(--portal-radius-sm);
            border: 1px solid var(--portal-line-strong);
            background: rgba(255, 255, 255, 0.04);
            color: var(--portal-text);
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .chip-accent {
            border-color: rgba(242, 140, 40, 0.55);
            background: rgba(242, 140, 40, 0.09);
        }

        .chip-ghost {
            color: var(--portal-muted);
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.8fr);
            gap: 20px;
            align-items: start;
            padding: 24px 0 16px;
        }

        .hero-main,
        .hero-side,
        .portal-panel,
        .member-panel,
        .module-panel {
            border: 1px solid var(--portal-line);
            border-radius: var(--portal-radius-xl);
            background: linear-gradient(180deg, rgba(13, 17, 26, 0.74), rgba(8, 10, 15, 0.86));
            backdrop-filter: blur(18px);
            box-shadow: var(--portal-shadow);
        }

        .hero-main {
            position: relative;
            overflow: hidden;
            padding: clamp(24px, 4vw, 42px);
        }

        .hero-main::after {
            content: "";
            position: absolute;
            inset: auto -18% -30% auto;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(242, 140, 40, 0.18), transparent 68%);
            pointer-events: none;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            color: rgba(241, 244, 248, 0.82);
            font-size: 0.72rem;
            letter-spacing: 0.34em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 18px;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--portal-accent), var(--portal-accent-2));
        }

        h1 {
            margin: 0;
            font-size: clamp(2.6rem, 6vw, 5.2rem);
            line-height: 0.93;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            max-width: 12ch;
        }

        .lead {
            margin: 18px 0 0;
            max-width: 64ch;
            color: var(--portal-muted);
            font-size: clamp(1rem, 1.4vw, 1.08rem);
            line-height: 1.76;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        .action-chip {
            transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
        }

        .action-chip:hover,
        .filter-chip:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.30);
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-news {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 24px;
        }

        .news-card {
            position: relative;
            overflow: hidden;
            min-height: 150px;
            padding: 16px 16px 15px;
            border-radius: var(--portal-radius-md);
            border: 1px solid rgba(255, 255, 255, 0.09);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.03)),
                rgba(9, 12, 18, 0.72);
        }

        .news-card::before {
            content: "";
            position: absolute;
            inset: 0 auto auto 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--portal-accent), var(--portal-accent-2));
        }

        .news-card h3 {
            margin: 8px 0 8px;
            font-size: 1.02rem;
            line-height: 1.25;
        }

        .news-card p {
            margin: 0;
            color: var(--portal-muted);
            font-size: 0.92rem;
            line-height: 1.65;
        }

        .hero-side {
            padding: 20px;
        }

        .summary-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: var(--portal-radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.10);
            background: rgba(255, 255, 255, 0.04);
            font-size: 0.76rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(241, 244, 248, 0.88);
        }

        .summary-copy {
            margin: 14px 0 0;
            color: var(--portal-muted);
            font-size: 0.94rem;
            line-height: 1.72;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .stat {
            padding: 14px;
            border-radius: var(--portal-radius-md);
            border: 1px solid rgba(255, 255, 255, 0.09);
            background: rgba(255, 255, 255, 0.035);
        }

        .stat strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .stat span {
            color: var(--portal-muted);
            font-size: 0.9rem;
            line-height: 1.55;
        }

        .portal-panel {
            margin-top: 20px;
            padding: 18px;
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .panel-head h2 {
            margin: 0;
            font-size: clamp(1.2rem, 2vw, 1.6rem);
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .panel-head p {
            margin: 0;
            color: var(--portal-soft);
            font-size: 0.9rem;
        }

        .migration-banner {
            margin-top: 12px;
            padding: 14px 15px;
            border-radius: var(--portal-radius-md);
            border: 1px solid rgba(245, 158, 11, 0.45);
            background: rgba(124, 45, 18, 0.22);
            color: #fde68a;
            line-height: 1.65;
            font-size: 0.94rem;
        }

        .panel-split {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
            gap: 14px;
            margin-top: 16px;
        }

        .panel-box {
            padding: 15px;
            border-radius: var(--portal-radius-md);
            border: 1px solid rgba(255, 255, 255, 0.09);
            background: rgba(255, 255, 255, 0.035);
        }

        .panel-box h3 {
            margin: 0 0 10px;
            font-size: 0.95rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .panel-box p {
            margin: 0;
            color: var(--portal-muted);
            font-size: 0.92rem;
            line-height: 1.65;
        }

        .member-list {
            display: grid;
            gap: 10px;
            margin-top: 12px;
            max-height: 440px;
            overflow: auto;
            padding-right: 2px;
        }

        .member-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 12px 13px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
        }

        .member-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #fff6ea;
            background: linear-gradient(135deg, rgba(242, 140, 40, 0.85), rgba(126, 99, 255, 0.70));
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
        }

        .member-name {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-weight: 700;
        }

        .member-meta {
            margin-top: 4px;
            color: var(--portal-muted);
            font-size: 0.88rem;
            line-height: 1.45;
        }

        .member-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: var(--portal-radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.10);
            background: rgba(255, 255, 255, 0.04);
            font-size: 0.74rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .tag-good { border-color: rgba(57, 217, 138, 0.35); color: #bbf7d0; }
        .tag-warn { border-color: rgba(245, 158, 11, 0.40); color: #fde68a; }

        .role-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: var(--portal-radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.10);
            background: rgba(255, 255, 255, 0.04);
            color: var(--portal-muted);
            font-size: 0.86rem;
        }

        .module-panel {
            margin-top: 20px;
            padding: 18px;
        }

        .sales-panel {
            margin-top: 20px;
            padding: 18px;
        }

        .sales-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
            gap: 18px;
            align-items: stretch;
        }

        .sales-copy {
            display: grid;
            gap: 14px;
        }

        .sales-copy p {
            margin: 0;
            color: var(--portal-muted);
            font-size: 0.96rem;
            line-height: 1.72;
        }

        .sales-steps {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .sales-step {
            padding: 14px;
            border-radius: var(--portal-radius-md);
            border: 1px solid rgba(255, 255, 255, 0.08);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02)),
                rgba(8, 12, 18, 0.72);
        }

        .sales-step strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.82rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--portal-accent-2);
        }

        .sales-step span {
            color: var(--portal-text);
            font-size: 0.95rem;
            line-height: 1.55;
        }

        .sales-visual {
            position: relative;
            overflow: hidden;
            min-height: 320px;
            padding: 18px;
            border-radius: 26px;
            border: 1px solid rgba(255, 255, 255, 0.10);
            background:
                radial-gradient(circle at top left, rgba(242, 140, 40, 0.20), transparent 44%),
                radial-gradient(circle at bottom right, rgba(125, 211, 252, 0.18), transparent 40%),
                linear-gradient(180deg, rgba(17, 24, 37, 0.96), rgba(7, 10, 16, 0.98));
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .sales-visual::before,
        .sales-visual::after {
            content: "";
            position: absolute;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
        }

        .sales-visual::before {
            inset: 26px 70px auto 26px;
            height: 88px;
        }

        .sales-visual::after {
            inset: auto 26px 26px 110px;
            height: 104px;
        }

        .sales-visual.has-image {
            background-image:
                linear-gradient(180deg, rgba(5, 9, 14, 0.18), rgba(5, 9, 14, 0.86)),
                url('<?= gm_modules_h($salesImage) ?>');
            background-size: cover;
            background-position: center;
        }

        .sales-visual.has-image::before,
        .sales-visual.has-image::after {
            display: none;
        }

        .sales-badge {
            position: relative;
            z-index: 1;
            display: inline-flex;
            width: fit-content;
            margin-bottom: 10px;
            padding: 7px 10px;
            border-radius: var(--portal-radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(6, 10, 15, 0.54);
            color: #fff6ea;
            font-size: 0.74rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .sales-visual strong,
        .sales-visual span {
            position: relative;
            z-index: 1;
        }

        .sales-visual strong {
            font-size: 1.14rem;
            line-height: 1.3;
        }

        .sales-visual span {
            margin-top: 8px;
            color: var(--portal-muted);
            line-height: 1.6;
        }

        .filter-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .filter-chip {
            cursor: pointer;
            transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
        }

        .filter-chip.is-active {
            border-color: rgba(242, 140, 40, 0.58);
            background: rgba(242, 140, 40, 0.12);
            color: #fff3e6;
        }

        .resume-card {
            display: none;
            margin-top: 16px;
            padding: 16px;
            border-radius: var(--portal-radius-md);
            border: 1px solid rgba(242, 140, 40, 0.32);
            background: linear-gradient(135deg, rgba(242, 140, 40, 0.12), rgba(126, 99, 255, 0.10));
        }

        .resume-card strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.96rem;
        }

        .resume-card p {
            margin: 0;
            color: var(--portal-muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .resume-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 16px;
        }

        .module-card {
            position: relative;
            overflow: hidden;
            min-height: 200px;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.09);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.03)),
                rgba(9, 12, 18, 0.74);
            transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
        }

        .module-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.22);
            box-shadow: 0 20px 42px rgba(0, 0, 0, 0.26);
        }

        .module-card::before {
            content: "";
            position: absolute;
            inset: 0 auto auto 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--portal-accent), var(--portal-accent-2));
        }

        .module-art {
            position: absolute;
            right: -10px;
            top: -10px;
            width: 120px;
            height: 120px;
            background-size: cover;
            background-position: center;
            border-radius: 28px;
            opacity: 0.22;
            filter: blur(0.5px) saturate(1.12);
            transform: rotate(8deg);
        }

        .module-group {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: var(--portal-radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.05);
            color: var(--portal-accent-2);
            font-size: 0.68rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }

        .module-card h3 {
            margin: 14px 0 6px;
            font-size: 1.08rem;
            line-height: 1.25;
            position: relative;
        }

        .module-card .module-subtitle {
            display: block;
            margin: 0 0 12px;
            color: rgba(241, 244, 248, 0.84);
            font-size: 0.9rem;
        }

        .module-card p {
            margin: 0;
            color: var(--portal-muted);
            font-size: 0.92rem;
            line-height: 1.66;
            position: relative;
        }

        .module-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 16px;
            position: relative;
        }

        .module-status .tag {
            margin-left: auto;
        }

        .status-ready { border-color: rgba(57, 217, 138, 0.42); color: #bbf7d0; }
        .status-pending { border-color: rgba(245, 158, 11, 0.42); color: #fde68a; }
        .status-static { border-color: rgba(125, 211, 252, 0.42); color: #cffafe; }

        .footer {
            margin-top: 18px;
            padding: 8px 8px 0;
            color: var(--portal-soft);
            font-size: 0.84rem;
            text-align: center;
        }

        @media (max-width: 1080px) {
            .hero,
            .panel-split,
            .sales-layout {
                grid-template-columns: 1fr;
            }

            .module-grid,
            .hero-news {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .portal-shell {
                width: min(var(--portal-width), calc(100vw - 20px));
                padding-top: 10px;
            }

            .topbar {
                top: 8px;
                padding: 14px;
                border-radius: 24px;
                flex-direction: column;
                align-items: flex-start;
            }

            .topbar-meta {
                width: 100%;
                justify-content: flex-start;
            }

            .hero {
                padding-top: 18px;
            }

            .hero-news,
            .module-grid,
            .stat-grid,
            .sales-steps {
                grid-template-columns: 1fr;
            }

            h1 {
                max-width: 10ch;
            }

            .hero-main,
            .hero-side,
            .portal-panel,
            .member-panel,
            .module-panel {
                border-radius: 24px;
            }

            .member-row {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .member-tags {
                grid-column: 1 / -1;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="portal-shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-kicker">Gray Mentality</div>
                <div class="brand-name">Modules Portal</div>
            </div>
            <div class="topbar-meta">
                <span class="chip chip-accent">Welcome, <?= gm_modules_h($displayName) ?></span>
                <span class="chip chip-ghost"><?= gm_modules_h($roleName) ?></span>
                <a class="chip" href="/logout.php">Logout</a>
            </div>
        </header>

        <main>
            <section class="hero">
                <div class="hero-main">
                    <div class="eyebrow">Logged-in menu first</div>
                    <h1>Portal first. Modules second.</h1>
                    <p class="lead">
                        This is the landing page for authenticated users. It opens with platform news, shows the current member
                        snapshot, and then drops into the module launcher. The module tables still live in the xFit schema for now,
                        so the portal will mark each module as pending until you migrate those tables into this database.
                    </p>

                    <div class="hero-actions">
                        <a class="action-chip" href="#modules">Open modules</a>
                        <a class="action-chip" href="#members">View members</a>
                        <span class="action-chip chip-ghost"><?= gm_modules_h($currentDate) ?></span>
                    </div>

                    <div class="hero-news">
                        <?php foreach ($news as $item): ?>
                            <article class="news-card">
                                <div class="tag tag-good">Platform news</div>
                                <h3><?= gm_modules_h($item['title']) ?></h3>
                                <p><?= gm_modules_h($item['copy']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <aside class="hero-side">
                    <div class="summary-title">Portal status</div>
                    <p class="summary-copy">
                        <?= gm_modules_h($subscriptionModeText) ?>. The menu is built to stay usable on mobile, and every card now
                        carries a table-status badge so you can see exactly what is still waiting on migration.
                    </p>

                    <div class="stat-grid">
                        <div class="stat">
                            <strong>Modules</strong>
                            <span><?= count($modules) ?> total across body comp, recovery, strength, and education.</span>
                        </div>
                        <div class="stat">
                            <strong>Status</strong>
                            <span><?= gm_modules_h($moduleSummaryText) ?>.</span>
                        </div>
                        <div class="stat">
                            <strong>Member view</strong>
                            <span><?= gm_modules_h((string)$memberSynopsis['count']) ?> active users in the current roster.</span>
                        </div>
                        <div class="stat">
                            <strong>Last login</strong>
                            <span><?= gm_modules_h($lastLogin) ?></span>
                        </div>
                    </div>

                    <div class="migration-banner">
                        <strong>Migration note:</strong>
                        The module queries currently point at tables that still need to be imported from xFit. This page is ready
                        to detect those tables later, so badges will flip automatically once the migration lands.
                    </div>
                </aside>
            </section>

            <section class="portal-panel sales-panel" id="home-gym-setups">
                <div class="panel-head">
                    <div>
                        <h2><?= gm_modules_h($salesFeature['eyebrow']) ?></h2>
                        <p>Planning, sourcing, install, and safe-use walkthroughs.</p>
                    </div>
                    <span class="chip chip-accent">Built around your room and budget</span>
                </div>

                <div class="sales-layout">
                    <div class="sales-copy">
                        <div class="eyebrow"><?= gm_modules_h($salesFeature['eyebrow']) ?></div>
                        <h3><?= gm_modules_h($salesFeature['title']) ?></h3>
                        <p><?= gm_modules_h($salesFeature['copy']) ?></p>
                        <p><?= gm_modules_h($salesFeature['follow_up']) ?></p>

                        <div class="sales-steps">
                            <?php foreach ($salesFeature['steps'] as $index => $step): ?>
                                <div class="sales-step">
                                    <strong>Step <?= gm_modules_h((string)($index + 1)) ?></strong>
                                    <span><?= gm_modules_h($step) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <aside class="sales-visual<?= $salesImage !== '' ? ' has-image' : '' ?>">
                        <div class="sales-badge"><?= $salesImage !== '' ? 'Project image' : 'Image slot' ?></div>
                        <strong>
                            <?= $salesImage !== '' ? 'Installed setup preview' : 'Add a home gym photo, render, or before-and-after here.' ?>
                        </strong>
                        <span>
                            <?= $salesImage !== '' ? 'Use this area to show the style and finish level you want to sell.' : 'Drop in a real room image later by setting the sales image asset path in the portal config.' ?>
                        </span>
                    </aside>
                </div>
            </section>

            <section class="portal-panel" id="members">
                <div class="panel-head">
                    <div>
                        <h2>Members & subscriptions</h2>
                        <p><?= gm_modules_h($memberSynopsis['source_label']) ?></p>
                    </div>
                    <span class="chip chip-accent"><?= gm_modules_h($memberSynopsis['mode'] === 'subscriptions' ? 'Subscription-linked' : 'Roster-linked') ?></span>
                </div>

                <p class="summary-copy">
                    <?= gm_modules_h($memberSynopsis['headline']) ?>
                </p>

                <div class="panel-split">
                    <div class="member-panel panel-box">
                        <h3>Active users</h3>
                        <p>
                            This is the synopsis the logged-in menu shows today. Once you migrate the module membership table into
                            this database, this panel will switch from roster fallback to actual subscription grouping.
                        </p>

                        <div class="member-list">
                            <?php if (empty($memberSynopsis['rows'])): ?>
                                <div class="member-row">
                                    <div class="member-avatar">GM</div>
                                    <div>
                                        <div class="member-name">No active users found</div>
                                        <div class="member-meta">The users table returned no active rows.</div>
                                    </div>
                                    <div class="member-tags">
                                        <span class="tag tag-warn">Pending data</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($memberSynopsis['rows'] as $row): ?>
                                    <?php
                                        $firstName = trim((string)($row['first_name'] ?? ''));
                                        $lastName = trim((string)($row['last_name'] ?? ''));
                                        $display = trim($firstName . ' ' . $lastName);
                                        if ($display === '') {
                                            $display = (string)($row['username'] ?? 'member');
                                        }
                                        $avatar = strtoupper(substr((string)$display, 0, 2));
                                        $lastLoginLabel = gm_modules_format_last_login(isset($row['last_login']) ? (string)$row['last_login'] : null);
                                        $moduleCount = isset($row['module_count']) ? (int)$row['module_count'] : 0;
                                        $moduleList = trim((string)($row['module_list'] ?? ''));
                                    ?>
                                    <div class="member-row">
                                        <div class="member-avatar"><?= gm_modules_h($avatar) ?></div>
                                        <div>
                                            <div class="member-name">
                                                <?= gm_modules_h($display) ?>
                                                <?php if ((int)($authUser['id'] ?? 0) === (int)($row['id'] ?? 0)): ?>
                                                    <span class="tag tag-good">You</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="member-meta">
                                                <?= gm_modules_h((string)($row['email'] ?? '')) ?> · <?= gm_modules_h((string)($row['role_name'] ?? 'user')) ?> · Last login: <?= gm_modules_h($lastLoginLabel) ?>
                                                <?php if ($moduleCount > 0): ?>
                                                    <br>Modules: <?= gm_modules_h((string)$moduleCount) ?><?= $moduleList !== '' ? ' · ' . gm_modules_h($moduleList) : '' ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="member-tags">
                                            <span class="tag <?= $moduleCount > 0 ? 'tag-good' : 'tag-warn' ?>">
                                                <?= $moduleCount > 0 ? gm_modules_h((string)$moduleCount . ' module' . ($moduleCount === 1 ? '' : 's')) : 'Roster only' ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <aside class="panel-box">
                        <h3>Role synopsis</h3>
                        <p>
                            Current member counts are read from the auth tables. The role summary remains useful while the module
                            subscription table is still pending migration.
                        </p>

                        <div class="role-summary">
                            <?php foreach ($memberSynopsis['role_counts'] as $role => $count): ?>
                                <span class="role-pill"><?= gm_modules_h(ucfirst($role)) ?>: <?= gm_modules_h((string)$count) ?></span>
                            <?php endforeach; ?>
                            <?php if (!$memberSynopsis['role_counts']): ?>
                                <span class="role-pill">No role data</span>
                            <?php endif; ?>
                        </div>

                        <div class="migration-banner" style="margin-top: 16px;">
                            When your module tables move into this database, this panel can be switched over to real subscription counts
                            without rewriting the menu surface.
                        </div>
                    </aside>
                </div>
            </section>

            <section class="module-panel" id="modules">
                <div class="panel-head">
                    <div>
                        <h2>Module menu</h2>
                        <p>Use filters to jump by category. Tap any card to enter the module directly.</p>
                    </div>
                    <span class="chip chip-accent">Touch-friendly launcher</span>
                </div>

                <div class="filter-row" role="tablist" aria-label="Module filters">
                    <button class="filter-chip is-active" type="button" data-filter="all">All</button>
                    <button class="filter-chip" type="button" data-filter="Body Comp">Body Comp</button>
                    <button class="filter-chip" type="button" data-filter="Recovery">Recovery</button>
                    <button class="filter-chip" type="button" data-filter="Strength">Strength</button>
                    <button class="filter-chip" type="button" data-filter="Education">Education</button>
                </div>

                <div id="resume-card" class="resume-card">
                    <strong>Continue where you left off</strong>
                    <p id="resume-copy">Open the last module you visited without returning to the start.</p>
                    <div class="resume-actions">
                        <a id="resume-link" class="action-chip" href="#">Resume module</a>
                        <button id="clear-resume" class="action-chip" type="button">Clear history</button>
                    </div>
                </div>

                <div class="module-grid">
                    <?php foreach ($modules as $module): ?>
                        <?php
                            $status = $module['status'];
                            $statusClass = 'status-' . $status['state'];
                            $asset = (string)($module['asset'] ?? '');
                            $group = (string)($module['group'] ?? 'Module');
                        ?>
                        <a
                            class="module-card"
                            href="<?= gm_modules_h((string)$module['route']) ?>"
                            data-title="<?= gm_modules_h((string)$module['title']) ?>"
                            data-route="<?= gm_modules_h((string)$module['route']) ?>"
                            data-group="<?= gm_modules_h($group) ?>"
                        >
                            <?php if ($asset !== ''): ?>
                                <div class="module-art" style="background-image: url('<?= gm_modules_h($asset) ?>');"></div>
                            <?php endif; ?>
                            <span class="module-group"><?= gm_modules_h($group) ?></span>
                            <h3><?= gm_modules_h((string)$module['title']) ?></h3>
                            <span class="module-subtitle"><?= gm_modules_h((string)$module['subtitle']) ?></span>
                            <p><?= gm_modules_h((string)$module['description']) ?></p>
                            <div class="module-status">
                                <span class="tag <?= gm_modules_h($statusClass) ?>"><?= gm_modules_h($status['label']) ?></span>
                                <span class="tag"><?= gm_modules_h((string)$status['detail']) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="footer">
                Gray Mentality portal menu · <?= gm_modules_h($currentDate) ?> · Built to flip from roster fallback to real subscriptions after migration.
            </div>
        </main>
    </div>

    <script>
        (function () {
            const cards = Array.from(document.querySelectorAll('.module-card'));
            const filterButtons = Array.from(document.querySelectorAll('.filter-chip'));
            const resumeCard = document.getElementById('resume-card');
            const resumeCopy = document.getElementById('resume-copy');
            const resumeLink = document.getElementById('resume-link');
            const clearResume = document.getElementById('clear-resume');
            const storageKey = 'gm_last_module';

            function applyFilter(group) {
                cards.forEach(card => {
                    const match = group === 'all' || card.dataset.group === group;
                    card.style.display = match ? '' : 'none';
                });

                filterButtons.forEach(button => {
                    button.classList.toggle('is-active', button.dataset.filter === group);
                });
            }

            function loadResume() {
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (!raw) {
                        resumeCard.style.display = 'none';
                        return;
                    }

                    const data = JSON.parse(raw);
                    if (!data || !data.route || !data.title) {
                        resumeCard.style.display = 'none';
                        return;
                    }

                    resumeCopy.textContent = `Last opened: ${data.title}`;
                    resumeLink.href = data.route;
                    resumeCard.style.display = 'block';
                } catch (error) {
                    resumeCard.style.display = 'none';
                }
            }

            cards.forEach(card => {
                card.addEventListener('click', () => {
                    try {
                        localStorage.setItem(storageKey, JSON.stringify({
                            title: card.dataset.title || 'Module',
                            route: card.dataset.route || '#'
                        }));
                    } catch (error) {
                        // Ignore storage failures on constrained browsers.
                    }
                });
            });

            filterButtons.forEach(button => {
                button.addEventListener('click', () => applyFilter(button.dataset.filter || 'all'));
            });

            if (clearResume) {
                clearResume.addEventListener('click', () => {
                    try {
                        localStorage.removeItem(storageKey);
                    } catch (error) {
                        // Ignore storage failures.
                    }
                    loadResume();
                });
            }

            applyFilter('all');
            loadResume();
        })();
    </script>
</body>
</html>
