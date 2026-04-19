<?php
declare(strict_types=1);

/**
 * BMR (RMR) + Maintenance & Goal-Intake Planner (exFIT) + Logging
 * =============================================================================
 * FILE
 *   /public/modules/bmr/index.php
 *
 * WHAT THIS MODULE DOES
 *   This page is a “baseline calories” tool + logger:
 *     1) Calculates BMR/RMR using Mifflin–St Jeor (kcal/day)
 *     2) Estimates maintenance calories by multiplying BMR by a PAL factor
 *     3) Optionally estimates a goal-based daily intake using a simple energy
 *        balance heuristic (~7700 kcal per kg of body-weight change)
 *     4) Writes the calculation to `bmr_logs` so users (and exFIT) can review
 *        how their assumptions and baseline estimates change over time.
 *
 * WHO IT’S FOR
 *   - Adults (18+) seeking a starting estimate for calorie needs.
 *   - Educational use only; not for pregnancy/breastfeeding or medical dieting.
 *   - Not a medical tool. Not individualized nutrition therapy.
 *
 * USER FLOW
 *   1) User opens the module from the “Modules” hub.
 *   2) User selects unit system:
 *        - Metric: kg / cm
 *        - U.S.:   lb / ft / in (converted to kg/cm internally)
 *   3) User enters sex, age, height, weight, and PAL.
 *   4) Optional: user enters a goal weight and timeframe.
 *   5) On submit:
 *        - Validate inputs
 *        - Compute BMR + Maintenance
 *        - If goal data present, compute goal intake estimate + warnings
 *        - Insert row into `bmr_logs`
 *        - Render results panel
 *
 * VISUAL / UX STANDARD (MATCHES exFIT MODULE STYLE)
 *   - Full-screen blurred background image (module specific)
 *   - Translucent centered card with soft border + shadow
 *   - Sticky header with “‹ Modules” back button
 *   - Two-panel layout:
 *        Left  = inputs / controls
 *        Right = results / explainers
 *   - Mobile-first responsive stacking at smaller widths
 *   - Lightweight “live preview” of BMR/maintenance client-side (optional),
 *     but authoritative values are calculated server-side and logged.
 *
 * CALCULATION METHODS
 *   A) Mifflin–St Jeor (kcal/day):
 *      - Male:   BMR = 10*weightKg + 6.25*heightCm - 5*age + 5
 *      - Female: BMR = 10*weightKg + 6.25*heightCm - 5*age - 161
 *
 *   B) Maintenance calories:
 *      - Maintenance = BMR * PAL
 *      - PAL (Physical Activity Level) is a multiplier representing average
 *        daily activity. This is NOT an “exercise multiplier” alone; it is
 *        meant to represent total lifestyle movement and job/steps/training.
 *
 *   C) Goal-based intake (optional, educational):
 *      - Uses rule of thumb: ~7700 kcal per kg of body mass change
 *      - deltaKg      = targetWeightKg - currentWeightKg
 *      - energyDelta  = deltaKg * 7700
 *      - dailyDelta   = energyDelta / days
 *      - goalIntake   = maintenance + dailyDelta
 *
 *      Notes:
 *       - If dailyDelta is negative → deficit (loss)
 *       - If dailyDelta is positive → surplus (gain)
 *       - Time conversions:
 *           weeks  → days = weeks * 7
 *           months → days = months * 30 (rough)
 *
 * SAFETY / WARNINGS
 *   - If timeframe < 14 days: warn about rapid change risks
 *   - If suggested calories approach/rest below resting needs (BMR-ish):
 *       warn the user to slow the pace / consider professional advice
 *   - If suggested calories < 800 kcal/day: warn about medical supervision
 *
 * DATA MODEL / LOGGING
 *   Table: `bmr_logs`
 *   - Each POST inserts one row (a snapshot of the user’s assumptions).
 *   - Logged in metric units for consistency:
 *       height_cm, weight_kg, goal_weight_kg
 *   - Columns:
 *       user_id                (nullable if you ever allow anon mode)
 *       sex, age
 *       height_cm, weight_kg
 *       pal
 *       bmr_kcal, maintenance_kcal
 *       goal_weight_kg         (nullable)
 *       goal_days              (nullable)
 *       goal_daily_delta_kcal  (nullable)
 *       goal_intake_kcal       (nullable)
 *       created_at
 *
 * SECURITY / VALIDATION
 *   - Requires session user_id (redirect to login if absent)
 *   - Uses mysqli prepared statements for insert
 *   - Server-side validation for all inputs (even if client-side preview exists)
 *   - Escapes output with `e()` for safe HTML rendering
 *
 * INTEGRATION NOTES (exFIT)
 *   - Typical usage is alongside Weight Loss / Protein / Recovery modules:
 *       • BMR helps set calorie expectations
 *       • Protein target supports lean mass retention
 *       • Recovery tracking helps avoid chronic fatigue & under-eating issues
 *   - Future enhancements (optional):
 *       - Pull age/sex/height/weight defaults from `users` table
 *       - Show recent `bmr_logs` history trend (latest 10)
 *       - Add “body weight unit” display + convert goal weight display back
 *         into the user’s chosen unit system for convenience
 *
 * DISCLAIMER
 *   This calculator provides estimates only. It does not replace medical
 *   advice, diagnosis, or treatment. If the user has health conditions,
 *   disordered eating history, or is on medication, recommend professional
 *   guidance before aggressive calorie targets.
 * =============================================================================
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

function post_value(string $key, $default = null) {
    return $_POST[$key] ?? $default;
}

function save_bmr_log(
    mysqli $conn,
    int $userId,
    string $sex,
    int $age,
    float $heightCm,
    float $weightKg,
    float $pal,
    int $bmrKcal,
    int $maintenanceKcal,
    ?float $goalWeightKg,
    ?int $goalDays,
    ?int $goalDailyDelta,
    ?int $goalIntake
): void {
    if ($stmt = $conn->prepare("
        INSERT INTO bmr_logs (
            user_id, sex, age, height_cm, weight_kg, pal,
            bmr_kcal, maintenance_kcal,
            goal_weight_kg, goal_days, goal_daily_delta_kcal, goal_intake_kcal
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ")) {
        // i user_id
        // s sex
        // i age
        // d height_cm
        // d weight_kg
        // d pal
        // i bmr_kcal
        // i maintenance_kcal
        // d goal_weight_kg
        // i goal_days
        // i goal_daily_delta_kcal
        // i goal_intake_kcal
        $stmt->bind_param(
            'isidddiidiii',
            $userId,
            $sex,
            $age,
            $heightCm,
            $weightKg,
            $pal,
            $bmrKcal,
            $maintenanceKcal,
            $goalWeightKg,
            $goalDays,
            $goalDailyDelta,
            $goalIntake
        );
        $stmt->execute();
        $stmt->close();
    } else {
        error_log('bmr_logs prepare failed: ' . $conn->error);
    }
}

// ---------- Main calc ----------
$errors = [];
$feedback = '';
$results = null;
$goalResults = null;
$goalWarnings = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $units = (string)post_value('units', 'metric'); // metric | us
    $sex   = (string)post_value('sex', '');
    $age   = (int)post_value('age', 0);

    $pal = (float)post_value('pal', 1.4);

    $weightInput = (float)post_value('weight', 0);

    $heightCm = 0.0;
    $weightKg = 0.0;

    if ($units === 'metric') {
        $heightCm = (float)post_value('height_cm', 0);
        $weightKg = $weightInput;
    } elseif ($units === 'us') {
        $heightFt = (float)post_value('height_ft', 0);
        $heightIn = (float)post_value('height_in', 0);
        $totalIn  = ($heightFt * 12.0) + $heightIn;
        $heightCm = $totalIn * 2.54;
        $weightKg = $weightInput * 0.45359237;
    } else {
        $errors[] = 'Invalid unit selection.';
    }

    // Goal fields (optional)
    $goalWeightInput = (float)post_value('goal_weight', 0);
    $goalTime        = (int)post_value('goal_time', 0);
    $goalTimeUnit    = (string)post_value('goal_time_unit', 'weeks');

    // Validation
    if (!in_array($sex, ['male', 'female'], true)) {
        $errors[] = 'Please choose sex.';
    }
    if ($age < 18 || $age > 100) {
        $errors[] = 'Age must be between 18 and 100 (adults only).';
    }
    if ($heightCm <= 0) {
        $errors[] = 'Please enter a valid height.';
    }
    if ($weightKg <= 0) {
        $errors[] = 'Please enter a valid weight.';
    }
    if ($pal < 1.2 || $pal > 2.6) {
        $errors[] = 'Please choose a valid physical activity level.';
    }

    $hasGoalWeight = $goalWeightInput > 0;
    $hasGoalTime   = $goalTime > 0;

    if (($hasGoalWeight && !$hasGoalTime) || (!$hasGoalWeight && $hasGoalTime)) {
        $errors[] = 'To use the goal feature, enter both a goal weight and a timeframe.';
    }
    if ($hasGoalTime && ($goalTime <= 0 || $goalTime > 260)) {
        $errors[] = 'Goal timeframe seems unrealistic. Please pick a shorter horizon.';
    }

    if (empty($errors)) {
        // Mifflin–St Jeor
        $bmr = 10 * $weightKg + 6.25 * $heightCm - 5 * $age + (($sex === 'male') ? 5 : -161);
        $maintenance = $bmr * $pal;

        $bmrKcal = (int)round($bmr);
        $maintKcal = (int)round($maintenance);

        $results = [
            'bmr' => $bmrKcal,
            'maintenance' => $maintKcal,
            'pal' => $pal,
            'weight_kg' => $weightKg,
            'height_cm' => $heightCm,
        ];

        // Goal estimate (optional)
        $goalWeightKg = null;
        $goalDays = null;
        $goalDailyDelta = null;
        $goalIntake = null;

        if ($hasGoalWeight && $hasGoalTime) {
            $goalWeightKg = ($units === 'metric')
                ? $goalWeightInput
                : ($goalWeightInput * 0.45359237);

            // Days
            $days = $goalTime;
            if ($goalTimeUnit === 'weeks') $days = $goalTime * 7;
            elseif ($goalTimeUnit === 'months') $days = $goalTime * 30;

            if ($days < 14) {
                $goalWarnings[] = 'Timeframe is very short; large, rapid changes may be unsafe.';
            }

            $deltaKg = $goalWeightKg - $weightKg;

            if (abs($deltaKg) < 0.00001) {
                $goalWarnings[] = 'Goal weight equals current weight; maintenance calories already match this goal.';
            } else {
                $energyDelta = $deltaKg * 7700.0;
                $dailyDelta  = $energyDelta / max(1, $days);

                $goalCaloriesRaw = $maintenance + $dailyDelta;
                $goalCalories    = (int)round($goalCaloriesRaw);
                $dailyDeltaRound = (int)round($dailyDelta);

                if ($goalCalories < ($bmr * 1.1)) {
                    $goalWarnings[] = 'Estimated daily calories drop close to or below resting needs. Consider a safer pace and/or talk to a professional.';
                }
                if ($goalCalories < 800) {
                    $goalWarnings[] = 'Sub-800 kcal/day diets are usually only used under strict medical supervision.';
                }

                $goalResults = [
                    'target_weight_kg' => round($goalWeightKg, 1),
                    'delta_kg' => round($deltaKg, 1),
                    'days' => (int)$days,
                    'daily_delta' => $dailyDeltaRound,
                    'goal_calories' => $goalCalories,
                ];

                $goalDays = (int)$days;
                $goalDailyDelta = $dailyDeltaRound;
                $goalIntake = $goalCalories;
            }
        }

        // Log
        save_bmr_log(
            $conn,
            $userId,
            $sex,
            $age,
            $heightCm,
            $weightKg,
            $pal,
            $bmrKcal,
            $maintKcal,
            $goalWeightKg,
            $goalDays,
            $goalDailyDelta,
            $goalIntake
        );

        $feedback = 'Saved. Your baseline is <strong>' . e((string)$bmrKcal) . '</strong> kcal/day (BMR) and <strong>' . e((string)$maintKcal) . '</strong> kcal/day (maintenance).';
    }
}

// For redisplay
$selectedUnits    = $_POST['units'] ?? 'metric';
$selectedSex      = $_POST['sex'] ?? '';
$selectedPal      = $_POST['pal'] ?? '1.4';
$selectedGoalUnit = $_POST['goal_time_unit'] ?? 'weeks';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BMR & Intake Planner | exFIT</title>
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

            /* swap this to your bmr image */
            --module-bg-image: url('../assets/bmr.png');
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
        input[type="number"], select {
            width: 100%;
            padding: 0.4rem 0.5rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(148,163,184,0.5);
            background: rgba(15,15,15,0.9);
            color: #f5f5f5;
            font-size: 0.82rem;
        }

        .inline-row { display:flex; gap:0.6rem; align-items:center; }
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
        .warn { margin-top:0.35rem; font-size:0.75rem; color:#fed7aa; }

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
                <div class="module-title">BMR & Intake Planner</div>
                <div class="module-subtitle">Estimate baseline calories. Log assumptions. Refine over time.</div>
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
                                <h1>BMR + Maintenance</h1>
                                <p>Mifflin–St Jeor baseline + PAL multiplier + optional goal intake.</p>
                            </div>
                        </div>
                        <div class="score-pill">
                            Gray Mentality •
                            <strong>
                                <?php echo $results ? e((string)$results['maintenance']) . ' kcal/day maint' : 'Calculate to begin'; ?>
                            </strong>
                        </div>
                    </header>

                    <section class="hero">
                        <div class="hero-card">
                            <h2 class="hero-title">
                                Calories are a <span class="hero-highlight">budget</span>, not a religion.
                            </h2>
                            <p class="hero-sub">
                                This gives you a starting point: resting burn (BMR) and a rough maintenance estimate.
                                Use it to set expectations, then let real-world trend (weight, training, appetite)
                                drive adjustments.
                            </p>
                            <p class="tagline">
                                exFIT logs each run to <code>bmr_logs</code> so you can see how your inputs evolve.
                            </p>
                        </div>

                        <div class="hero-card score-card">
                            <div class="score-ring">
                                <div class="score-label">Baseline (BMR)</div>
                                <div class="score-value" id="liveBmrValue">–</div>
                                <div class="score-caption">kcal/day at rest (estimate).</div>
                            </div>

                            <div class="score-ring">
                                <div class="score-label">Maintenance</div>
                                <div class="score-value" id="liveMaintValue">–</div>
                                <div class="score-caption">BMR × PAL (rough daily burn).</div>
                            </div>
                        </div>
                    </section>

                    <section class="layout-grid">
                        <!-- Left: Inputs -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Calculator Inputs</h3>
                                <p class="card-subtitle">Pick units, enter stats, choose PAL, optionally add a goal.</p>
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

                            <form method="post" id="bmr-form" autocomplete="off" novalidate>
                                <label for="units">Units</label>
                                <select id="units" name="units">
                                    <option value="metric" <?php echo ($selectedUnits === 'metric') ? 'selected' : ''; ?>>Metric (kg / cm)</option>
                                    <option value="us" <?php echo ($selectedUnits === 'us') ? 'selected' : ''; ?>>U.S. (lb / ft / in)</option>
                                </select>

                                <label for="sex">Sex</label>
                                <select id="sex" name="sex" required>
                                    <option value="" <?php echo ($selectedSex === '') ? 'selected' : ''; ?>>Select…</option>
                                    <option value="male" <?php echo ($selectedSex === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($selectedSex === 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>

                                <label for="age">Age</label>
                                <input type="number" id="age" name="age" min="18" max="100" step="1"
                                       value="<?php echo e((string)($_POST['age'] ?? '')); ?>">

                                <!-- Metric -->
                                <div id="metricFields" style="<?php echo ($selectedUnits === 'metric') ? '' : 'display:none;'; ?>">
                                    <label for="height_cm">Height (cm)</label>
                                    <input type="number" id="height_cm" name="height_cm" min="100" max="250" step="0.1"
                                           value="<?php echo e((string)($_POST['height_cm'] ?? '')); ?>">

                                    <label for="weight_metric">Weight (kg)</label>
                                    <input type="number" id="weight_metric" name="weight" min="30" max="300" step="0.1"
                                           value="<?php echo ($selectedUnits === 'metric') ? e((string)($_POST['weight'] ?? '')) : ''; ?>">
                                </div>

                                <!-- US -->
                                <div id="usFields" style="<?php echo ($selectedUnits === 'us') ? '' : 'display:none;'; ?>">
                                    <label>Height (ft / in)</label>
                                    <div class="inline-row">
                                        <div>
                                            <input type="number" name="height_ft" min="3" max="8" step="1" placeholder="ft"
                                                   value="<?php echo e((string)($_POST['height_ft'] ?? '')); ?>">
                                        </div>
                                        <div>
                                            <input type="number" name="height_in" min="0" max="11" step="1" placeholder="in"
                                                   value="<?php echo e((string)($_POST['height_in'] ?? '')); ?>">
                                        </div>
                                    </div>

                                    <label for="weight_us">Weight (lb)</label>
                                    <input type="number" id="weight_us" name="weight" min="66" max="660" step="0.1"
                                           value="<?php echo ($selectedUnits === 'us') ? e((string)($_POST['weight'] ?? '')) : ''; ?>">
                                </div>

                                <label for="pal">Physical Activity Level (PAL)</label>
                                <select id="pal" name="pal">
                                    <option value="1.2" <?php echo ($selectedPal == '1.2') ? 'selected' : ''; ?>>1.2 – Bed rest / extremely sedentary</option>
                                    <option value="1.4" <?php echo ($selectedPal == '1.4') ? 'selected' : ''; ?>>1.4 – Mostly sitting, very little exercise</option>
                                    <option value="1.6" <?php echo ($selectedPal == '1.6') ? 'selected' : ''; ?>>1.6 – Lightly active (walks / light exercise)</option>
                                    <option value="1.8" <?php echo ($selectedPal == '1.8') ? 'selected' : ''; ?>>1.8 – Moderately active (3–5 hard sessions/week)</option>
                                    <option value="2.0" <?php echo ($selectedPal == '2.0') ? 'selected' : ''; ?>>2.0 – Very active (physical job / frequent hard training)</option>
                                    <option value="2.4" <?php echo ($selectedPal == '2.4') ? 'selected' : ''; ?>>2.4 – Extremely active (athlete-level)</option>
                                </select>

                                <div class="feedback" style="margin-top:0.7rem;">
                                    <strong>Goal & timeframe (optional)</strong><br>
                                    <span style="opacity:0.85;">Rough illustration only (7700 kcal/kg).</span>

                                    <div class="inline-row" style="margin-top:0.6rem;">
                                        <div>
                                            <label style="margin-top:0;">Goal weight</label>
                                            <input type="number" name="goal_weight" min="20" max="400" step="0.1"
                                                   value="<?php echo e((string)($_POST['goal_weight'] ?? '')); ?>">
                                        </div>
                                        <div>
                                            <label style="margin-top:0;">Time</label>
                                            <div class="inline-row">
                                                <div>
                                                    <input type="number" name="goal_time" min="1" max="260" step="1" placeholder="12"
                                                           value="<?php echo e((string)($_POST['goal_time'] ?? '')); ?>">
                                                </div>
                                                <div>
                                                    <select name="goal_time_unit">
                                                        <option value="weeks"  <?php echo ($selectedGoalUnit === 'weeks') ? 'selected' : ''; ?>>weeks</option>
                                                        <option value="months" <?php echo ($selectedGoalUnit === 'months') ? 'selected' : ''; ?>>months</option>
                                                        <option value="days"   <?php echo ($selectedGoalUnit === 'days') ? 'selected' : ''; ?>>days</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="warn">Tip: pick conservative changes; trend beats hero math.</div>
                                </div>

                                <button type="submit" class="btn-primary">
                                    Calculate & Save <span>➜</span>
                                </button>
                            </form>
                        </article>

                        <!-- Right: Results -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Results</h3>
                                <p class="card-subtitle">Baseline, maintenance, and optional goal intake.</p>
                            </div>

                            <?php if (!$results): ?>
                                <div class="feedback">
                                    Fill in the form and hit <strong>Calculate & Save</strong>.
                                    A row will be written into <code>bmr_logs</code>.
                                </div>
                            <?php else: ?>
                                <div class="feedback">
                                    <strong>Your Baseline</strong><br>
                                    <span style="opacity:0.9;">
                                        BMR: <strong><?php echo e((string)$results['bmr']); ?></strong> kcal/day<br>
                                        Maintenance: <strong><?php echo e((string)$results['maintenance']); ?></strong> kcal/day
                                        <span style="opacity:0.8;">(PAL <?php echo e((string)$results['pal']); ?>)</span>
                                    </span>
                                </div>

                                <?php if ($goalResults): ?>
                                    <div class="feedback">
                                        <strong>Goal-Based Intake (rough)</strong><br>
                                        <span style="opacity:0.9;">
                                            Target: <strong><?php echo e((string)$goalResults['target_weight_kg']); ?></strong> kg<br>
                                            Change: <strong><?php echo ($goalResults['delta_kg'] > 0 ? '+' : ''); echo e((string)$goalResults['delta_kg']); ?></strong> kg<br>
                                            Time: <strong><?php echo e((string)$goalResults['days']); ?></strong> days<br>
                                            Suggested intake: <strong><?php echo e((string)$goalResults['goal_calories']); ?></strong> kcal/day<br>
                                            Daily delta vs maint: <strong><?php echo ($goalResults['daily_delta'] >= 0 ? '+' : ''); echo e((string)$goalResults['daily_delta']); ?></strong> kcal/day
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="feedback">
                                        <strong>Goal-Based Intake</strong><br>
                                        <span style="opacity:0.85;">Add a goal weight + timeframe to see a rough target.</span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($goalWarnings)): ?>
                                    <div class="feedback" style="border-color: rgba(251,191,36,0.55); background: rgba(120,53,15,0.25);">
                                        <strong>Notes / warnings</strong><br>
                                        <?php foreach ($goalWarnings as $w): ?>
                                            <div class="warn">⚠ <?php echo e((string)$w); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="feedback" style="opacity:0.9;">
                                    <strong>Reminder</strong><br>
                                    This is a starting estimate for adults (18+). Big diet/training changes should be done carefully,
                                    especially with medical conditions or meds.
                                </div>
                            <?php endif; ?>

                            <footer class="lab-footer">
                                exFIT • Gray Mentality — Build the system. Then refine it.
                            </footer>
                        </article>
                    </section>

                </div>
            </section>
        </main>
    </div>
</div>

<script>
    const unitsSel = document.getElementById('units');
    const metricFields = document.getElementById('metricFields');
    const usFields = document.getElementById('usFields');

    const sexSel = document.getElementById('sex');
    const ageEl = document.getElementById('age');
    const palEl = document.getElementById('pal');

    const heightCmEl = document.getElementById('height_cm');
    const weightMetricEl = document.getElementById('weight_metric');

    const liveBmrValue = document.getElementById('liveBmrValue');
    const liveMaintValue = document.getElementById('liveMaintValue');

    function showFields() {
        const u = unitsSel.value;
        metricFields.style.display = (u === 'metric') ? '' : 'none';
        usFields.style.display = (u === 'us') ? '' : 'none';
        updateLive();
    }

    function getInputsAsMetric() {
        const u = unitsSel.value;
        const age = parseFloat(ageEl.value || '0');
        const pal = parseFloat(palEl.value || '1.4');
        const sex = sexSel.value;

        let heightCm = 0, weightKg = 0;

        if (u === 'metric') {
            heightCm = parseFloat((heightCmEl && heightCmEl.value) || '0');
            weightKg = parseFloat((weightMetricEl && weightMetricEl.value) || '0');
        } else {
            const ft = parseFloat((document.querySelector('input[name="height_ft"]')?.value) || '0');
            const inch = parseFloat((document.querySelector('input[name="height_in"]')?.value) || '0');
            const lb = parseFloat((document.getElementById('weight_us')?.value) || '0');

            const totalIn = ft * 12 + inch;
            heightCm = totalIn * 2.54;
            weightKg = lb * 0.45359237;
        }

        return { sex, age, pal, heightCm, weightKg };
    }

    function updateLive() {
        const { sex, age, pal, heightCm, weightKg } = getInputsAsMetric();

        if (!sex || age <= 0 || heightCm <= 0 || weightKg <= 0 || pal <= 0) {
            liveBmrValue.textContent = '–';
            liveMaintValue.textContent = '–';
            return;
        }

        let bmr = 10 * weightKg + 6.25 * heightCm - 5 * age + ((sex === 'male') ? 5 : -161);
        let maint = bmr * pal;

        liveBmrValue.textContent = Math.round(bmr) + ' kcal';
        liveMaintValue.textContent = Math.round(maint) + ' kcal';
    }

    unitsSel.addEventListener('change', showFields);
    [sexSel, ageEl, palEl].forEach(el => el.addEventListener('input', updateLive));

    document.querySelectorAll('input, select').forEach(el => {
        el.addEventListener('input', updateLive);
    });

    showFields();
</script>

</body>
</html>
