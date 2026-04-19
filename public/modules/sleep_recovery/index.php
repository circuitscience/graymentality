<?php
declare(strict_types=1);

/**
 * Sleep & Recovery Check-In (exFIT)
 * ------------------------------------------------------------------------
 * FILE:  /public/modules/sleep_recovery/index.php
 *
 * PURPOSE
 *  - Daily “readiness” snapshot:
 *      • hours slept
 *      • sleep quality (1–5)
 *      • morning energy (1–5)
 *      • mood (1–5)
 *      • muscle soreness (1–5)  (inverse-scored)
 *      • motivation (1–5)
 *      • notes
 *  - Compute a simple 0–100 recovery_score
 *  - Save to: sleep_recovery_logs
 *  - Show last 7 logs for this user
 *
 * UX / LAYOUT (MATCHES GRIP STRENGTH / FRAME POTENTIAL)
 *  - module shell w/ blurred background image
 *  - translucent module card
 *  - header w/ “‹ Modules” back button
 *  - two-column layout: form + guidance/history
 *
 * DATABASE EXPECTATIONS
 *  Table: sleep_recovery_logs
 *  Columns (minimum):
 *    id (PK), user_id, log_date, hours_slept,
 *    sleep_quality, energy_am, mood, soreness, motivation,
 *    notes, recovery_score, created_at
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$userId = (int)($authUser['id'] ?? 0);

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    die('Database connection not available.');
}

if ($conn->connect_error) {
    http_response_code(500);
    die('DB Connection failed: ' . $conn->connect_error);
}

// Simple HTML escape
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ---------- Scoring helpers ----------
function hours_score(float $hours): int
{
    if ($hours <= 0) return 0;

    // Sweet spot: 7–9 hours
    if ($hours >= 7.0 && $hours <= 9.0) {
        return 100;
    }

    // Penalty: 10 pts per hour away from 8
    $diff = abs($hours - 8.0);
    $score = (int)round(100 - ($diff * 10.0));
    return max(0, min(100, $score));
}

function subjective_score(int $sleepQ, int $energy, int $mood, int $soreness, int $motivation): int
{
    // Convert soreness (1–5) into inverse score (5 best):
    // soreness=1 -> 5, soreness=5 -> 1
    $inverseSoreness = 6 - $soreness;

    // Average remains 1–5
    $avg = ($sleepQ + $energy + $mood + $inverseSoreness + $motivation) / 5.0;

    // Map 1..5 -> 0..100
    $score = (int)round((($avg - 1.0) / 4.0) * 100.0);
    return max(0, min(100, $score));
}

function recovery_score(int $hoursScore, int $subjectiveScore): int
{
    return (int)round(($hoursScore * 0.5) + ($subjectiveScore * 0.5));
}

// ---------- POST handling ----------
$errors = [];
$feedback = '';
$todayScore = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logDate      = $_POST['log_date'] ?? date('Y-m-d');
    $hoursSlept   = isset($_POST['hours_slept']) ? (float)$_POST['hours_slept'] : 0.0;

    $sleepQuality = isset($_POST['sleep_quality']) ? (int)$_POST['sleep_quality'] : 0;
    $energyAm     = isset($_POST['energy_am']) ? (int)$_POST['energy_am'] : 0;
    $mood         = isset($_POST['mood']) ? (int)$_POST['mood'] : 0;
    $soreness     = isset($_POST['soreness']) ? (int)$_POST['soreness'] : 0;
    $motivation   = isset($_POST['motivation']) ? (int)$_POST['motivation'] : 0;

    $notes        = trim($_POST['notes'] ?? '');

    // Validation
    if ($hoursSlept < 0 || $hoursSlept > 14) {
        $errors[] = 'Please enter a realistic number of hours slept (0–14).';
    }
    foreach ([
        'Sleep quality' => $sleepQuality,
        'Morning energy' => $energyAm,
        'Mood' => $mood,
        'Soreness' => $soreness,
        'Motivation' => $motivation
    ] as $label => $val) {
        if ($val < 1 || $val > 5) {
            $errors[] = $label . ' must be between 1 and 5.';
        }
    }

    // Compute + insert
    if (empty($errors)) {
        $hScore = hours_score($hoursSlept);
        $sScore = subjective_score($sleepQuality, $energyAm, $mood, $soreness, $motivation);
        $score  = recovery_score($hScore, $sScore);

        $todayScore = $score;

        if ($stmt = $conn->prepare("
            INSERT INTO sleep_recovery_logs
                (user_id, log_date, hours_slept, sleep_quality, energy_am, mood, soreness, motivation, notes, recovery_score, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")) {
            // i user_id
            // s log_date
            // d hours_slept
            // i sleep_quality
            // i energy_am
            // i mood
            // i soreness
            // i motivation
            // s notes
            // i recovery_score
            $stmt->bind_param(
                "isdiiiii si",
                $userId,
                $logDate,
                $hoursSlept,
                $sleepQuality,
                $energyAm,
                $mood,
                $soreness,
                $motivation,
                $notes,
                $score
            );

            if ($stmt->execute()) {
                $feedback = 'Saved. Today’s recovery score: <strong>' . e((string)$score) . '/100</strong>.';
            } else {
                $errors[] = 'Failed to save log: ' . e($stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = 'DB error: ' . e($conn->error);
        }
    }
}

// ---------- Fetch recent logs (last 7 for this user) ----------
$recentLogs = [];
if ($stmt = $conn->prepare("
    SELECT log_date, hours_slept, sleep_quality, energy_am, mood, soreness, motivation, recovery_score
    FROM sleep_recovery_logs
    WHERE user_id = ?
    ORDER BY log_date DESC, id DESC
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

// ---------- Latest score pill (optional) ----------
$latestScore = null;
if (!empty($recentLogs)) {
    $latestScore = (int)$recentLogs[0]['recovery_score'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sleep & Recovery | exFIT</title>
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

            /* change this image path to whatever you use for sleep */
            --module-bg-image: url('../assets/recovery8.png');
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

        .module-header-left {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .module-title {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .module-subtitle {
            font-size: 0.75rem;
            opacity: 0.7;
        }

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

        /* ===== inner layout (matching grip) ===== */
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
        .brand-text p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

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
        input[type="number"], input[type="date"], textarea {
            width: 100%;
            padding: 0.4rem 0.5rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(148,163,184,0.5);
            background: rgba(15,15,15,0.9);
            color: #f5f5f5;
            font-size: 0.82rem;
        }

        input[type="range"] { width: 100%; margin-top: 0.15rem; }
        textarea { min-height: 72px; resize: vertical; }

        .inline-row { display:flex; gap:0.6rem; align-items:center; justify-content:space-between; }
        .inline-row .muted { font-size:0.72rem; opacity:0.75; }
        .range-row { display:flex; align-items:center; justify-content:space-between; gap:0.6rem; }
        .range-row .val { font-size:0.72rem; opacity:0.8; white-space:nowrap; }

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

        .feedback {
            border-radius: 0.7rem;
            padding: 0.7rem 0.75rem;
            background: rgba(15,23,42,0.9);
            border: 1px solid rgba(148,163,184,0.6);
            font-size: 0.78rem;
            margin-bottom: 0.6rem;
        }
        .feedback.error {
            border-color: rgba(248,113,113,0.55);
            background: rgba(127,29,29,0.35);
        }
        .feedback.success {
            border-color: rgba(52,211,153,0.55);
            background: rgba(6,95,70,0.35);
        }

        .history-wrap { overflow-x:auto; }
        table { width:100%; border-collapse: collapse; font-size:0.78rem; }
        th, td { padding:0.35rem 0.4rem; border-bottom:1px solid rgba(51,65,85,0.8); text-align:left; }
        th { font-weight:600; font-size:0.74rem; opacity:0.8; }

        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 0.1rem 0.6rem;
            font-size: 0.72rem;
            border: 1px solid rgba(148,163,184,0.55);
            opacity: 0.95;
            white-space: nowrap;
        }
        .b-green { border-color: rgba(52,211,153,0.7); color: #bbf7d0; }
        .b-amber { border-color: rgba(251,191,36,0.7); color: #fde68a; }
        .b-red   { border-color: rgba(248,113,113,0.7); color: #fecaca; }

        footer.lab-footer {
            margin-top: 0.9rem;
            font-size: 0.72rem;
            opacity: 0.7;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="module-shell">
    <div class="module-bg"></div>

    <div class="module-content">
        <header class="module-header">
            <div class="module-header-left">
                <div class="module-title">Sleep & Recovery</div>
                <div class="module-subtitle">Log readiness. Spot patterns. Train smarter.</div>
            </div>
            <button class="module-back" type="button" onclick="window.location.href='/modules/index.php'">‹ Modules</button>
        </header>

        <main class="module-main">
            <section class="module-card">
                <div class="page-wrap">

                    <header class="lab-header">
                        <div class="brand">
                            <div class="brand-logo">exFIT</div>
                            <div class="brand-text">
                                <h1>Sleep & Recovery Console</h1>
                                <p>Quick check-in → simple score → better decisions.</p>
                            </div>
                        </div>
                        <div class="score-pill">
                            Gray Mentality •
                            <strong>
                                <?php echo ($latestScore !== null) ? 'Latest: ' . e((string)$latestScore) . '/100' : 'Log today to start'; ?>
                            </strong>
                        </div>
                    </header>

                    <section class="hero">
                        <div class="hero-card">
                            <h2 class="hero-title">
                                Are you recovered… or just <span class="hero-highlight">used to being tired</span>?
                            </h2>
                            <p class="hero-sub">
                                This isn’t about perfection. It’s about trendlines: sleep, mood, soreness, motivation —
                                and how they show up in your training performance over time.
                            </p>
                            <p class="tagline">
                                If your recovery runs low, keep the session honest: warm-up longer, respect RIR, and
                                earn the heavy days.
                            </p>
                        </div>

                        <div class="hero-card score-card">
                            <div class="score-ring">
                                <div class="score-label">Live estimate</div>
                                <div class="score-value" id="liveScoreValue">~70</div>
                                <div class="score-caption" id="liveScoreCaption">
                                    You’re in a decent spot. Warm up well and listen to your joints.
                                </div>
                            </div>

                            <div class="score-ring">
                                <div class="score-label">Quick interpretation</div>
                                <div class="score-caption">
                                    <span class="badge b-green">80–100</span> Train as planned<br>
                                    <span class="badge b-amber">60–79</span> Train, keep top sets shy<br>
                                    <span class="badge b-amber">40–59</span> Back off intensity/volume<br>
                                    <span class="badge b-red">&lt; 40</span> Recovery first
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="layout-grid">
                        <!-- Left: Form -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Daily Check-In</h3>
                                <p class="card-subtitle">Answer honestly. Your future self will thank you.</p>
                            </div>

                            <?php if (!empty($errors)): ?>
                                <div class="feedback error">
                                    <?php foreach ($errors as $msg): ?>
                                        • <?php echo e((string)$msg); ?><br>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($feedback): ?>
                                <div class="feedback success"><?php echo $feedback; ?></div>
                            <?php endif; ?>

                            <form method="post" id="recovery-form" autocomplete="off" novalidate>
                                <label for="log_date">Log Date</label>
                                <input type="date" id="log_date" name="log_date"
                                       value="<?php echo e($_POST['log_date'] ?? date('Y-m-d')); ?>">

                                <div class="inline-row" style="margin-top:0.55rem;">
                                    <label for="hours_slept" style="margin:0;">Hours slept</label>
                                    <div class="muted"><span id="hoursDisplay">8.0</span> hrs</div>
                                </div>
                                <input type="number" step="0.5" min="0" max="14"
                                       id="hours_slept" name="hours_slept"
                                       value="<?php echo e((string)($_POST['hours_slept'] ?? '8')); ?>" required>

                                <label for="sleep_quality">Sleep quality (1–5)</label>
                                <div class="range-row">
                                    <input type="range" id="sleep_quality" name="sleep_quality" min="1" max="5"
                                           value="<?php echo e((string)($_POST['sleep_quality'] ?? '3')); ?>">
                                    <div class="val"><span id="sqDisplay">3</span>/5</div>
                                </div>

                                <label for="energy_am">Morning energy (1–5)</label>
                                <div class="range-row">
                                    <input type="range" id="energy_am" name="energy_am" min="1" max="5"
                                           value="<?php echo e((string)($_POST['energy_am'] ?? '3')); ?>">
                                    <div class="val"><span id="enDisplay">3</span>/5</div>
                                </div>

                                <label for="mood">Mood (1–5)</label>
                                <div class="range-row">
                                    <input type="range" id="mood" name="mood" min="1" max="5"
                                           value="<?php echo e((string)($_POST['mood'] ?? '3')); ?>">
                                    <div class="val"><span id="moDisplay">3</span>/5</div>
                                </div>

                                <label for="soreness">Muscle soreness (1–5)</label>
                                <div class="range-row">
                                    <input type="range" id="soreness" name="soreness" min="1" max="5"
                                           value="<?php echo e((string)($_POST['soreness'] ?? '3')); ?>">
                                    <div class="val"><span id="soDisplay">3</span>/5</div>
                                </div>

                                <label for="motivation">Motivation to train (1–5)</label>
                                <div class="range-row">
                                    <input type="range" id="motivation" name="motivation" min="1" max="5"
                                           value="<?php echo e((string)($_POST['motivation'] ?? '3')); ?>">
                                    <div class="val"><span id="mtDisplay">3</span>/5</div>
                                </div>

                                <label for="notes">Notes (optional)</label>
                                <textarea id="notes" name="notes" placeholder="Stress, late workout, alcohol, travel, etc."><?php
                                    echo e((string)($_POST['notes'] ?? ''));
                                ?></textarea>

                                <button type="submit" class="btn-primary">
                                    Save Check-In <span>➜</span>
                                </button>
                            </form>
                        </article>

                        <!-- Right: Guidance + history -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">How to use this</h3>
                                <p class="card-subtitle">The score is a nudge — performance trend is the truth.</p>
                            </div>

                            <div class="feedback" id="liveSummary" style="margin-top:0.4rem;">
                                <strong id="liveBadgeText">Good to train</strong><br>
                                <span style="opacity:0.85;" id="liveSummaryText">
                                    You’re in a decent spot. Warm up well and listen to your joints.
                                </span>
                            </div>

                            <div class="card-header" style="margin-top:1rem;">
                                <h3 class="card-title">Last 7 days</h3>
                                <p class="card-subtitle">Your recent logs (this user only).</p>
                            </div>

                            <?php if (empty($recentLogs)): ?>
                                <div class="feedback">No logs yet. Save your first entry to start tracking patterns.</div>
                            <?php else: ?>
                                <div class="history-wrap">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Hrs</th>
                                            <th>Qual</th>
                                            <th>Energy</th>
                                            <th>Sore</th>
                                            <th>Score</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($recentLogs as $row): ?>
                                            <tr>
                                                <td><?php echo e((string)$row['log_date']); ?></td>
                                                <td><?php echo e((string)$row['hours_slept']); ?></td>
                                                <td><?php echo (int)$row['sleep_quality']; ?></td>
                                                <td><?php echo (int)$row['energy_am']; ?></td>
                                                <td><?php echo (int)$row['soreness']; ?></td>
                                                <td><?php echo (int)$row['recovery_score']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <footer class="lab-footer">
                                exFIT • Gray Mentality — Sleep is part of the lift.
                            </footer>
                        </article>
                    </section>

                </div>
            </section>
        </main>
    </div>
</div>

<script>
    const hoursInput = document.getElementById('hours_slept');
    const sqInput    = document.getElementById('sleep_quality');
    const enInput    = document.getElementById('energy_am');
    const moInput    = document.getElementById('mood');
    const soInput    = document.getElementById('soreness');
    const mtInput    = document.getElementById('motivation');

    const hoursDisplay = document.getElementById('hoursDisplay');
    const sqDisplay = document.getElementById('sqDisplay');
    const enDisplay = document.getElementById('enDisplay');
    const moDisplay = document.getElementById('moDisplay');
    const soDisplay = document.getElementById('soDisplay');
    const mtDisplay = document.getElementById('mtDisplay');

    const liveScoreValue = document.getElementById('liveScoreValue');
    const liveScoreCaption = document.getElementById('liveScoreCaption');

    const liveBadgeText = document.getElementById('liveBadgeText');
    const liveSummaryText = document.getElementById('liveSummaryText');

    function calcHoursScore(hours) {
        if (!hours || hours <= 0) return 0;
        if (hours >= 7 && hours <= 9) return 100;
        const diff = Math.abs(hours - 8);
        return Math.max(0, Math.min(100, Math.round(100 - diff * 10)));
    }

    function calcSubjectiveScore(sq, en, mo, so, mt) {
        const invSo = 6 - so;
        const avg = (sq + en + mo + invSo + mt) / 5.0;
        const score = Math.round(((avg - 1) / 4) * 100);
        return Math.max(0, Math.min(100, score));
    }

    function updateLive() {
        const hours = parseFloat(hoursInput.value || '0');
        const sq = parseInt(sqInput.value || '1', 10);
        const en = parseInt(enInput.value || '1', 10);
        const mo = parseInt(moInput.value || '1', 10);
        const so = parseInt(soInput.value || '1', 10);
        const mt = parseInt(mtInput.value || '1', 10);

        hoursDisplay.textContent = (hours || 0).toFixed(1);
        sqDisplay.textContent = sq;
        enDisplay.textContent = en;
        moDisplay.textContent = mo;
        soDisplay.textContent = so;
        mtDisplay.textContent = mt;

        const hScore = calcHoursScore(hours);
        const sScore = calcSubjectiveScore(sq, en, mo, so, mt);
        const score = Math.round((hScore * 0.5) + (sScore * 0.5));

        liveScoreValue.textContent = "~" + score;

        let badge = "Good to train";
        let caption = "You’re in a decent spot. Warm up well and listen to your joints.";

        if (score >= 80) {
            badge = "Green light";
            caption = "Full send is on the table. Keep RIR honest and don’t rush warm-ups.";
        } else if (score >= 60) {
            badge = "Caution";
            caption = "Train, but keep top sets shy of failure. Let the warm-up decide the day.";
        } else if (score >= 40) {
            badge = "Easy day";
            caption = "Technique, pump work, walking, mobility. Save heavy iron for better readiness.";
        } else {
            badge = "Recovery first";
            caption = "Sleep, food, low-stress movement. Heavy lifting can wait.";
        }

        liveBadgeText.textContent = badge;
        liveSummaryText.textContent = caption;

        liveScoreCaption.textContent = caption;
    }

    [hoursInput, sqInput, enInput, moInput, soInput, mtInput].forEach(el => {
        el.addEventListener('input', updateLive);
    });

    updateLive();
</script>

</body>
</html>
