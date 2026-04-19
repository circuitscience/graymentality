<?php
/**
 * exFIT • Weight Management Tracker
 *
 * This page lets a logged-in user:
 *  - Log a "body composition" check-in (weight + circumference measurements)
 *  - Automatically convert metric/imperial inputs to kg/cm for storage
 *  - Save the raw measurements into `body_comp_logs`
 *  - Call MySQL functions to derive metrics:
 *      - BMI                         (fn_bmi)
 *      - Waist–hip ratio + category  (fn_whr, fn_whr_category)
 *      - Estimated body fat %        (fn_bodyfat_navy)
 *      - Lean mass & fat mass        (fn_lean_mass, inline calc)
 *      - Adjusted BMI for muscularity (fn_adj_bmi)
 *  - Show the most recent body-comp snapshot in a summary strip + “Results” tab
 *  - Show a “Daily Intake Snapshot” using:
 *      - `protein_logs`   (grams + daily goal)
 *      - `hydration_logs` (liters + daily goal)
 *      - `creatine_logs`  (grams taken today)
 *
 * Flow:
 *  1. Session guard: require `$_SESSION['user_id']`, otherwise redirect to login.
 *  2. Load user profile defaults from `users` (gender, age, units, height, measures).
 *  3. If POST:
 *      - Read check-in date, units, weight, and optional tape measures.
 *      - Normalize metric/imperial -> kg/cm using `to_kg()` and `to_cm()`.
 *      - Fall back to stored profile values when form fields are left blank.
 *      - Validate inputs (weight required, height must exist in profile).
 *      - In a transaction:
 *          a) INSERT new row into `body_comp_logs` with raw metrics.
 *          b) UPDATE that row to fill all derived metrics via SQL functions.
 *  4. Always fetch:
 *      - Latest `body_comp_logs` row for this user (for UI summary + results panel).
 *      - Today’s protein / hydration / creatine logs for the “Daily Intake Snapshot”.
 *  5. Render Tailwind-based UI with:
 *      - Tabbed layout (Check-in / Results / Education).
 *      - Check-in form (measurements + subjective sliders).
 *      - Results snapshot (BMI, body fat, lean mass, WHR, risk category).
 *      - Education cards as stubs for future long-form content.
 *      - Nutrition strip linking to the other tracker modules.
 *
 * Dependencies:
 *  - `$conn` : mysqli connection from `config.php`.
 *  - Auth: `$_SESSION['user_id']` must be set.
 *  - DB tables:
 *      - users
 *      - body_comp_logs
 *      - protein_logs
 *      - hydration_logs
 *      - creatine_logs
 *  - Custom MySQL functions for body-comp math: fn_bmi, fn_whr, fn_whr_category,
 *    fn_bodyfat_navy, fn_lean_mass, fn_adj_bmi.
 */

// weightloss_tracker.php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$userId = (int)($authUser['id'] ?? 0);

/* ---------------------------------------------------
 * 1) Fetch user + calorie profile
 * --------------------------------------------------- */

$sqlUser = "
    SELECT
        u.gender,
        u.age,
        u.weight,
        u.height,
        u.units,
        u.waist,
        u.hips,
        u.first_name,
        u.last_name,
        u.user_name,
        u.id
    FROM users AS u
    WHERE u.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sqlUser);
$stmt->bind_param('i', $userId);
$stmt->execute();
$resUser = $stmt->get_result();
$user = $resUser->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found.');
}

// Fallbacks
$gender = $user['gender'] ?? null;
$age    = (int)($user['age'] ?? 0);
$units  = $user['units'] ?? 'metric';

$weightRaw = (float)$user['weight']; // may be kg or lb
$heightRaw = (float)$user['height']; // may be cm or inches
$waistRaw  = (float)$user['waist'];
$hipsRaw   = (float)$user['hips'];

// Convert to metric if needed
if ($units === 'imperial') {
    $weightKg = $weightRaw * 0.453592; // lb -> kg
    $heightCm = $heightRaw * 2.54;     // in -> cm
    $waistCm  = $waistRaw * 2.54;
    $hipsCm   = $hipsRaw * 2.54;
} else {
    $weightKg = $weightRaw;
    $heightCm = $heightRaw;
    $waistCm  = $waistRaw;
    $hipsCm   = $hipsRaw;
}

// Fetch or create calorie profile
$sqlProfile = "
    SELECT activity_level, goal, body_type
    FROM user_calorie_profiles
    WHERE user_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sqlProfile);
$stmt->bind_param('i', $userId);
$stmt->execute();
$resProfile = $stmt->get_result();
$profile = $resProfile->fetch_assoc();
$stmt->close();

if (!$profile) {
    // Create default profile
    $defaultActivity = 'moderate';
    $defaultGoal     = 'maintain';

    // call SQL functions for BMI/WHR/body_type
    $sqlInit = "
        INSERT INTO user_calorie_profiles (user_id, activity_level, goal, body_type)
        VALUES (?, ?, ?, 'unknown')
    ";
    $stmt = $conn->prepare($sqlInit);
    $stmt->bind_param('iss', $userId, $defaultActivity, $defaultGoal);
    $stmt->execute();
    $stmt->close();

    $profile = [
        'activity_level' => $defaultActivity,
        'goal'           => $defaultGoal,
        'body_type'      => 'unknown',
    ];
}

$activityLevel = $profile['activity_level'];
$goal          = $profile['goal'];
$bodyTypeDb    = $profile['body_type'];

/* ---------------------------------------------------
 * 2) Compute BMI, WHR, BMR, TDEE via MySQL functions
 * --------------------------------------------------- */
function getScalar(mysqli $conn, string $sql, array $params, string $types) {
    $stmt = $conn->prepare($sql);
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stmt->bind_result($val);
    $stmt->fetch();
    $stmt->close();
    return $val;
}

$bmi = getScalar(
    $conn,
    "SELECT calc_bmi(?, ?)",
    [$weightKg, $heightCm],
    "dd"
);

$whr = getScalar(
    $conn,
    "SELECT calc_whr(?, ?)",
    [$waistCm, $hipsCm],
    "dd"
);

$bmr = getScalar(
    $conn,
    "SELECT calc_bmr(?, ?, ?, ?)",
    [$gender, $weightKg, $heightCm, $age],
    "sddi"
);

$tdee = getScalar(
    $conn,
    "SELECT calc_tdee(?, ?)",
    [$bmr, $activityLevel],
    "ds"
);

$bodyTypeCalc = getScalar(
    $conn,
    "SELECT classify_body_type(?, ?, ?)",
    [$bmi, $whr, $gender],
    "dds"
);

// Auto-update body_type if unknown
if ($bodyTypeDb === 'unknown' && $bodyTypeCalc) {
    $stmt = $conn->prepare("
        UPDATE user_calorie_profiles
        SET body_type = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->bind_param('si', $bodyTypeCalc, $userId);
    $stmt->execute();
    $stmt->close();
    $bodyTypeDb = $bodyTypeCalc;
}

/* ---------------------------------------------------
 * 3) Calorie Targets (simple logic)
 * --------------------------------------------------- */

$deficit = 400; // default for weight loss
$surplusMesomorph = 250;
$surplusEcto      = 400;
$surplusEndo      = 150;

switch ($bodyTypeDb) {
    case 'ectomorph':
        $surplus = $surplusEcto;
        break;
    case 'endomorph':
        $surplus = $surplusEndo;
        $deficit = 500;
        break;
    case 'mesomorph':
    default:
        $surplus = $surplusMesomorph;
        break;
}

$tdeeMaintain = $tdee;
$tdeeLoss     = max(1200, $tdee - $deficit);
$tdeeGainRest = $tdee;             // rest day
$tdeeGainTrain= $tdee + $surplus;  // training day

// Default target for UI based on goal
$defaultTarget = $tdeeMaintain;
if ($goal === 'loss') {
    $defaultTarget = $tdeeLoss;
} elseif ($goal === 'gain') {
    $defaultTarget = $tdeeGainTrain;
}

/* ---------------------------------------------------
 * 4) Handle POST: log today
 * --------------------------------------------------- */

$today = date('Y-m-d');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isTrainingDay   = isset($_POST['is_training_day']) ? 1 : 0;
    $actualCalories  = isset($_POST['actual_calories']) ? (float)$_POST['actual_calories'] : null;
    $targetCalories  = isset($_POST['target_calories']) ? (float)$_POST['target_calories'] : $defaultTarget;
    $bodyWeightInput = isset($_POST['body_weight']) ? (float)$_POST['body_weight'] : null;
    $notes           = isset($_POST['notes']) ? trim($_POST['notes']) : null;

    // convert logged weight to kg
    if (!empty($bodyWeightInput)) {
        if ($units === 'imperial') {
            $bodyWeightKg = $bodyWeightInput * 0.453592;
        } else {
            $bodyWeightKg = $bodyWeightInput;
        }
    } else {
        $bodyWeightKg = null;
    }

    // Upsert today's row
    $stmt = $conn->prepare("
        INSERT INTO calories_log
            (user_id, log_date, is_training_day, bmr, tdee, target_calories, actual_calories, body_weight_kg, notes)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_training_day = VALUES(is_training_day),
            bmr             = VALUES(bmr),
            tdee            = VALUES(tdee),
            target_calories = VALUES(target_calories),
            actual_calories = VALUES(actual_calories),
            body_weight_kg  = VALUES(body_weight_kg),
            notes           = VALUES(notes)
    ");

    $stmt->bind_param(
        'isiddddss',
        $userId,
        $today,
        $isTrainingDay,
        $bmr,
        $tdee,
        $targetCalories,
        $actualCalories,
        $bodyWeightKg,
        $notes
    );

    if ($stmt->execute()) {
        $message = 'Today\'s entry saved.';
    } else {
        $message = 'Error saving entry: ' . $stmt->error;
    }
    $stmt->close();
}

/* ---------------------------------------------------
 * 5) Fetch recent log entries
 * --------------------------------------------------- */

$sqlHistory = "
    SELECT log_date, is_training_day, target_calories, actual_calories, body_weight_kg, notes
    FROM calories_log
    WHERE user_id = ?
    ORDER BY log_date DESC
    LIMIT 14
";
$stmt = $conn->prepare($sqlHistory);
$stmt->bind_param('i', $userId);
$stmt->execute();
$resHistory = $stmt->get_result();
$history = $resHistory->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>exFIT – Weight Loss & Calorie Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #05060a;
            color: #f7f7ff;
        }
        header, footer {
            background: #0f1020;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #22243a;
        }
        header h1 {
            margin: 0;
            font-size: 1.4rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #ff7b1a;
        }
        header span.logo-circle {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            border: 2px solid #b46fff;
            box-shadow: 0 0 8px rgba(180,111,255,0.8);
            margin-right: 8px;
        }
        main {
            padding: 16px;
            max-width: 1100px;
            margin: 0 auto;
        }
        .grid {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
            gap: 16px;
        }
        .card {
            background: #0b0c15;
            border-radius: 14px;
            border: 1px solid #242640;
            padding: 16px 18px;
            box-shadow: 0 0 24px rgba(0,0,0,0.45);
        }
        .card h2 {
            margin: 0 0 8px;
            font-size: 1.1rem;
            color: #ff7b1a;
        }
        .card h3 {
            margin: 12px 0 4px;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9aa0ff;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
            padding: 3px 0;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            border: 1px solid #34375b;
            color: #cfd1ff;
        }
        .pill.orange {
            border-color: #ff7b1a;
            color: #ffb88a;
        }
        form label {
            display: block;
            font-size: 0.86rem;
            margin-top: 8px;
        }
        form input[type="number"],
        form input[type="text"],
        form textarea,
        form select {
            width: 100%;
            margin-top: 3px;
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid #303357;
            background: #060713;
            color: #f7f7ff;
            font-size: 0.9rem;
        }
        form textarea {
            resize: vertical;
            min-height: 52px;
        }
        .inline {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .inline input[type="checkbox"] {
            transform: scale(1.1);
        }
        button {
            margin-top: 12px;
            padding: 8px 14px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #ff7b1a, #ff4d7d);
            color: #05060a;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
        }
        button:hover {
            filter: brightness(1.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 0.85rem;
        }
        table th, table td {
            padding: 6px 4px;
            border-bottom: 1px solid #20223b;
            text-align: left;
        }
        table th {
            font-weight: 500;
            color: #aeb3ff;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            font-size: 0.75rem;
        }
        .badge-training {
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 0.7rem;
            border: 1px solid #ff7b1a;
            color: #ffb88a;
        }
        .badge-rest {
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 0.7rem;
            border: 1px solid #3b8fdf;
            color: #c2ddff;
        }
        .message {
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: #88ffb5;
        }
        footer {
            margin-top: 24px;
            border-top: 1px solid #22243a;
            font-size: 0.8rem;
            color: #8084b0;
        }
        @media (max-width: 800px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        function updateTarget() {
            const g = document.querySelector('input[name="goal_choice"]:checked')?.value;
            const isTraining = document.getElementById('is_training').checked;

            const tMaintain = parseFloat('<?php echo (float)$tdeeMaintain; ?>');
            const tLoss     = parseFloat('<?php echo (float)$tdeeLoss; ?>');
            const tGainRest = parseFloat('<?php echo (float)$tdeeGainRest; ?>');
            const tGainTrain= parseFloat('<?php echo (float)$tdeeGainTrain; ?>');

            let target = tMaintain;
            if (g === 'loss') {
                target = tLoss;
            } else if (g === 'gain') {
                target = isTraining ? tGainTrain : tGainRest;
            }

            const input = document.getElementById('target_calories');
            if (input) {
                input.value = Math.round(target);
            }
        }
    </script>
</head>
<body>
<header>
    <div style="display:flex;align-items:center;">
        <span class="logo-circle"></span>
        <h1>exFIT • Weightloss Tracker</h1>
    </div>
    <div class="pill orange">
        <?php echo htmlspecialchars($user['first_name'] ?? $user['user_name'] ?? 'User'); ?>
    </div>
</header>

<main>
    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="grid">
        <!-- LEFT: Info / Recommendations -->
        <section class="card">
            <h2>Your Metabolic Snapshot</h2>
            <div class="stat-row"><span>BMI</span><span><?php echo $bmi ? number_format((float)$bmi, 1) : 'N/A'; ?></span></div>
            <div class="stat-row"><span>Waist–Hip Ratio</span><span><?php echo $whr ? number_format((float)$whr, 2) : 'N/A'; ?></span></div>
            <div class="stat-row"><span>Body Type (exFIT)</span><span><?php echo htmlspecialchars($bodyTypeDb); ?></span></div>
            <div class="stat-row"><span>BMR</span><span><?php echo $bmr ? round((float)$bmr) . ' kcal' : 'N/A'; ?></span></div>
            <div class="stat-row"><span>TDEE (maintain)</span><span><?php echo $tdeeMaintain ? round((float)$tdeeMaintain) . ' kcal' : 'N/A'; ?></span></div>

            <h3>Daily Targets</h3>
            <div class="stat-row"><span>Weight Loss</span><span><?php echo round((float)$tdeeLoss); ?> kcal</span></div>
            <div class="stat-row"><span>Maintain</span><span><?php echo round((float)$tdeeMaintain); ?> kcal</span></div>
            <div class="stat-row"><span>Gain – Rest Day</span><span><?php echo round((float)$tdeeGainRest); ?> kcal</span></div>
            <div class="stat-row"><span>Gain – Training Day</span><span><?php echo round((float)$tdeeGainTrain); ?> kcal</span></div>

            <h3>Simple Strategy</h3>
            <ul style="font-size:0.85rem; padding-left:18px; margin-top:4px; color:#d1d3ff;">
                <li>On <strong>training days</strong>, eat close to the gain target if your goal is muscle.</li>
                <li>On <strong>rest days</strong>, stay near maintain or loss target to keep fat down.</li>
                <li>Keep protein high, spread across meals, and avoid huge calorie spikes at night.</li>
            </ul>
        </section>

        <!-- RIGHT: Daily Log Form -->
        <section class="card">
            <h2>Log Today (<?php echo htmlspecialchars($today); ?>)</h2>
            <form method="post" oninput="updateTarget()">
                <label>Goal for today:</label>
                <div class="inline">
                    <label class="inline">
                        <input type="radio" name="goal_choice" value="loss"
                            <?php echo $goal === 'loss' ? 'checked' : ''; ?>>
                        <span style="font-size:0.8rem;">Loss</span>
                    </label>
                    <label class="inline">
                        <input type="radio" name="goal_choice" value="maintain"
                            <?php echo $goal === 'maintain' ? 'checked' : ''; ?>>
                        <span style="font-size:0.8rem;">Maintain</span>
                    </label>
                    <label class="inline">
                        <input type="radio" name="goal_choice" value="gain"
                            <?php echo $goal === 'gain' ? 'checked' : ''; ?>>
                        <span style="font-size:0.8rem;">Gain</span>
                    </label>
                </div>

                <label class="inline" style="margin-top:10px;">
                    <input type="checkbox" name="is_training_day" id="is_training" <?php echo isset($_POST['is_training_day']) ? 'checked' : ''; ?>>
                    <span style="font-size:0.8rem;">This was a training day</span>
                </label>

                <label>Target calories for today (kcal):</label>
                <input
                    type="number"
                    id="target_calories"
                    name="target_calories"
                    step="1"
                    value="<?php echo round((float)$defaultTarget); ?>">

                <label>Actual calories consumed (approx):</label>
                <input
                    type="number"
                    name="actual_calories"
                    step="1"
                    min="0"
                    placeholder="e.g. 2100">

                <label>Body weight today (<?php echo $units === 'imperial' ? 'lb' : 'kg'; ?>):</label>
                <input
                    type="number"
                    name="body_weight"
                    step="0.1"
                    min="0"
                    placeholder="Optional">

                <label>Notes (hunger, energy, cravings):</label>
                <textarea name="notes" placeholder="Optional..."></textarea>

                <button type="submit">Save Today</button>
            </form>
        </section>
    </div>

    <!-- History -->
    <section class="card" style="margin-top:16px;">
        <h2>Recent Days</h2>
        <?php if ($history): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Actual</th>
                        <th>Weight (kg)</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['log_date']); ?></td>
                        <td>
                            <?php if ((int)$row['is_training_day'] === 1): ?>
                                <span class="badge-training">Training</span>
                            <?php else: ?>
                                <span class="badge-rest">Rest</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['target_calories'] !== null ? round((float)$row['target_calories']) : '—'; ?></td>
                        <td><?php echo $row['actual_calories'] !== null ? round((float)$row['actual_calories']) : '—'; ?></td>
                        <td><?php echo $row['body_weight_kg'] !== null ? number_format((float)$row['body_weight_kg'], 1) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($row['notes'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="font-size:0.85rem;color:#b1b4e8;">No entries yet. Log today to start the trend line.</p>
        <?php endif; ?>
    </section>
</main>

<footer>
    <div style="max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;">
        <span>exFIT • Gray Mentality • Die Living</span>
        <span style="font-size:0.75rem;">Weightloss module v1</span>
    </div>
</footer>
</body>
</html>
