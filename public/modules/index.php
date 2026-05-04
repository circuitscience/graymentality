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
        "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.last_login,
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

$user = gm_dashboard_user($db, (int)$authUser['id']);
$displayName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($user['username'] ?? $authUser['username'] ?? 'member');
}

$firstName = trim((string)($user['first_name'] ?? ''));
if ($firstName === '') {
    $firstName = (string)($user['username'] ?? 'there');
}

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
    ],
    [
        'title' => 'Weight Trend',
        'label' => 'Log',
        'copy' => 'Track bodyweight, calorie direction, and trend movement.',
        'route' => '/modules/weight_loss/index.php',
        'asset' => '/modules/assets/weight_loss.png',
    ],
    [
        'title' => 'Protein Intake',
        'label' => 'Calculator',
        'copy' => 'Set daily protein targets and keep intake accountable.',
        'route' => '/modules/protein_intake/index.php',
        'asset' => '/modules/assets/protein.png',
    ],
    [
        'title' => 'Creatine',
        'label' => 'Guide',
        'copy' => 'Supplement guidance and intake history.',
        'route' => '/modules/creatine/index.php',
        'asset' => '/modules/assets/creatine.png',
    ],
    [
        'title' => 'Hydration',
        'label' => 'Log',
        'copy' => 'Record water intake and daily hydration habits.',
        'route' => '/modules/hydration/index.php',
        'asset' => '/modules/assets/hydration.png',
    ],
    [
        'title' => 'Sleep',
        'label' => 'Log',
        'copy' => 'Track sleep consistency and recovery context.',
        'route' => '/modules/sleep/index.php',
        'asset' => '/modules/assets/recovery.png',
    ],
    [
        'title' => 'Recovery',
        'label' => 'Workflow',
        'copy' => 'Capture recovery notes, prompts, and reset sessions.',
        'route' => '/modules/sleep_recovery/index.php',
        'asset' => '/modules/assets/recovery1.png',
    ],
    [
        'title' => 'Frame Potential',
        'label' => 'Assessment',
        'copy' => 'Review build indicators, leverage, and structural context.',
        'route' => '/modules/frame_potential/index.php',
        'asset' => '/modules/assets/frame_potential.png',
    ],
    [
        'title' => 'Muscle Growth',
        'label' => 'Log',
        'copy' => 'Track bodyweight and growth signals over time.',
        'route' => '/modules/muscle_growth/index.php',
        'asset' => '/modules/assets/muscle_growth.png',
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

        .section {
            margin-top: 22px;
            padding: clamp(22px, 4vw, 34px);
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
    </style>
</head>
<body>
    <div class="dashboard">
        <header class="topbar">
            <a class="brand" href="/modules/index.php" aria-label="Gray Mentality dashboard">
                <span>Gray Mentality</span>
                <strong>Dashboard</strong>
            </a>
            <nav class="top-actions" aria-label="Dashboard actions">
                <a class="chip" href="#labs">Labs</a>
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
                            <a class="button" href="#mentality">Mental Work</a>
                        </div>
                    </div>
                </div>

                <aside class="hero-side panel">
                    <div class="stat">
                        <strong>Member</strong>
                        <span><?= gm_dashboard_h($displayName) ?></span>
                    </div>
                    <div class="stat">
                        <strong>Profile</strong>
                        <span><?= gm_dashboard_h($profileBits ? implode(' / ', $profileBits) : 'Profile initialized') ?></span>
                    </div>
                    <div class="stat">
                        <strong>Last login</strong>
                        <span><?= gm_dashboard_h($lastLogin) ?></span>
                    </div>
                    <div class="stat">
                        <strong>Sections</strong>
                        <span>Labs, xFit, and Mentality are separated so this portal can grow without becoming a mixed menu.</span>
                    </div>
                </aside>
            </section>

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
                        This area is ready for the content library: tenets, essays, prompts, decision logs, recovery notes, and
                        non-physical operating principles. For now, it defines the containers so your content can land cleanly.
                    </p>

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
