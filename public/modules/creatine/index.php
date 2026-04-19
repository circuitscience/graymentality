<?php
declare(strict_types=1);

/**
 * exFIT • Creatine Tracker + Adherence Scoring + Logging (Standalone)
 * =============================================================================
 * FILE
 *   /public/modules/creatine/index.php  (or any standalone location)
 *
 * PURPOSE
 *   Standalone creatine intake tracker that:
 *     - Logs daily creatine intake (grams), goal, doses, timing, notes
 *     - Calculates a simple adherence score (0–100)
 *     - Writes a row into `creatine_logs`
 *     - Displays the most recent 14 logs
 *     - Shows a live “estimated score” preview (JavaScript) before saving
 *
 * WHAT MAKES IT “STANDALONE”
 *   - No site-wide nav menu / hub links
 *   - Self-contained CSS + JS in this file
 *   - Minimal header with exFIT mark + page title only
 *
 * DATABASE EXPECTATIONS
 *   Table: creatine_logs
 *   Suggested schema (adjust to your DB conventions):
 *
 *   CREATE TABLE IF NOT EXISTS creatine_logs (
 *     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     user_id INT NULL,
 *     log_date DATE NOT NULL,
 *     grams_taken DECIMAL(5,2) NOT NULL,
 *     goal_grams DECIMAL(5,2) NOT NULL,
 *     doses TINYINT UNSIGNED NOT NULL,
 *     timing VARCHAR(32) NOT NULL,
 *     notes TEXT NULL,
 *     adherence_score TINYINT UNSIGNED NOT NULL,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * ADHERENCE SCORE (0–100)
 *   ratio = grams_taken / goal_grams
 *   - grams_taken <= 0 OR goal <= 0 => 0
 *   - ratio clamped to [0.0, 1.5]
 *   - if ratio in [0.8, 1.2] => 100
 *   - else score = round(ratio * 100), clamped 0–100
 *
 * NOTES
 *   - If you want to require login, uncomment session_start() and add a guard.
 *   - user_id can be NULL (if anonymous). Ensure creatine_logs.user_id allows NULL.
 * =============================================================================
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../includes/session_guard.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('DB connection not available.');
}

if ($conn->connect_error) {
    die('DB Connection failed: ' . $conn->connect_error);
}

$userId = (int)($authUser['id'] ?? 0);

// Simple HTML escape
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$successMessage = null;

// Defaults
$defaultGoalG = 5.0; // typical maintenance dose
$defaultDoses = 1;

// -------------------------------
// Handle POST
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $logDate    = date('Y-m-d');
    $gramsTaken = isset($_POST['grams_taken']) ? (float)$_POST['grams_taken'] : 0.0;
    $goalGrams  = isset($_POST['goal_grams']) ? (float)$_POST['goal_grams'] : $defaultGoalG;
    $doses      = isset($_POST['doses']) ? (int)$_POST['doses'] : $defaultDoses;
    $timing     = isset($_POST['timing']) ? trim((string)$_POST['timing']) : 'any';
    $notes      = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

    // Validation
    if ($gramsTaken < 0 || $gramsTaken > 30) {
        $errors[] = 'Creatine intake should be between 0 and 30 g.';
    }
    if ($goalGrams <= 0 || $goalGrams > 30) {
        $errors[] = 'Goal should be between 1 and 30 g.';
    }
    if ($doses < 0 || $doses > 6) {
        $errors[] = 'Doses per day should be between 0 and 6.';
    }
    if ($timing === '') {
        $timing = 'any';
    }

    // Adherence score 0–100
    // 0g = 0, 80–120% of goal = 100, otherwise scaled.
    $adherenceScore = 0;
    if (empty($errors)) {
        if ($gramsTaken <= 0.0 || $goalGrams <= 0.0) {
            $adherenceScore = 0;
        } else {
            $ratio = $gramsTaken / $goalGrams;
            $ratio = max(0.0, min($ratio, 1.5));

            if ($ratio >= 0.8 && $ratio <= 1.2) {
                $adherenceScore = 100;
            } else {
                $adherenceScore = (int)round($ratio * 100);
            }

            $adherenceScore = max(0, min($adherenceScore, 100));
        }

        // Save
        $sql = "
            INSERT INTO creatine_logs
                (user_id, log_date, grams_taken, goal_grams, doses, timing, notes, adherence_score, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        if ($stmt = $conn->prepare($sql)) {
            // types:
            // i user_id
            // s log_date
            // d grams_taken
            // d goal_grams
            // i doses
            // s timing
            // s notes
            // i adherence_score
            $stmt->bind_param(
                'isddissi',
                $userId,
                $logDate,
                $gramsTaken,
                $goalGrams,
                $doses,
                $timing,
                $notes,
                $adherenceScore
            );

            if ($stmt->execute()) {
                $successMessage = "Creatine log saved. Today’s adherence score: <strong>{$adherenceScore}/100</strong>.";
            } else {
                $errors[] = 'Failed to save creatine log: ' . e($stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = 'DB error: ' . e($conn->error);
        }
    }
}

// -------------------------------
// Fetch last 14 days (user-specific)
// -------------------------------
$recentLogs = [];
if ($stmt = $conn->prepare("
    SELECT log_date, grams_taken, goal_grams, doses, timing, adherence_score
    FROM creatine_logs
    WHERE user_id = ?
    ORDER BY log_date DESC
    LIMIT 14
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
    <title>Creatine Tracker | exFIT</title>
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
            --module-bg-image: url('../assets/creatine.png');
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

        .module-shell {
            position: relative;
            width: 100%;
        }

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
        input[type="number"], select, textarea {
            width: 100%;
            padding: 0.4rem 0.5rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(148,163,184,0.5);
            background: rgba(15,15,15,0.9);
            color: #f5f5f5;
            font-size: 0.82rem;
        }
        textarea { min-height: 72px; resize: vertical; }

        .inline-row { display:flex; gap:0.6rem; }
        .inline-row > div { flex:1; }

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
    </style>
</head>
<body>

<div class="module-shell">
    <div class="module-bg"></div>

    <div class="module-content">
        <header class="module-header">
            <div class="module-header-left">
                <div class="module-title">Creatine Tracker</div>
                <div class="module-subtitle">Log the habit. Keep the muscle saturated.</div>
            </div>
            <button class="module-back" type="button" onclick="window.location.href='/modules/index.php'">
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
                                <h1>Creatine · Consistency Tracker</h1>
                                <p>Daily grams → adherence score → honest trends.</p>
                            </div>
                        </div>
                        <div class="score-pill">
                            Gray Mentality • <strong>Creatine only works if you take it</strong>
                        </div>
                    </header>

                    <section class="hero">
                        <div class="hero-card">
                            <h2 class="hero-title">
                                Keep the <span class="hero-highlight">habit</span> tight
                            </h2>
                            <p class="hero-sub">
                                Creatine helps you squeeze out a little more high-intensity work. That “little more” compounds
                                into strength, muscle, and resilience — but only if the muscle stays saturated.
                            </p>
                            <p class="tagline">
                                Most lifters: <span class="hero-highlight">3–5 g/day</span>. Loading is optional. Consistency isn’t.
                            </p>
                        </div>

                        <div class="hero-card score-card">
                            <div class="score-ring">
                                <div class="score-label">Live adherence estimate</div>
                                <div class="score-value" id="score-value">~0</div>
                                <div class="score-caption" id="score-caption">
                                    No creatine yet today. Not fatal — just don’t let “yet” become “never”.
                                </div>
                            </div>

                            <div class="score-ring">
                                <div class="score-label">Quick rules</div>
                                <div class="score-caption">
                                    • 80–120% of goal = 100<br>
                                    • Under goal = scaled down<br>
                                    • 0g = 0 (log it anyway)
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
                        <!-- Left card: log form -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Today’s Intake</h3>
                                <p class="card-subtitle">Track grams, doses, and rough timing.</p>
                            </div>

                            <form method="post" id="creatine-form" autocomplete="off" novalidate>
                                <label for="grams_taken">
                                    Total creatine today (g)
                                    <span class="pill" style="margin-left:0.35rem;" id="grams-display">0.0 g</span>
                                </label>
                                <input
                                    type="number"
                                    name="grams_taken"
                                    id="grams_taken"
                                    min="0"
                                    max="30"
                                    step="0.5"
                                    value="<?php echo e($_POST['grams_taken'] ?? '0'); ?>"
                                    required
                                >

                                <label for="goal_grams">
                                    Goal for today (g)
                                    <span class="pill" style="margin-left:0.35rem; opacity:0.85;">default 5.0</span>
                                </label>
                                <input
                                    type="number"
                                    name="goal_grams"
                                    id="goal_grams"
                                    min="1"
                                    max="30"
                                    step="0.5"
                                    value="<?php echo e($_POST['goal_grams'] ?? number_format($defaultGoalG, 1, '.', '')); ?>"
                                    required
                                >

                                <label for="doses">
                                    Doses today
                                    <span class="pill" style="margin-left:0.35rem;" id="doses-display"><?php echo (int)($$_POST['doses'] ?? $defaultDoses); ?></span>
                                </label>
                                <input
                                    type="number"
                                    name="doses"
                                    id="doses"
                                    min="0"
                                    max="6"
                                    step="1"
                                    value="<?php echo e((string)($_POST['doses'] ?? (string)$defaultDoses)); ?>"
                                >

                                <label for="timing">Timing</label>
                                <?php $timingVal = (string)($_POST['timing'] ?? 'any'); ?>
                                <select name="timing" id="timing">
                                    <option value="any" <?php echo ($timingVal === 'any') ? 'selected' : ''; ?>>Whenever</option>
                                    <option value="morning" <?php echo ($timingVal === 'morning') ? 'selected' : ''; ?>>Morning</option>
                                    <option value="pre-workout" <?php echo ($timingVal === 'pre-workout') ? 'selected' : ''; ?>>Pre-workout</option>
                                    <option value="post-workout" <?php echo ($timingVal === 'post-workout') ? 'selected' : ''; ?>>Post-workout</option>
                                    <option value="split" <?php echo ($timingVal === 'split') ? 'selected' : ''; ?>>Split doses</option>
                                    <option value="off-day" <?php echo ($timingVal === 'off-day') ? 'selected' : ''; ?>>Rest day</option>
                                </select>

                                <label for="notes">Notes (optional)</label>
                                <textarea
                                    name="notes"
                                    id="notes"
                                    placeholder="Gut issues, missed dose, different brand, etc."
                                ><?php echo e($_POST['notes'] ?? ''); ?></textarea>

                                <div class="inline-row">
                                    <button type="submit" class="btn-primary">Save Log <span>➜</span></button>
                                    <button type="button" class="btn-secondary" onclick="resetForm()">Reset</button>
                                </div>
                            </form>
                        </article>

                        <!-- Right card: education + history -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Creatine 101</h3>
                                <p class="card-subtitle">Simple, practical, and non-magical.</p>
                            </div>

                            <div class="feedback" style="margin-bottom:0.8rem;">
                                <strong>How much?</strong><br>
                                <span style="opacity:0.9;">
                                    Most lifters: 3–5 g/day. Loading (optional): 15–20 g/day split for 5–7 days,
                                    then 3–5 g/day. Timing matters less than frequency.
                                </span>
                            </div>

                            <div class="feedback" style="margin-bottom:0.8rem;">
                                <strong>Hydration & safety</strong><br>
                                <span style="opacity:0.9;">
                                    Drink enough fluids, especially on training days. A small scale bump can happen early
                                    (water in muscle). If you have kidney issues or medical conditions, talk to a clinician.
                                </span>
                            </div>

                            <div class="card-header" style="margin-top:0.2rem;">
                                <h3 class="card-title">Last 14 days</h3>
                                <p class="card-subtitle">Your trend line starts here.</p>
                            </div>

                            <?php if (empty($recentLogs)): ?>
                                <div class="feedback">No creatine logs yet. Save your first entry to start tracking.</div>
                            <?php else: ?>
                                <div class="history-wrap">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Taken</th>
                                            <th>Goal</th>
                                            <th>Doses</th>
                                            <th>Timing</th>
                                            <th>Score</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($recentLogs as $row): ?>
                                            <tr>
                                                <td><?php echo e((string)$row['log_date']); ?></td>
                                                <td><?php echo e(number_format((float)$row['grams_taken'], 1)); ?> g</td>
                                                <td><?php echo e(number_format((float)$row['goal_grams'], 1)); ?> g</td>
                                                <td><?php echo (int)$row['doses']; ?></td>
                                                <td><?php echo e((string)$row['timing']); ?></td>
                                                <td><?php echo (int)$row['adherence_score']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </article>
                    </section>

                    <footer class="lab-footer">
                        exFIT • Gray Mentality — Consistency beats “secret supplements”.
                    </footer>

                </div>
            </section>
        </main>
    </div>
</div>

<script>
    const gramsEl = document.getElementById('grams_taken');
    const goalEl  = document.getElementById('goal_grams');
    const dosesEl = document.getElementById('doses');

    const gramsDisplay = document.getElementById('grams-display');
    const dosesDisplay = document.getElementById('doses-display');

    const scoreValueEl   = document.getElementById('score-value');
    const scoreCaptionEl = document.getElementById('score-caption');

    function clamp(n, min, max) {
        return Math.max(min, Math.min(n, max));
    }

    function computeScore(grams, goal) {
        if (grams <= 0 || goal <= 0) return 0;

        let ratio = grams / goal;
        ratio = clamp(ratio, 0.0, 1.5);

        if (ratio >= 0.8 && ratio <= 1.2) return 100;
        return clamp(Math.round(ratio * 100), 0, 100);
    }

    function updateUI() {
        const grams = parseFloat(gramsEl.value || '0');
        const goal  = parseFloat(goalEl.value || '0');
        const doses = parseInt(dosesEl.value || '0', 10);

        gramsDisplay.textContent = grams.toFixed(1) + ' g';
        dosesDisplay.textContent = String(isNaN(doses) ? 0 : doses);

        const score = computeScore(grams, goal);
        scoreValueEl.textContent = '~' + score;

        let caption = 'No creatine yet today. Not fatal — just don’t let “yet” become “never”.';
        if (score === 100) caption = 'Right in the sweet spot. This is how saturation happens.';
        else if (score >= 60) caption = 'You got some in. Try to hit full dose most days.';
        else if (score > 0) caption = 'Under target. Fine occasionally — don’t make it the norm.';

        scoreCaptionEl.textContent = caption;
    }

    function resetForm() {
        gramsEl.value = 0;
        dosesEl.value = 1;
        document.getElementById('timing').value = 'any';
        document.getElementById('notes').value = '';
        updateUI();
    }

    [gramsEl, goalEl, dosesEl].forEach(el => el.addEventListener('input', updateUI));
    updateUI();
</script>

</body>
</html>
