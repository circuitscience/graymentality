<?php
declare(strict_types=1);

/**
 * Grip Strength Lab (exFIT)
 * ------------------------------------------------------------------------
 * FILE:  /public/modules/grip_strength/index.php
 *
 * PURPOSE
 *  - Provide a simple grip-strength tracker + mini-plan generator.
 *  - Grip is treated as a practical “health + performance” marker:
 *      • correlates with overall strength
 *      • predicts capability in daily life (carry, pull, brace, control)
 *      • tends to decline with age unless deliberately trained
 *
 * DATA FLOW
 *  1) Session guard:
 *      - requires $_SESSION['user_id'] (redirects to /public/login.php if missing)
 *  2) Pull user context:
 *      - SELECT age, gender FROM users WHERE id = ?
 *      - used ONLY for category cutoffs (weak/average/strong/elite)
 *  3) User submits a test:
 *      - test_type determines how avg_grip_lbs is derived:
 *          a) dynamometer  : avg = (left + right) / 2
 *          b) dead_hang    : estimated avg = bodyweight_lbs * (seconds / 60)
 *          c) farmer_carry : approximation avg = weight_per_hand_lbs
 *  4) Categorize:
 *      - categorize_grip(gender, age, avgGripLbs) → "weak|average|strong|elite"
 *      - category is a coarse band used for messaging + mini plan only
 *  5) Save to grip_logs:
 *      - INSERT row containing raw inputs + computed avg + category + notes
 *  6) Display:
 *      - Recent history table (last 30 logs)
 *      - Mini-plan block based on most recent category
 *
 * UX / LAYOUT (MATCHES FRAME POTENTIAL)
 *  - Uses the same module shell:
 *      • blurred background image (module-specific)
 *      • translucent “module-card” overlay
 *      • header with “‹ Modules” back button to /public/modules/index.php
 *  - Background image is controlled via:
 *      :root { --module-bg-image: url('../assets/grip.png'); }
 *
 * IMPORTANT ASSUMPTIONS + LIMITATIONS
 *  - Dead-hang and farmer carry “avg grip” values are approximations.
 *    They are useful for tracking trends but not directly comparable to
 *    dynamometer scores.
 *  - Category thresholds are simple heuristics. They are not medical
 *    cutoffs and should not be used as a diagnosis.
 *
 * DATABASE EXPECTATIONS
 *  - Table: grip_logs
 *    Columns expected (names must match):
 *      id (PK), user_id, test_date, test_type,
 *      bodyweight_kg, bodyweight_lbs,
 *      grip_left_lbs, grip_right_lbs, avg_grip_lbs,
 *      dead_hang_seconds, farmer_weight_lbs,
 *      category, notes
 *
 * SECURITY NOTES
 *  - All DB writes use prepared statements.
 *  - All displayed text is HTML-escaped with e() or htmlspecialchars().
 *
 * NEXT EXTENSIONS (OPTIONAL)
 *  - Add unit toggle (kg/newtons vs lbs) + display normalization
 *  - Add charts (trend line for avg_grip_lbs)
 *  - Add “personal best” detection + badges + email nudges
 *  - Add edit/delete log entries (admin/user control)
 */

session_start();
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Simple HTML escape
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// --- Fetch user age & gender for category logic ---
$userAge = null;
$userGender = null;
if ($stmt = $conn->prepare("SELECT age, gender FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($userAge, $userGender);
    $stmt->fetch();
    $stmt->close();
}

// --- Helper: categorize grip based on sex/age ---
function categorize_grip(?string $gender, ?int $age, float $avgGripLbs): string
{
    if ($age === null || $age <= 0) {
        if ($avgGripLbs < 70)  return 'weak';
        if ($avgGripLbs < 100) return 'average';
        if ($avgGripLbs < 130) return 'strong';
        return 'elite';
    }

    $g = strtolower((string)$gender);
    $isMale = ($g === 'male' || $g === 'm');

    if ($isMale) {
        if ($age < 40) {
            if ($avgGripLbs < 85)  return 'weak';
            if ($avgGripLbs < 115) return 'average';
            if ($avgGripLbs < 141) return 'strong';
            return 'elite';
        } elseif ($age < 60) {
            if ($avgGripLbs < 80)  return 'weak';
            if ($avgGripLbs < 110) return 'average';
            if ($avgGripLbs < 136) return 'strong';
            return 'elite';
        } else {
            if ($avgGripLbs < 70)  return 'weak';
            if ($avgGripLbs < 99)  return 'average';
            if ($avgGripLbs < 121) return 'strong';
            return 'elite';
        }
    } else { // female/default
        if ($age < 40) {
            if ($avgGripLbs < 60)  return 'weak';
            if ($avgGripLbs < 80)  return 'average';
            if ($avgGripLbs < 96)  return 'strong';
            return 'elite';
        } elseif ($age < 60) {
            if ($avgGripLbs < 55)  return 'weak';
            if ($avgGripLbs < 75)  return 'average';
            if ($avgGripLbs < 91)  return 'strong';
            return 'elite';
        } else {
            if ($avgGripLbs < 50)  return 'weak';
            if ($avgGripLbs < 70)  return 'average';
            if ($avgGripLbs < 86)  return 'strong';
            return 'elite';
        }
    }
}

// --- Handle POST (save log) ---
$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $testDate = $_POST['test_date'] ?? date('Y-m-d');
    $testType = $_POST['test_type'] ?? 'dynamometer';

    $bodyLbs  = (isset($_POST['bodyweight_lbs']) && $_POST['bodyweight_lbs'] !== '')
        ? (float)$_POST['bodyweight_lbs']
        : null;
    $bodyKg   = ($bodyLbs && $bodyLbs > 0) ? $bodyLbs / 2.20462 : null;

    $gripLeft  = (isset($_POST['grip_left_lbs']) && $_POST['grip_left_lbs'] !== '')
        ? (float)$_POST['grip_left_lbs']
        : null;
    $gripRight = (isset($_POST['grip_right_lbs']) && $_POST['grip_right_lbs'] !== '')
        ? (float)$_POST['grip_right_lbs']
        : null;

    $deadHang  = (isset($_POST['dead_hang_seconds']) && $_POST['dead_hang_seconds'] !== '')
        ? (int)$_POST['dead_hang_seconds']
        : null;

    $farmerW   = (isset($_POST['farmer_weight_lbs']) && $_POST['farmer_weight_lbs'] !== '')
        ? (float)$_POST['farmer_weight_lbs']
        : null;

    $notes = trim($_POST['notes'] ?? '');

    // Calculate avg grip based on test type
    $avgGrip = null;

    if ($testType === 'dynamometer') {
        if ($gripLeft !== null && $gripRight !== null && $gripLeft > 0 && $gripRight > 0) {
            $avgGrip = ($gripLeft + $gripRight) / 2.0;
        }
    } elseif ($testType === 'dead_hang') {
        if ($bodyLbs && $bodyLbs > 0 && $deadHang && $deadHang > 0) {
            // Estimate grip: bodyweight × (time/60)
            $avgGrip = $bodyLbs * ($deadHang / 60.0);
        }
    } elseif ($testType === 'farmer_carry') {
        if ($farmerW && $farmerW > 0) {
            // Approx: per-hand weight ~ grip demand
            $avgGrip = $farmerW;
        }
    }

    if ($avgGrip !== null && $avgGrip > 0) {
        $category = categorize_grip($userGender, $userAge, $avgGrip);

        if ($stmt = $conn->prepare("
            INSERT INTO grip_logs (
                user_id, test_date, test_type,
                bodyweight_kg, bodyweight_lbs,
                grip_left_lbs, grip_right_lbs, avg_grip_lbs,
                dead_hang_seconds, farmer_weight_lbs,
                category, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")) {
            // types:
            // i   user_id
            // s   test_date
            // s   test_type
            // d   bodyweight_kg
            // d   bodyweight_lbs
            // d   grip_left_lbs
            // d   grip_right_lbs
            // d   avg_grip_lbs
            // i   dead_hang_seconds
            // d   farmer_weight_lbs
            // s   category
            // s   notes
            $stmt->bind_param(
                "issdddddidss",
                $userId,
                $testDate,
                $testType,
                $bodyKg,
                $bodyLbs,
                $gripLeft,
                $gripRight,
                $avgGrip,
                $deadHang,
                $farmerW,
                $category,
                $notes
            );

            if ($stmt->execute()) {
                $feedback = 'Grip strength log saved successfully.';
            } else {
                $feedback = 'Error saving log: ' . e($stmt->error);
            }
            $stmt->close();
        } else {
            $feedback = 'Database error preparing statement.';
        }
    } else {
        $feedback = 'Could not calculate average grip – check your inputs.';
    }
}

// --- Fetch recent logs ---
$logs = [];
if ($stmt = $conn->prepare("
    SELECT id, test_date, test_type, bodyweight_lbs,
           grip_left_lbs, grip_right_lbs, avg_grip_lbs,
           dead_hang_seconds, farmer_weight_lbs, category, notes
    FROM grip_logs
    WHERE user_id = ?
    ORDER BY test_date DESC, id DESC
    LIMIT 30
")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
}

// --- Mini Grip Plan (based on most recent category) ---
$latestCategory = null;
$miniTitle = 'Log a grip test to unlock a mini plan';
$miniLead  = 'Once you save at least one grip test, exFIT will give you a simple grip focus to follow.';
$miniList  = [];

if (!empty($logs)) {
    $latestCategory = strtolower((string)$logs[0]['category']);

    switch ($latestCategory) {
        case 'weak':
            $miniTitle = 'Starter Plan: Build Your Base Grip';
            $miniLead  = 'Think “practice, not punishment” – shorter, frequent holds 2–3x per week.';
            $miniList  = [
                '2–3 sets of dead hangs (10–20 seconds) after upper-body workouts.',
                'Farmer carries: 20–30 meters with light–moderate dumbbells, 2–3 rounds.',
                'Stay submax: avoid shaking/all-out failure early on.'
            ];
            break;

        case 'average':
            $miniTitle = 'Progress Plan: Level Up Your Grip';
            $miniLead  = 'You have a base. Now we push volume and load without frying elbows.';
            $miniList  = [
                'Dead hangs: build toward 30–45 seconds, 2–3 sets, twice per week.',
                'Farmer carries heavier: 2–4 rounds of 30–40 meters.',
                'Add 1 pinch/towel grip movement 1–2x per week.'
            ];
            break;

        case 'strong':
            $miniTitle = 'Performance Plan: Strong Grip, Stronger You';
            $miniLead  = 'You’re strong—now keep it climbing without irritating tendons.';
            $miniList  = [
                'Heavy farmer carries once/week: 3–5 rounds at challenging weight.',
                'Hangs/holds after back sessions: 30–60 seconds.',
                'Rotate thick-bar or towel variations for finger demand.'
            ];
            break;

        case 'elite':
            $miniTitle = 'Elite Plan: Maintain & Direct the Power';
            $miniLead  = 'Your grip is a weapon. Use it strategically and protect it.';
            $miniList  = [
                '1–2 focused grip sessions/week (heavy carries or thick-bar work).',
                'Match grip volume to sport: golf, climbing, lifting, manual work.',
                'Prioritize recovery: soft tissue, contrast, deload weeks.'
            ];
            break;

        default:
            $miniTitle = 'Keep Logging: We’ll Dial In Your Plan';
            $miniLead  = 'As your tests stabilize, the mini plan will adjust to your real-world numbers.';
            $miniList  = [
                'Test grip once per week using the same method.',
                'Add one simple carry or hang after your main workout.'
            ];
            break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Grip Strength Lab | exFIT</title>
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
            --module-bg-image: url('../assets/grip.png');
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

        /* ===== Frame-potential-like inner layout ===== */
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
        input[type="number"], input[type="date"], select, textarea {
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

        .feedback {
            border-radius: 0.7rem;
            padding: 0.7rem 0.75rem;
            background: rgba(15,23,42,0.9);
            border: 1px solid rgba(148,163,184,0.6);
            font-size: 0.78rem;
            margin-bottom: 0.6rem;
        }

        .tag {
            display: inline-block;
            border-radius: 999px;
            padding: 0.05rem 0.6rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 1px solid rgba(148,163,184,0.55);
            opacity: 0.95;
        }
        .tag-weak { border-color:#ff4b4b; color:#ffb3b3; }
        .tag-average { border-color:#ffb84b; color:#ffe0ad; }
        .tag-strong { border-color:#4bff9a; color:#b9ffd9; }
        .tag-elite { border-color:#4b9aff; color:#b8d8ff; }

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
    </style>
</head>
<body>

<div class="module-shell">
    <div class="module-bg"></div>

    <div class="module-content">
        <header class="module-header">
            <div class="module-header-left">
                <div class="module-title">Grip Strength Lab</div>
                <div class="module-subtitle">Track a simple marker that predicts real-world strength and aging.</div>
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
                                <h1>Grip Strength FYI & Tracker</h1>
                                <p>Log your grip. Get a mini plan. Watch the trend.</p>
                            </div>
                        </div>
                        <div class="score-pill">
                            Gray Mentality • <strong>Hands that hold on</strong>
                        </div>
                    </header>

                    <section class="hero">
                        <div class="hero-card">
                            <h2 class="hero-title">
                                Why <span class="hero-highlight">grip</span> matters
                            </h2>
                            <p class="hero-sub">
                                Grip strength is a blunt, honest measure. It reflects muscle quality, nervous system output,
                                and the ability to handle real life: carrying, climbing, bracing, pulling, and controlling tools.
                            </p>
                            <p class="tagline">
                                You don’t need to obsess. Just log it occasionally and keep the line moving the right way.
                            </p>
                        </div>

                        <div class="hero-card score-card">
                            <div class="score-ring">
                                <div class="score-label">Latest category</div>
                                <div class="score-value">
                                    <?php if ($latestCategory): ?>
                                        <?php
                                        $class = 'tag-average';
                                        if ($latestCategory === 'weak') $class = 'tag-weak';
                                        elseif ($latestCategory === 'strong') $class = 'tag-strong';
                                        elseif ($latestCategory === 'elite') $class = 'tag-elite';
                                        ?>
                                        <span class="tag <?php echo $class; ?>"><?php echo e($latestCategory); ?></span>
                                    <?php else: ?>
                                        –
                                    <?php endif; ?>
                                </div>
                                <div class="score-caption">
                                    Based on your most recent saved log.
                                </div>
                            </div>

                            <div class="score-ring">
                                <div class="score-label">Quick test options</div>
                                <div class="score-caption">
                                    • Dynamometer (best)<br>
                                    • Dead hang (estimate)<br>
                                    • Farmer carry (approx)
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="layout-grid">
                        <!-- Left card: Inputs + logging -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Calculator & Log</h3>
                                <p class="card-subtitle">Choose a test type, enter values, and save.</p>
                            </div>

                            <?php if ($feedback): ?>
                                <div class="feedback"><?php echo e($feedback); ?></div>
                            <?php endif; ?>

                            <form method="POST" id="grip-form" autocomplete="off">
                                <label for="test_date">Test Date</label>
                                <input type="date" id="test_date" name="test_date"
                                       value="<?php echo e($_POST['test_date'] ?? date('Y-m-d')); ?>">

                                <label for="test_type">Test Type</label>
                                <select id="test_type" name="test_type">
                                    <option value="dynamometer" <?php echo (($_POST['test_type'] ?? 'dynamometer') === 'dynamometer') ? 'selected' : ''; ?>>
                                        Dynamometer (left + right)
                                    </option>
                                    <option value="dead_hang" <?php echo (($_POST['test_type'] ?? '') === 'dead_hang') ? 'selected' : ''; ?>>
                                        Dead Hang (estimate)
                                    </option>
                                    <option value="farmer_carry" <?php echo (($_POST['test_type'] ?? '') === 'farmer_carry') ? 'selected' : ''; ?>>
                                        Farmer Carry (approx)
                                    </option>
                                </select>

                                <!-- Dead hang needs bodyweight -->
                                <div id="bodyweight-row">
                                    <label for="bodyweight_lbs">Bodyweight (lbs)</label>
                                    <input type="number" step="0.1" id="bodyweight_lbs" name="bodyweight_lbs"
                                           value="<?php echo e($_POST['bodyweight_lbs'] ?? ''); ?>">
                                </div>

                                <!-- Dynamometer -->
                                <div id="dyn-fields">
                                    <div class="inline-row">
                                        <div>
                                            <label for="grip_left_lbs">Left Hand (lbs)</label>
                                            <input type="number" step="0.1" id="grip_left_lbs" name="grip_left_lbs"
                                                   value="<?php echo e($_POST['grip_left_lbs'] ?? ''); ?>">
                                        </div>
                                        <div>
                                            <label for="grip_right_lbs">Right Hand (lbs)</label>
                                            <input type="number" step="0.1" id="grip_right_lbs" name="grip_right_lbs"
                                                   value="<?php echo e($_POST['grip_right_lbs'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Dead hang -->
                                <div id="hang-fields" style="display:none;">
                                    <label for="dead_hang_seconds">Dead Hang Time (seconds)</label>
                                    <input type="number" step="1" id="dead_hang_seconds" name="dead_hang_seconds"
                                           value="<?php echo e($_POST['dead_hang_seconds'] ?? ''); ?>">
                                </div>

                                <!-- Farmer carry -->
                                <div id="farmer-fields" style="display:none;">
                                    <label for="farmer_weight_lbs">Weight per Hand (lbs)</label>
                                    <input type="number" step="0.5" id="farmer_weight_lbs" name="farmer_weight_lbs"
                                           value="<?php echo e($_POST['farmer_weight_lbs'] ?? ''); ?>">
                                </div>

                                <label for="notes">Notes (optional)</label>
                                <textarea id="notes" name="notes" placeholder="How did it feel? New PR? Any elbow irritation?"><?php
                                    echo e($_POST['notes'] ?? '');
                                ?></textarea>

                                <div id="calc-summary" class="feedback" style="display:none;"></div>

                                <button type="submit" class="btn-primary">
                                    Save Grip Log <span>➜</span>
                                </button>
                            </form>
                        </article>

                        <!-- Right card: Mini plan + recent logs -->
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Mini Grip Plan</h3>
                                <p class="card-subtitle">Based on your most recent saved category.</p>
                            </div>

                            <div class="feedback">
                                <strong><?php echo e($miniTitle); ?></strong><br>
                                <span style="opacity:0.85;"><?php echo e($miniLead); ?></span>
                            </div>

                            <?php if (!empty($miniList)): ?>
                                <ul style="margin:0.2rem 0 0; padding-left:1.1rem; font-size:0.8rem; opacity:0.9;">
                                    <?php foreach ($miniList as $item): ?>
                                        <li style="margin:0.25rem 0;"><?php echo e($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <div class="card-header" style="margin-top:1rem;">
                                <h3 class="card-title">Recent Logs</h3>
                                <p class="card-subtitle">Last 30 entries.</p>
                            </div>

                            <?php if (empty($logs)): ?>
                                <div class="feedback">No grip tests logged yet. Save your first entry to start tracking.</div>
                            <?php else: ?>
                                <div class="history-wrap">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Avg</th>
                                            <th>Cat</th>
                                            <th>Notes</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($logs as $row): ?>
                                            <?php
                                            $cat = strtolower((string)$row['category']);
                                            $class = 'tag-average';
                                            if ($cat === 'weak') $class = 'tag-weak';
                                            elseif ($cat === 'strong') $class = 'tag-strong';
                                            elseif ($cat === 'elite') $class = 'tag-elite';
                                            ?>
                                            <tr>
                                                <td><?php echo e((string)$row['test_date']); ?></td>
                                                <td><?php echo e((string)$row['test_type']); ?></td>
                                                <td><?php echo $row['avg_grip_lbs'] !== null ? e(number_format((float)$row['avg_grip_lbs'], 1)) : ''; ?></td>
                                                <td>
                                                    <?php if (!empty($row['category'])): ?>
                                                        <span class="tag <?php echo $class; ?>"><?php echo e((string)$row['category']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="opacity:0.8;">
                                                    <?php echo e(mb_strimwidth((string)($row['notes'] ?? ''), 0, 42, '…')); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </article>
                    </section>

                    <footer class="lab-footer">
                        exFIT • Gray Mentality — Hands that hold on when it counts.
                    </footer>

                </div>
            </section>
        </main>
    </div>
</div>

<script>
    const testTypeSelect = document.getElementById('test_type');
    const bodyRow        = document.getElementById('bodyweight-row');
    const dynFields      = document.getElementById('dyn-fields');
    const hangFields     = document.getElementById('hang-fields');
    const farmerFields   = document.getElementById('farmer-fields');
    const summaryEl      = document.getElementById('calc-summary');

    const inputBody   = document.getElementById('bodyweight_lbs');
    const inputLeft   = document.getElementById('grip_left_lbs');
    const inputRight  = document.getElementById('grip_right_lbs');
    const inputHang   = document.getElementById('dead_hang_seconds');
    const inputFarmer = document.getElementById('farmer_weight_lbs');

    function updateFieldVisibility() {
        const type = testTypeSelect.value;

        dynFields.style.display    = (type === 'dynamometer') ? 'block' : 'none';
        hangFields.style.display   = (type === 'dead_hang') ? 'block' : 'none';
        farmerFields.style.display = (type === 'farmer_carry') ? 'block' : 'none';

        bodyRow.style.display = (type === 'dead_hang') ? 'block' : 'none';

        updateSummary();
    }

    function categorizeLocal(avgGrip) {
        if (!avgGrip || avgGrip <= 0) return '';
        if (avgGrip < 70) return 'weak';
        if (avgGrip < 100) return 'average';
        if (avgGrip < 130) return 'strong';
        return 'elite';
    }

    function updateSummary() {
        const type = testTypeSelect.value;
        let avgGrip = null;
        let text = '';

        if (type === 'dynamometer') {
            const l = parseFloat(inputLeft.value || '0');
            const r = parseFloat(inputRight.value || '0');
            if (l > 0 && r > 0) {
                avgGrip = (l + r) / 2.0;
                text = `Estimated average grip: <strong>${avgGrip.toFixed(1)} lbs</strong>`;
            }
        } else if (type === 'dead_hang') {
            const bw  = parseFloat(inputBody.value || '0');
            const sec = parseFloat(inputHang.value || '0');
            if (bw > 0 && sec > 0) {
                avgGrip = bw * (sec / 60.0);
                text = `Estimated grip from dead hang: <strong>${avgGrip.toFixed(1)} lbs</strong>`;
            }
        } else if (type === 'farmer_carry') {
            const fw = parseFloat(inputFarmer.value || '0');
            if (fw > 0) {
                avgGrip = fw;
                text = `Approx grip (per hand): <strong>${avgGrip.toFixed(1)} lbs</strong>`;
            }
        }

        if (avgGrip !== null) {
            const cat = categorizeLocal(avgGrip);
            if (cat) text += ` · rough category: <em>${cat}</em>`;
            summaryEl.style.display = 'block';
            summaryEl.innerHTML = text;
        } else {
            summaryEl.style.display = 'none';
            summaryEl.innerHTML = '';
        }
    }

    testTypeSelect.addEventListener('change', updateFieldVisibility);
    [inputBody, inputLeft, inputRight, inputHang, inputFarmer].forEach(el => {
        if (!el) return;
        el.addEventListener('input', updateSummary);
    });

    updateFieldVisibility();
</script>

</body>
</html>
