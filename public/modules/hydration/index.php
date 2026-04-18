<?php
declare(strict_types=1);

/**
 * Hydration Tracker (exFIT)
 * ------------------------------------------------------------------------
 * FILE: /public/modules/hydration/index.php
 *
 * PURPOSE
 *  - Track daily fluid intake (liters), urine color (1–5), thirst level (1–5), notes.
 *  - Auto-suggest a daily goal from body weight (kg) when available.
 *  - Compute a hydration score (0–100) and save to `hydration_logs`.
 *  - Display last 7 days for quick trend checking.
 *
 * UX / LAYOUT (MATCHES GRIP STRENGTH LAB)
 *  - Uses the same module shell:
 *      • blurred background image (module-specific)
 *      • translucent “module-card” overlay
 *      • header with “‹ Modules” back button to ../index.php
 *  - Background image is controlled via:
 *      :root { --module-bg-image: url('../assets/hydration.png'); }
 *
 * DATABASE EXPECTATIONS
 *  - Table: hydration_logs
 *    Columns used:
 *      user_id, log_date, intake_liters, goal_liters, urine_color,
 *      thirst_level, notes, hydration_score, created_at
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('DB connection not available.');
}

if ($conn->connect_error) {
    die('DB Connection failed: ' . $conn->connect_error);
}

$userId = (int)$_SESSION['user_id'];

// Simple HTML escape
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$successMessage = null;

// -------------------------------
// Auto goal from user weight (kg)
// -------------------------------
$autoGoalLiters = 2.5; // fallback

$weightKg = null;
if ($stmt = $conn->prepare("SELECT weight FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($weightKg);
    $stmt->fetch();
    $stmt->close();
}

if ($weightKg !== null) {
    $w = (float)$weightKg;

    // sanity check: your DB might store kg (preferred) but could be "lbs" for some legacy users.
    // We'll assume kg (as your comment says). If you later add units, we can tighten this.
    if ($w > 40 && $w < 200) {
        // Rough guideline: 0.035 L per kg bodyweight, clamped
        $autoGoalLiters = max(1.8, min(4.0, $w * 0.035));
    }
}

// -------------------------------
// Handle POST
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $logDate      = date('Y-m-d');
    $intakeLiters = isset($_POST['intake_liters']) ? (float)$_POST['intake_liters'] : 0.0;
    $goalLiters   = isset($_POST['goal_liters']) ? (float)$_POST['goal_liters'] : $autoGoalLiters;
    $urineColor   = isset($_POST['urine_color']) ? (int)$_POST['urine_color'] : 0;
    $thirstLevel  = isset($_POST['thirst_level']) ? (int)$_POST['thirst_level'] : 0;
    $notes        = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

    // Validation
    if ($intakeLiters <= 0 || $intakeLiters > 10) {
        $errors[] = 'Enter a realistic water intake for the day (0–10 L).';
    }
    if ($goalLiters <= 0 || $goalLiters > 10) {
        $errors[] = 'Hydration goal looks off. Keep it between 0 and 10 L.';
    }
    if ($urineColor < 1 || $urineColor > 10) {
        $errors[] = 'Select urine color between 1 and 10.';
    }
    if ($thirstLevel < 1 || $thirstLevel > 10) {
        $errors[] = 'Select thirst level between 1 and 10.';
    }

    if (empty($errors)) {
        // Hydration score 0–100
        $ratio = ($goalLiters > 0) ? ($intakeLiters / $goalLiters) : 0.0;
        $ratio = max(0.0, min($ratio, 1.2));

        $intakeScore = min(100.0, $ratio * 100.0);
        $urineScore  = ((11 - $urineColor) / 10) * 100.0;   // 1 (pale) → high score
        $thirstScore = ((11 - $thirstLevel) / 10) * 100.0;  // 1 (not thirsty) → higher score

        $hydrationScore = (int)round(($intakeScore * 0.5) + ($urineScore * 0.25) + ($thirstScore * 0.25));
        $hydrationScore = max(0, min($hydrationScore, 100));

        $sql = "
            INSERT INTO hydration_logs
                (user_id, log_date, intake_liters, goal_liters, urine_color, thirst_level, notes, hydration_score, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        if ($stmt = $conn->prepare($sql)) {
            // types:
            // i user_id
            // s log_date
            // d intake_liters
            // d goal_liters
            // i urine_color
            // i thirst_level
            // s notes
            // i hydration_score
            $stmt->bind_param(
                'isddiisi',
                $userId,
                $logDate,
                $intakeLiters,
                $goalLiters,
                $urineColor,
                $thirstLevel,
                $notes,
                $hydrationScore
            );

            if ($stmt->execute()) {
                $successMessage = "Hydration log saved. Today’s hydration score: <strong>{$hydrationScore}/100</strong>.";
            } else {
                $errors[] = 'Failed to save hydration log: ' . e($stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = 'DB error: ' . e($conn->error);
        }
    }
}

// -------------------------------
// Fetch last 7 logs (user-specific)
// -------------------------------
$recentLogs = [];
if ($stmt = $conn->prepare("
    SELECT log_date, intake_liters, goal_liters, urine_color, thirst_level, hydration_score
    FROM hydration_logs
    WHERE user_id = ?
    ORDER BY log_date DESC
    LIMIT 7
")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $recentLogs[] = $row;
    }
    $stmt->close();
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hydration Tracker | exFIT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --bg-main: #05060a;
            --bg-card: #111320;
            --bg-card-alt: #171a2a;
            --accent-orange: #ff7a1a;
            --accent-purple: #a855ff;
            --text-main: #f5f7ff;
            --text-muted: #9ca3af;
            --danger: #f87171;
            --success: #34d399;
            --module-bg-image: url('../assets/hydration.png');
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #000;
            color: #f5f5f5;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            min-height: 100%;
            overflow-x: hidden;
        }

        .module-shell { position: relative; width: 100%; }

        .module-bg {
            position: fixed;
            inset: 0;
            background-image: var(--module-bg-image);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(6px) brightness(0.4);
            transform: scale(1.06);
            z-index: 0;
        }

        .module-content {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .module-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(to bottom, rgba(0,0,0,0.8), rgba(0,0,0,0.35));
            backdrop-filter: blur(12px);
        }

        .module-header-left { display: flex; flex-direction: column; gap: 0.15rem; }

        .module-title {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .module-subtitle { font-size: 0.75rem; opacity: 0.7; }

        .module-back {
            border: none;
            background: rgba(0,0,0,0.4);
            color: #f5f5f5;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .module-back:hover {
            background: rgba(0,0,0,0.75);
            transform: translateY(-1px);
        }

        .module-main {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 1.2rem 1rem 1.8rem;
        }

        .module-card {
            width: 100%;
            max-width: 1000px;
            border-radius: 1.2rem;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 1.4rem 1.2rem;
            background: rgba(10,10,10,0.74);
            backdrop-filter: blur(18px);
            box-shadow:
                0 18px 40px rgba(0,0,0,0.9),
                0 0 40px rgba(0,0,0,0.8);
        }

        /* ===== Inner layout (same pattern) ===== */
        .page-wrap { display: flex; flex-direction: column; gap: 1.2rem; }

        header.lab-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .brand { display:flex; align-items:center; gap:0.7rem; }
        .brand-logo {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: 2px solid var(--accent-orange);
            box-shadow: 0 0 12px rgba(255,122,26,0.7),
                        0 0 28px rgba(168,85,255,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            color: var(--accent-orange);
        }
        .brand-text h1 { margin:0; font-size: 1.2rem; }
        .brand-text p  { margin:0.15rem 0 0; font-size:0.78rem; opacity:0.8; }
        .brand-text h1 {
            font-size: 1.15rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--accent-orange);
        }
        .brand-text p { font-size: 0.8rem; color: var(--text-muted); }

        .score-pill {
            font-size: 0.78rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.25);
            background: rgba(0,0,0,0.6);
            white-space: nowrap;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1.4fr);
            gap: 1rem;
        }
        @media (max-width: 800px) { .hero { grid-template-columns: 1fr; } }

        .hero-card {
            border-radius: 0.9rem;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.12);
            background: radial-gradient(circle at top left, rgba(255,122,26,0.12), transparent),
                        radial-gradient(circle at bottom right, rgba(190,70,255,0.1), transparent),
                        rgba(10,10,10,0.9);
        }
        .hero-title { margin:0 0 0.4rem; font-size: 1.1rem; }
        .hero-highlight { color: #ffb347; }
        .hero-sub { margin:0; font-size:0.83rem; opacity:0.9; }
        .tagline { margin-top:0.6rem; font-size:0.8rem; opacity:0.85; }

        .score-card { display:flex; flex-direction:column; gap:0.6rem; }
        .score-ring {
            border-radius: 0.9rem;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 0.7rem 0.8rem;
            background: rgba(0,0,0,0.6);
        }
        .score-label { font-size:0.75rem; opacity:0.7; }
        .score-value { font-size:1.2rem; font-weight:700; margin-top:0.25rem; }
        .score-caption { font-size:0.72rem; opacity:0.75; margin-top:0.25rem; }

        .layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.6fr);
            gap: 1rem;
        }
        @media (max-width: 900px) { .layout-grid { grid-template-columns: 1fr; } }

        .card {
            border-radius: 0.9rem;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(12,12,12,0.95);
        }
        .card-header { margin-bottom: 0.6rem; }
        .card-title { margin:0; font-size:0.95rem; }
        .card-subtitle { margin:0.25rem 0 0; font-size:0.78rem; opacity:0.8; }

        label { font-size:0.78rem; display:block; margin:0.55rem 0 0.15rem; }
        input[type="number"], input[type="range"], textarea {
            width: 100%;
        }
        input[type="number"], textarea {
            padding: 0.4rem 0.5rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(148,163,184,0.5);
            background: rgba(15,15,15,0.9);
            color: #f5f5f5;
            font-size: 0.82rem;
        }
        textarea { min-height: 72px; resize: vertical; }

        .slider-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.72rem;
            opacity: 0.8;
            margin-top: 0.15rem;
        }

        .inline-row { display:flex; gap:0.6rem; flex-wrap: wrap; }
        .inline-row > button { flex: 0 0 auto; }

        .btn-primary {
            border: none;
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            background: linear-gradient(135deg, #ff7a1a, #ff3b6a);
            color: #050505;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            box-shadow: 0 8px 24px rgba(255,122,26,0.5);
            margin-top: 0.7rem;
        }

        .btn-secondary {
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.35);
            color: #f5f5f5;
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            font-size: 0.78rem;
            cursor: pointer;
            margin-top: 0.7rem;
        }

        .feedback {
            border-radius: 0.7rem;
            padding: 0.7rem 0.75rem;
            background: rgba(15,23,42,0.9);
            border: 1px solid rgba(148,163,184,0.6);
            font-size: 0.78rem;
            margin-bottom: 0.6rem;
        }
        .feedback.success {
            border-color: rgba(52,211,153,0.6);
            background: rgba(6,95,70,0.35);
        }
        .feedback.error {
            border-color: rgba(248,113,113,0.6);
            background: rgba(127,29,29,0.25);
        }

        .pill {
            display:inline-block;
            border-radius: 999px;
            padding: 0.05rem 0.6rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 1px solid rgba(148,163,184,0.55);
            opacity: 0.95;
        }

        .history-wrap { overflow-x:auto; }
        table { width:100%; border-collapse: collapse; font-size:0.78rem; }
        th, td { padding:0.35rem 0.4rem; border-bottom:1px solid rgba(51,65,85,0.8); text-align:left; }
        th { font-weight:600; font-size:0.74rem; opacity:0.8; }

        footer.lab-footer {
            margin-top: 0.9rem;
            font-size: 0.72rem;
            opacity: 0.7;
            text-align: center;
        }
        .urine-swatch{
    display:inline-block;
    width: 18px;
    height: 12px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.25);
    box-shadow: 0 0 10px rgba(0,0,0,0.35) inset;
    vertical-align: middle;
}
    </style>
</head>
<body>

<div class="module-shell">
    <div class="module-bg"></div>

    <div class="module-content">
        <header class="module-header">
            <div class="module-header-left">
                <div class="module-title">Hydration Tracker</div>
                <div class="module-subtitle">Hydrate the engine before you floor it.</div>
            </div>
            <button class="module-back" type="button" onclick="window.location.href='../index.php'">
                ‹ Modules
            </button>
        </header>

        <main class="module-main">
            <section class="module-card">
                <div class="page-wrap">

                    <header class="lab-header">
                        <div class="brand">
                            <div class="brand-logo">exFIT</div>
                            <div class="brand-text">
                                <h1>Hydration · Daily Check-In</h1>
                                <p>Intake + signals → score → honest patterns.</p>
                            </div>
                        </div>
                        <div class="score-pill">
                            Gray Mentality • <strong>Hydrate like it matters</strong>
                        </div>
                    </header>

                    <section class="hero">
                        <div class="hero-card">
                            <h2 class="hero-title">
                                Prime your <span class="hero-highlight">performance</span>
                            </h2>
                            <p class="hero-sub">
                                Under-hydrated days show up later: headaches, trash sleep, stalled strength,
                                cranky joints. This check-in keeps the pattern visible.
                            </p>
                            <p class="tagline">
                                Suggested goal: <span class="hero-highlight"><?php echo e(number_format($autoGoalLiters, 1)); ?> L/day</span>
                                <?php if ($weightKg !== null): ?>
                                    <span style="opacity:0.75;">(from saved bodyweight)</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="hero-card score-card">
                            <div class="score-ring">
                                <div class="score-label">Live hydration estimate</div>
                                <div class="score-value" id="score-value">~0</div>
                                <div class="score-caption" id="score-caption">
                                    Start logging—your trend will tell you what “normal” looks like.
                                </div>
                            </div>

                            <div class="score-ring">
                                <div class="score-label">Urine color guide</div>
                                <div class="score-caption">
                                    • 1–3 = great<br>
                                    • 3-6= okay<br>
                                    • 7–10 = drink up
                                </div>
                            </div>
                        </div>
                    </section>

                    <?php if (!empty($errors)): ?>
                        <div class="feedback error">
                            <?php foreach ($errors as $msg): ?>
                                <div>• <?php echo e($msg); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="feedback success">
                            <?php echo $successMessage; ?>
                        </div>
                    <?php endif; ?>

                    <section class="layout-grid">
                        <!-- Left card: form -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Today’s Hydration</h3>
                                <p class="card-subtitle">Log intake and two quick body signals.</p>
                            </div>

                            <form method="post" id="hydration-form" autocomplete="off" novalidate>
                                <label for="intake_liters">
                                    Total intake today (L)
                                    <span class="pill" style="margin-left:0.35rem;" id="intake-display">0.0 L</span>
                                </label>
                                <input
                                    type="number"
                                    name="intake_liters"
                                    id="intake_liters"
                                    min="0"
                                    max="10"
                                    step="0.1"
                                    value="<?php echo e($_POST['intake_liters'] ?? '0'); ?>"
                                    required
                                >

                                <label for="goal_liters">
                                    Daily goal (L)
                                    <span class="pill" style="margin-left:0.35rem; opacity:0.85;">
                                        auto <?php echo e(number_format($autoGoalLiters, 1)); ?>
                                    </span>
                                </label>
                                <input
                                    type="number"
                                    name="goal_liters"
                                    id="goal_liters"
                                    min="0.5"
                                    max="10"
                                    step="0.1"
                                    value="<?php echo e($_POST['goal_liters'] ?? number_format($autoGoalLiters, 1, '.', '')); ?>"
                                >

                                <label for="urine_color">
                                Urine color (1–10)
                                <span class="pill" style="margin-left:0.35rem;" id="urine-display">
                                    <?php echo e((string)($_POST['urine_color'] ?? '5')); ?>
                                </span>
                                <span class="pill" style="margin-left:0.35rem; border-color: rgba(255,255,255,0.22);">
                                    <span class="urine-swatch" id="urine-swatch" aria-hidden="true"></span>
                                    <span id="urine-label" style="margin-left:0.35rem; opacity:0.9;">—</span>
                                </span>
                            </label>

                            <input
                                type="range"
                                name="urine_color"
                                id="urine_color"
                                min="1"
                                max="10"
                                step="1"
                                value="<?php echo e((string)($_POST['urine_color'] ?? '5')); ?>"
                            >

                            <div class="slider-meta">
                                <span>1 — very pale</span>
                                <span>10 — very dark</span>
                            </div>

                                <label for="thirst_level">
                        Thirst level (1–10)
                        <span class="pill" style="margin-left:0.35rem;" id="thirst-display">
                            <?php echo e((string)($_POST['thirst_level'] ?? '5')); ?>
                        </span>
                                </label>
                    <input
                        type="range"
                        name="thirst_level"
                        id="thirst_level"
                        min="1"
                        max="10"
                        step="1"
                        value="<?php echo e((string)($_POST['thirst_level'] ?? '5')); ?>"
                    >
                    <div class="slider-meta">
                        <span>1 — not thirsty</span>
                        <span>10 — extremely thirsty</span>
                    </div>

                                <label for="notes">Notes (optional)</label>
                                <textarea
                                    name="notes"
                                    id="notes"
                                    placeholder="Sauna, cardio, alcohol, travel, long shift, etc."
                                ><?php echo e($_POST['notes'] ?? ''); ?></textarea>

                                <div class="inline-row">
                                    <button type="submit" class="btn-primary">Save Log <span>➜</span></button>
                                    <button type="button" class="btn-secondary" onclick="resetForm()">Reset</button>
                                </div>
                            </form>
                        </article>

                        <!-- Right card: guidance + history -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">How to read your score</h3>
                                <p class="card-subtitle">Not judgment. Just pattern awareness.</p>
                            </div>

                            <div class="feedback" style="margin-bottom:0.8rem;">
                                <strong>Score bands</strong><br>
                                <span style="opacity:0.9;">
                                    80–100: fully primed • 60–79: decent • 40–59: low • &lt;40: depleted
                                </span>
                            </div>

                            <div class="card-header" style="margin-top:0.2rem;">
                                <h3 class="card-title">Last 7 days</h3>
                                <p class="card-subtitle">Quick scan for “dry streaks”.</p>
                            </div>

                            <?php if (empty($recentLogs)): ?>
                                <div class="feedback">No hydration logs yet. Save your first entry to start tracking.</div>
                            <?php else: ?>
                                <div class="history-wrap">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Intake</th>
                                            <th>Goal</th>
                                            <th>Urine</th>
                                            <th>Thirst</th>
                                            <th>Score</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($recentLogs as $row): ?>
                                            <tr>
                                                <td><?php echo e((string)$row['log_date']); ?></td>
                                                <td><?php echo e(number_format((float)$row['intake_liters'], 1)); ?> L</td>
                                                <td><?php echo e(number_format((float)$row['goal_liters'], 1)); ?> L</td>
                                                <td><?php echo (int)$row['urine_color']; ?></td>
                                                <td><?php echo (int)$row['thirst_level']; ?></td>
                                                <td><?php echo (int)$row['hydration_score']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </article>
                    </section>

                    <footer class="lab-footer">
                        exFIT • Gray Mentality — Hydrate the engine before you floor it.
                    </footer>

                </div>
            </section>
        </main>
    </div>
</div>

<script>
    const intakeEl = document.getElementById('intake_liters');
    const goalEl   = document.getElementById('goal_liters');
    const urineEl  = document.getElementById('urine_color');
    const thirstEl = document.getElementById('thirst_level');

    const intakeDisplay = document.getElementById('intake-display');
    const urineDisplay  = document.getElementById('urine-display');
    const thirstDisplay = document.getElementById('thirst-display');

    const scoreValueEl   = document.getElementById('score-value');
    const scoreCaptionEl = document.getElementById('score-caption');
    const urineSwatch = document.getElementById('urine-swatch');
const urineLabel  = document.getElementById('urine-label');

// 1–10 color scale (pale straw → deep amber/brown)
// (Not a medical tool; just a visual guide.)
const urineScale = [
  { v:1,  hex:'#FFFDE7', label:'very pale' },
  { v:2,  hex:'#FFF9C4', label:'pale straw' },
  { v:3,  hex:'#FFF59D', label:'straw' },
  { v:4,  hex:'#FFEE58', label:'light yellow' },
  { v:5,  hex:'#FFE082', label:'yellow' },
  { v:6,  hex:'#FFD54F', label:'gold' },
  { v:7,  hex:'#FFCA28', label:'deep yellow' },
  { v:8,  hex:'#FFB300', label:'amber' },
  { v:9,  hex:'#F59E0B', label:'dark amber' },
  { v:10, hex:'#B45309', label:'very dark' }
];

function urineInfo(v){
  const item = urineScale.find(x => x.v === v) || urineScale[4];
  return item;
}


    function clamp(n, min, max) {
        return Math.max(min, Math.min(n, max));
    }

    function computeHydrationScore(intake, goal, urine, thirst) {
    let ratio = (goal > 0) ? (intake / goal) : 0;
    ratio = clamp(ratio, 0, 1.2);

    const intakeScore = Math.min(100, ratio * 100);
    const urineScore  = ((11 - urine) / 10) * 100;
    const thirstScore = ((11 - thirst) / 10) * 100;

    return clamp(Math.round(intakeScore * 0.5 + urineScore * 0.25 + thirstScore * 0.25), 0, 100);
}


    function updateUI() {
        const intake = parseFloat(intakeEl.value || '0');
        const goal   = parseFloat(goalEl.value || '0');
        const urine  = parseInt(urineEl.value || '3', 10);
        const thirst = parseInt(thirstEl.value || '3', 10);

        intakeDisplay.textContent = intake.toFixed(1) + ' L';
        urineDisplay.textContent  = String(urine);
        const ui = urineInfo(urine);
            if (urineSwatch) urineSwatch.style.background = ui.hex;
            if (urineLabel)  urineLabel.textContent = ui.label;

        thirstDisplay.textContent = String(thirst);

        const score = computeHydrationScore(intake, goal, urine, thirst);
        scoreValueEl.textContent = '~' + score;

        let caption = 'Start logging—your trend will tell you what “normal” looks like.';
        if (score >= 80) caption = 'Excellent hydration base. Perfect for heavy or long sessions.';
        else if (score >= 60) caption = 'Not bad. Keep a bottle nearby and top up.';
        else if (score >= 40) caption = 'You’re under target. Add fluids and consider easing intensity.';
        else caption = 'Hydration is poor. Focus today on water, electrolytes, and easy movement.';

        scoreCaptionEl.textContent = caption;
    }

    function resetForm() {
        intakeEl.value = 0;
        urineEl.value  = 5;
        thirstEl.value = 5;
        document.getElementById('notes').value = '';
        updateUI();
    }

    [intakeEl, goalEl, urineEl, thirstEl].forEach(el => el.addEventListener('input', updateUI));
    updateUI();
</script>

</body>
</html>
