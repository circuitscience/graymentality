<?php
declare(strict_types=1);

/**
 * Protein Intake (exFIT)
 * ------------------------------------------------------------------------
 * FILE: /public/modules/protein_intake/index.php
 *
 * PURPOSE
 *  - Educational module: “Are you really getting enough protein?”
 *  - Calculator estimates protein target range based on:
 *      • body_weight + unit (kg/lb)
 *      • goal/context multiplier range
 *  - Allows user to SAVE the calculated range to protein_logs
 *  - Includes a simple “Daily Protein Check-In” (optional) and signal tracker
 *  - Displays recent logs (latest 7)
 *
 * UX / LAYOUT
 *  - Uses the same module shell pattern as Frame Potential & Muscle Growth:
 *      • blurred full-screen background image (module-specific)
 *      • translucent centered content card
 *      • sticky header with “‹ Modules” back button
 *      • hero section + grid layout
 *      • accordion-style content cards
 *
 * DATABASE EXPECTATIONS
 *  1) Table: protein_logs
 *     Columns used:
 *       id (PK), created_at (datetime/timestamp),
 *       user_id (nullable/int),
 *       body_weight (decimal),
 *       weight_unit (varchar 2),
 *       goal (varchar),
 *       protein_min_g (decimal),
 *       protein_max_g (decimal),
 *       notes (text nullable)
 *
 *  2) OPTIONAL Table: protein_intake_logs (daily check-in)
 *     If you don't want this, remove the "Daily Check-In" section and handler.
 *     Columns (suggested):
 *       id (PK), created_at (datetime/timestamp),
 *       user_id (nullable/int),
 *       hit_target (tinyint 0/1),
 *       meals (int),
 *       est_protein_g (int),
 *       notes (text nullable)
 *
 * NOTES
 *  - Calculator uses JS; saving uses normal POST (no AJAX required).
 *  - If you prefer AJAX, you can adapt it to match your muscle_growth endpoint style.
 */

require_once __DIR__ . '/../../../config/config.php';

// OPTIONAL: session user
// session_start();
// $current_user_id = $_SESSION['user_id'] ?? null;
$current_user_id = null;

// helper
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* -----------------------------------------------------------------------
 * 1) SAVE calculated target range into protein_logs
 * ---------------------------------------------------------------------*/
$save_success = false;
$save_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_protein_log'])) {
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        $save_error = 'Database connection not available.';
    } else {
        $body_weight = isset($_POST['body_weight']) ? (float)$_POST['body_weight'] : 0.0;
        $weight_unit = (($_POST['weight_unit'] ?? 'kg') === 'lb') ? 'lb' : 'kg';
        $goal        = (string)($_POST['goal'] ?? 'Active / training');

        $protein_min = isset($_POST['protein_min']) ? (float)$_POST['protein_min'] : 0.0;
        $protein_max = isset($_POST['protein_max']) ? (float)$_POST['protein_max'] : 0.0;

        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($body_weight <= 0 || $protein_min <= 0 || $protein_max <= 0) {
            $save_error = 'Please calculate your target first.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO protein_logs
                    (user_id, body_weight, weight_unit, goal, protein_min_g, protein_max_g, notes)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                $save_error = 'Failed to prepare insert statement.';
            } else {
                // i d s s d d s
                $stmt->bind_param(
                    "idssdds",
                    $current_user_id,
                    $body_weight,
                    $weight_unit,
                    $goal,
                    $protein_min,
                    $protein_max,
                    $notes
                );

                if ($stmt->execute()) {
                    $save_success = true;
                } else {
                    $save_error = 'Failed to save: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

/* -----------------------------------------------------------------------
 * 2) OPTIONAL: Daily check-in save (protein_intake_logs)
 * ---------------------------------------------------------------------*/
$checkin_success = false;
$checkin_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_protein_checkin'])) {
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        $checkin_error = 'Database connection not available.';
    } else {
        $hit_target    = isset($_POST['hit_target']) ? (int)$_POST['hit_target'] : 0;
        $meals         = isset($_POST['meals']) ? (int)$_POST['meals'] : 0;
        $est_protein_g = isset($_POST['est_protein_g']) ? (int)$_POST['est_protein_g'] : 0;
        $c_notes       = trim((string)($_POST['checkin_notes'] ?? ''));

        // Basic validation (lightweight)
        if ($meals < 0 || $meals > 12) $meals = 0;
        if ($est_protein_g < 0 || $est_protein_g > 600) $est_protein_g = 0;
        if ($hit_target !== 1) $hit_target = 0;

        // If you don't have protein_intake_logs table, you can disable this block
        $stmt = $conn->prepare("
            INSERT INTO protein_intake_logs
                (user_id, hit_target, meals, est_protein_g, notes)
            VALUES
                (?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $checkin_error = 'Check-in table missing or failed to prepare statement.';
        } else {
            $stmt->bind_param("iiiis", $current_user_id, $hit_target, $meals, $est_protein_g, $c_notes);
            if ($stmt->execute()) {
                $checkin_success = true;
            } else {
                $checkin_error = 'Failed to save check-in: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

/* -----------------------------------------------------------------------
 * 3) Fetch recent target logs
 * ---------------------------------------------------------------------*/
$recent_logs = [];
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
    $sql = "SELECT body_weight, weight_unit, goal, protein_min_g, protein_max_g, notes, created_at
            FROM protein_logs
            ORDER BY created_at DESC
            LIMIT 7";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) $recent_logs[] = $row;
        $res->free();
    }
}

/* -----------------------------------------------------------------------
 * 4) Fetch recent check-ins (optional)
 * ---------------------------------------------------------------------*/
$checkins = [];
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
    // If table doesn't exist, query will fail silently (we just won't show it)
    $sql = "SELECT hit_target, meals, est_protein_g, notes, created_at
            FROM protein_intake_logs
            ORDER BY created_at DESC
            LIMIT 6";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) $checkins[] = $row;
        $res->free();
    }
}

/* -----------------------------------------------------------------------
 * POST repopulation
 * ---------------------------------------------------------------------*/
$post_weight = e((string)($_POST['body_weight'] ?? ''));
$post_unit   = e((string)($_POST['weight_unit'] ?? 'kg'));
$post_goal   = e((string)($_POST['goal'] ?? 'Active / training'));
$post_notes  = e((string)($_POST['notes'] ?? ''));

$post_min = e((string)($_POST['protein_min'] ?? ''));
$post_max = e((string)($_POST['protein_max'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Protein Intake | exFIT</title>
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

            /* Set your module image here */
            --module-bg-image: url('../assets/protein.png');
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

        /* ===== Module Shell (matches your muscle_growth file) ===== */
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
            position: sticky;
            top: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(to bottom, rgba(0,0,0,0.82), rgba(0,0,0,0.35));
            backdrop-filter: blur(12px);
        }
        .module-header-left { display:flex; flex-direction:column; gap:0.15rem; }
        .module-title { font-size: 1rem; font-weight: 600; letter-spacing: 0.03em; }
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

        /* ===== Inner content styling (same vibe) ===== */
        .page-wrap { display:flex; flex-direction:column; gap:1.15rem; }

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
            margin-top: 0.75rem;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(0, 1.55fr);
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

        /* Accordion */
        .accordion { display:flex; flex-direction:column; gap:0.6rem; }
        .accordion-item { border-radius: 0.85rem; overflow:hidden; }
        .accordion-header {
            width: 100%;
            text-align: left;
            background: rgba(0,0,0,0.35);
            border: none;
            color: #f5f5f5;
            padding: 0.75rem 0.8rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
            cursor:pointer;
        }
        .accordion-header h3 { margin: 0; font-size: 0.9rem; font-weight: 650; }
        .accordion-toggle { font-size: 1.1rem; opacity: 0.85; width: 28px; text-align: center; }
        .accordion-body {
            display: none;
            padding: 0.75rem 0.85rem 0.9rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            font-size: 0.82rem;
            opacity: 0.95;
            line-height: 1.45;
        }
        .accordion-body ul { margin: 0.4rem 0 0; padding-left: 1.1rem; }
        .accordion-body li { margin: 0.22rem 0; }

        /* Forms */
        label { font-size:0.78rem; display:block; margin:0.55rem 0 0.15rem; opacity:0.85; }
        input[type="number"], select, textarea {
            width: 100%;
            padding: 0.45rem 0.55rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(148,163,184,0.5);
            background: rgba(15,15,15,0.9);
            color: #f5f5f5;
            font-size: 0.82rem;
        }
        textarea { min-height: 80px; resize: vertical; }
        .muted { opacity: 0.75; font-size: 0.78rem; }

        .row-2 { display:grid; grid-template-columns: 1fr 1fr; gap: 0.7rem; }
        @media (max-width: 700px){ .row-2{ grid-template-columns: 1fr; } }

        .result-box {
            margin-top: 0.7rem;
            padding: 0.7rem 0.8rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(0,0,0,0.35);
            display:none;
        }
        .result-box strong { color: #ffb347; }

        /* Logs */
        table { width:100%; border-collapse: collapse; margin-top: 0.6rem; }
        th, td {
            text-align:left;
            padding: 0.55rem 0.4rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-size: 0.8rem;
            vertical-align: top;
        }
        th { font-size: 0.74rem; opacity: 0.75; }

        .status-ok { color: var(--success); font-size: 0.8rem; margin-top: 0.6rem; }
        .status-err { color: var(--danger); font-size: 0.8rem; margin-top: 0.6rem; }

        footer.lab-footer {
            margin-top: 0.7rem;
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
                <div class="module-title">Protein Intake</div>
                <div class="module-subtitle">Calculate targets, track signals, and log the trend.</div>
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
                                <h1>Are you really getting enough protein?</h1>
                                <p>Protein is aging armor. Track it like training.</p>
                            </div>
                        </div>
                        <div class="score-pill">
                            Gray Mentality • <strong>Consistency wins</strong>
                        </div>
                    </header>

                    <section class="hero">
                        <div class="hero-card">
                            <h2 class="hero-title">
                                Targets beat guessing — <span class="hero-highlight">every time</span>
                            </h2>
                            <p class="hero-sub">
                                Calculate a realistic daily protein range based on your bodyweight and goal.
                                Then log it. You don’t need perfection — you need a trend.
                            </p>
                            <button class="btn-primary" id="scrollToCalc" type="button">
                                Calculate Target <span>➜</span>
                            </button>
                        </div>

                        <div class="hero-card">
                            <h2 class="hero-title">Quick rule</h2>
                            <p class="hero-sub">
                                Most active adults do well around <strong>1.2–2.2 g/kg/day</strong>.
                                Over 50? Drift higher. Spread across 3–5 meals.
                            </p>
                            <p class="tagline">Aim for 20–40g per meal and make it boringly consistent.</p>
                        </div>
                    </section>

                    <section class="layout-grid">
                        <!-- Calculator + Save -->
                        <article class="card" id="calculator">
                            <div class="card-header">
                                <h3 class="card-title">Protein Target Calculator</h3>
                                <p class="card-subtitle">Calculate your range, then save it to your log.</p>
                            </div>

                            <form id="proteinForm" method="post" action="#logs">
                                <div class="row-2">
                                    <div>
                                        <label for="body_weight">Body weight</label>
                                        <input type="number" step="0.1" min="20" id="body_weight" name="body_weight"
                                               value="<?= $post_weight ?>" placeholder="e.g. 80" required>
                                    </div>
                                    <div>
                                        <label for="weight_unit">Unit</label>
                                        <select id="weight_unit" name="weight_unit">
                                            <option value="kg" <?= $post_unit === 'kg' ? 'selected' : '' ?>>kg</option>
                                            <option value="lb" <?= $post_unit === 'lb' ? 'selected' : '' ?>>lb</option>
                                        </select>
                                    </div>
                                </div>

                                <label for="goal">Goal / Context</label>
                                <select id="goal" name="goal">
                                    <option value="Basic health" <?= $post_goal === 'Basic health' ? 'selected' : '' ?>>Basic health</option>
                                    <option value="Active / training" <?= $post_goal === 'Active / training' ? 'selected' : '' ?>>Active / training</option>
                                    <option value="Muscle gain / aging defense" <?= $post_goal === 'Muscle gain / aging defense' ? 'selected' : '' ?>>Muscle gain / aging defense</option>
                                </select>

                                <div class="muted" style="margin-top:0.5rem;">
                                    Mapping: Basic ~1.2–1.6 · Active ~1.4–1.8 · Muscle/Aging ~1.8–2.2 g/kg/day
                                </div>

                                <button type="button" class="btn-primary" id="btnCalc">
                                    Calculate <span>➜</span>
                                </button>

                                <div class="result-box" id="resultBox">
                                    Estimated range:
                                    <div style="margin-top:0.25rem;">
                                        <strong id="resultRange">—</strong>
                                    </div>
                                    <div class="muted" id="resultMeals" style="margin-top:0.35rem;"></div>
                                </div>

                                <!-- hidden fields used by PHP save -->
                                <input type="hidden" id="protein_min" name="protein_min" value="<?= $post_min ?>">
                                <input type="hidden" id="protein_max" name="protein_max" value="<?= $post_max ?>">
                                <input type="hidden" name="save_protein_log" value="1">

                                <label for="notes">Notes (optional)</label>
                                <textarea id="notes" name="notes" placeholder="e.g. Hit 3 meals, missed evening snack."><?= $post_notes ?></textarea>

                                <button type="submit" class="btn-primary" style="margin-top:0.75rem;">
                                    Save Target <span>➜</span>
                                </button>

                                <?php if ($save_success): ?>
                                    <div class="status-ok">Saved. Scroll down to see it in your logs.</div>
                                <?php elseif ($save_error !== ''): ?>
                                    <div class="status-err"><?= e($save_error) ?></div>
                                <?php endif; ?>
                            </form>
                        </article>

                        <!-- Signals accordion -->
                        <article class="card" id="signals">
                            <div class="card-header">
                                <h3 class="card-title">Protein Signals</h3>
                                <p class="card-subtitle">Tap each section. Track flags and wins.</p>
                            </div>

                            <div class="accordion">

                                <div class="card accordion-item">
                                    <button class="accordion-header" type="button" aria-expanded="false">
                                        <h3>1. Signals you might be under-eating protein</h3>
                                        <span class="accordion-toggle">+</span>
                                    </button>
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Recovery feels slow; soreness lingers longer than it should.</li>
                                            <li>Strength stalls even though training is consistent.</li>
                                            <li>Constant hunger or unstable appetite.</li>
                                            <li>Softening / losing lean tissue over time.</li>
                                            <li>More frequent “run down” feelings.</li>
                                        </ul>
                                        <p class="muted" style="margin-top:0.5rem;">
                                            One symptom isn’t proof. A cluster over weeks is a clue.
                                        </p>
                                    </div>
                                </div>

                                <div class="card accordion-item">
                                    <button class="accordion-header" type="button" aria-expanded="false">
                                        <h3>2. Signals you’re likely on track</h3>
                                        <span class="accordion-toggle">+</span>
                                    </button>
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Strength is stable or trending up.</li>
                                            <li>Recovery between sessions is reasonable.</li>
                                            <li>Appetite and energy feel more stable day-to-day.</li>
                                            <li>You’re holding muscle even while leaning out.</li>
                                            <li>Less brain fog, better focus.</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="card accordion-item">
                                    <button class="accordion-header" type="button" aria-expanded="false">
                                        <h3>3. Execution rule</h3>
                                        <span class="accordion-toggle">+</span>
                                    </button>
                                    <div class="accordion-body">
                                        <p>
                                            Don’t “wing it” with random shakes. Hit a consistent baseline with real meals first.
                                            If needed, supplement after.
                                        </p>
                                        <p class="muted">
                                            Goal: boring consistency → measurable trend → real confidence.
                                        </p>
                                    </div>
                                </div>

                            </div>
                        </article>
                    </section>

                    <!-- Optional Daily Check-In -->
                    <section class="layout-grid" id="checkin">
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Daily Protein Check-In (optional)</h3>
                                <p class="card-subtitle">Quick compliance snapshot. Not perfection — pattern.</p>
                            </div>

                            <form method="post" action="#checkin">
                                <label>
                                    <input type="checkbox" name="hit_target" value="1" style="transform: translateY(1px);">
                                    I hit my protein target (or close enough)
                                </label>

                                <div class="row-2" style="margin-top:0.4rem;">
                                    <div>
                                        <label for="meals">Meals with protein</label>
                                        <input type="number" id="meals" name="meals" min="0" max="12" step="1" placeholder="e.g. 4">
                                    </div>
                                    <div>
                                        <label for="est_protein_g">Estimated protein (g)</label>
                                        <input type="number" id="est_protein_g" name="est_protein_g" min="0" max="600" step="5" placeholder="e.g. 160">
                                    </div>
                                </div>

                                <label for="checkin_notes">Notes</label>
                                <textarea id="checkin_notes" name="checkin_notes" placeholder="What helped/hurt today?"></textarea>

                                <input type="hidden" name="save_protein_checkin" value="1">

                                <button type="submit" class="btn-primary" style="margin-top:0.75rem;">
                                    Save Check-In <span>➜</span>
                                </button>

                                <?php if ($checkin_success): ?>
                                    <div class="status-ok">Check-in saved.</div>
                                <?php elseif ($checkin_error !== ''): ?>
                                    <div class="status-err"><?= e($checkin_error) ?></div>
                                <?php endif; ?>
                            </form>
                        </article>

                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Recent Check-Ins</h3>
                                <p class="card-subtitle">Last few snapshots (if enabled).</p>
                            </div>

                            <?php if (empty($checkins)): ?>
                                <div class="muted">No check-ins found (or table not enabled).</div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Hit</th>
                                        <th>Meals</th>
                                        <th>Est (g)</th>
                                        <th>Note</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($checkins as $c): ?>
                                        <tr>
                                            <td><?= e((string)$c['created_at']) ?></td>
                                            <td><?= ((int)$c['hit_target'] === 1) ? '✔' : '—' ?></td>
                                            <td><?= (int)$c['meals'] ?></td>
                                            <td><?= (int)$c['est_protein_g'] ?></td>
                                            <td><?= e((string)($c['notes'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </article>
                    </section>

                    <!-- Logs -->
                    <article class="card" id="logs">
                        <div class="card-header">
                            <h3 class="card-title">Recent Protein Targets</h3>
                            <p class="card-subtitle">Last 7 saved target ranges.</p>
                        </div>

                        <?php if (empty($recent_logs)): ?>
                            <div class="muted">No logs yet. Calculate a target and save it.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Weight</th>
                                    <th>Goal</th>
                                    <th>Protein Range</th>
                                    <th>Note</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td><?= e((string)$log['created_at']) ?></td>
                                        <td><?= e((string)$log['body_weight']) ?> <?= e((string)$log['weight_unit']) ?></td>
                                        <td><?= e((string)$log['goal']) ?></td>
                                        <td><strong><?= e((string)$log['protein_min_g']) ?>–<?= e((string)$log['protein_max_g']) ?> g</strong></td>
                                        <td><?= e((string)($log['notes'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </article>

                    <footer class="lab-footer">
                        exFIT • Gray Mentality — Die Living.
                    </footer>

                </div>
            </section>
        </main>
    </div>
</div>

<script>
    // Scroll CTA
    document.getElementById('scrollToCalc')?.addEventListener('click', () => {
        document.getElementById('calculator')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Accordion behavior (same as muscle_growth)
    document.querySelectorAll('.accordion-header').forEach(btn => {
        btn.addEventListener('click', () => {
            const body = btn.parentElement.querySelector('.accordion-body');
            const isOpen = body.style.display === 'block';

            document.querySelectorAll('.accordion-body').forEach(b => b.style.display = 'none');
            document.querySelectorAll('.accordion-header').forEach(h => {
                h.setAttribute('aria-expanded', 'false');
                const t = h.querySelector('.accordion-toggle');
                if (t) t.textContent = '+';
            });

            if (!isOpen) {
                body.style.display = 'block';
                btn.setAttribute('aria-expanded', 'true');
                const t = btn.querySelector('.accordion-toggle');
                if (t) t.textContent = '–';
            }
        });
    });

    // Calculator (kept from your earlier file, styled to match)
    function roundTo(value, step) { return Math.round(value / step) * step; }

    const weightInput = document.getElementById('body_weight');
    const unitSelect  = document.getElementById('weight_unit');
    const goalSelect  = document.getElementById('goal');

    const resultBox   = document.getElementById('resultBox');
    const resultRange = document.getElementById('resultRange');
    const resultMeals = document.getElementById('resultMeals');

    const proteinMinField = document.getElementById('protein_min');
    const proteinMaxField = document.getElementById('protein_max');
    const btnCalc = document.getElementById('btnCalc');

    function calculateProteinRange() {
        const weight = parseFloat(weightInput.value || '0');
        if (!weight || weight <= 0) { alert('Please enter a valid body weight.'); return; }

        const unit = unitSelect.value;
        const goal = goalSelect.value;

        let weightKg = weight;
        if (unit === 'lb') weightKg = weight * 0.45359237;

        let minMult = 1.2, maxMult = 1.6;
        if (goal === 'Active / training') { minMult = 1.4; maxMult = 1.8; }
        else if (goal === 'Muscle gain / aging defense') { minMult = 1.8; maxMult = 2.2; }

        let minG = roundTo(weightKg * minMult, 5);
        let maxG = roundTo(weightKg * maxMult, 5);

        proteinMinField.value = minG.toFixed(0);
        proteinMaxField.value = maxG.toFixed(0);

        const avg = (minG + maxG) / 2;
        const perMeal3 = Math.round(avg / 3);
        const perMeal4 = Math.round(avg / 4);

        resultRange.textContent = `${minG.toFixed(0)}–${maxG.toFixed(0)} g/day`;
        resultMeals.textContent = `~${perMeal3} g if 3 meals, or ~${perMeal4} g across 4 meals.`;

        resultBox.style.display = 'block';
    }

    btnCalc?.addEventListener('click', calculateProteinRange);

    // If the hidden fields already have values (after POST), show the result box
    (function bootstrapResult() {
        const minV = parseFloat(proteinMinField?.value || '0');
        const maxV = parseFloat(proteinMaxField?.value || '0');
        if (minV > 0 && maxV > 0) {
            const avg = (minV + maxV) / 2;
            resultRange.textContent = `${minV.toFixed(0)}–${maxV.toFixed(0)} g/day`;
            resultMeals.textContent = `~${Math.round(avg/3)} g if 3 meals, or ~${Math.round(avg/4)} g across 4 meals.`;
            resultBox.style.display = 'block';
        }
    })();
</script>

</body>
</html>
