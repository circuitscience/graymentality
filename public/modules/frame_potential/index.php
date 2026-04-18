<?php
declare(strict_types=1);

/**
 * frame_potential_lab.php
 *
 * Purpose:
 * --------
 * Interactive “Frame Potential Lab” for exFIT.
 * Uses basic anthropometric inputs (height, weight, wrist, ankle,
 * and optional girths) to estimate a rough upper limit for muscular
 * size on the user’s current skeletal frame, then compares their
 * current state to that potential.
 *
 * Key Features:
 * -------------
 * - Accepts inputs in metric (cm / kg) or imperial (in / lb) and
 *   normalizes everything to cm / kg internally.
 * - Uses simple rule-of-thumb formulas to estimate:
 *     • Max realistic upper arm girth
 *     • Max realistic calf girth
 *     • Max realistic chest circumference
 * - Computes an overall “Frame Score” (0–100) based on:
 *     • Average % of predicted potential (if current girths given), or
 *     • A synthetic score centered on a “muscular but not obese” BMI.
 * - Renders three main UI blocks:
 *     • Hero section with explanation and current Frame Score gauge
 *     • Breakdown card showing predicted ceilings vs. current values
 *     • “Expectations & Effort Curve” card explaining exFIT philosophy
 *     - Responsive design for desktop and mobile.
 *
 * Notes:
 * ------
 * - This is a context / education tool, not a diagnostic or promise.
 * - Formulas are deliberately simple and should be treated as
 *   approximations to spark discussion and expectation-setting.
 * - Optional: can log each run to `frame_potential_logs` if a DB
 *   connection and user session are available (see commented-out code).
 *
 * Dependencies:
 * -------------
 * - Pure PHP + HTML/CSS/JS; no external libraries required.
 * - Optional MySQL logging (requires config + session if enabled).
 *
 * Author: Jerry + exFIT / Gray Mentality (with AI assist)
 * Date:    <?php echo date('Y-m-d'); ?>
 */
// If you have a config + session:
 session_start();
 require_once __DIR__ . '/../../../config/config.php'; // adjust path
 require_once __DIR__ . '/frame_potential_info.php'; // info modal content

// Simple HTML escape
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Convert a length stored in cm into display units
function format_length(float $cm, string $unitSystem): string {
    if ($unitSystem === 'imperial') {
        $in = $cm / 2.54;
        return number_format($in, 1); // inches
    }
    return number_format($cm, 1);     // cm
}

function length_unit_label(string $unitSystem): string {
    return $unitSystem === 'imperial' ? 'in' : 'cm';
}

// Defaults / initial
$result = null;
$errors = [];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unitSystem   = $_POST['unit_system'] ?? 'metric'; // metric | imperial
    $gender       = $_POST['gender'] ?? 'other';

    // Raw inputs as strings
    $heightRaw       = $_POST['height']        ?? '';
    $weightRaw       = $_POST['weight']        ?? '';
    $wristRaw        = $_POST['wrist']         ?? '';
    $ankleRaw        = $_POST['ankle']         ?? '';
    $armCurrentRaw   = $_POST['arm_current']   ?? '';
    $calfCurrentRaw  = $_POST['calf_current']  ?? '';
    $chestCurrentRaw = $_POST['chest_current'] ?? '';

    // Basic numeric validation
    if ($heightRaw === '' || !is_numeric($heightRaw) || (float)$heightRaw <= 0) {
        $errors[] = "Please enter a valid height greater than zero.";
    }
    if ($weightRaw === '' || !is_numeric($weightRaw) || (float)$weightRaw <= 0) {
        $errors[] = "Please enter a valid body weight greater than zero.";
    }
    if ($wristRaw === ''  || !is_numeric($wristRaw)  || (float)$wristRaw <= 0) {
        $errors[] = "Please enter a valid wrist circumference greater than zero.";
    }
    if ($ankleRaw === ''  || !is_numeric($ankleRaw)  || (float)$ankleRaw <= 0) {
        $errors[] = "Please enter a valid ankle circumference greater than zero.";
    }

    if (!$errors) {
        // Cast after validation
        $height      = (float)$heightRaw;
        $weight      = (float)$weightRaw;
        $wrist       = (float)$wristRaw;
        $ankle       = (float)$ankleRaw;
        $armCurrent  = ($armCurrentRaw   !== '' && is_numeric($armCurrentRaw))   ? (float)$armCurrentRaw   : 0.0;
        $calfCurrent = ($calfCurrentRaw  !== '' && is_numeric($calfCurrentRaw))  ? (float)$calfCurrentRaw  : 0.0;
        $chestCurrent= ($chestCurrentRaw !== '' && is_numeric($chestCurrentRaw)) ? (float)$chestCurrentRaw : 0.0;

        // ---- Convert everything to metric (cm / kg) ----
        if ($unitSystem === 'imperial') {
            // height: inches → cm, weight: lbs → kg, girths: inches → cm
            $heightCm       = $height * 2.54;
            $weightKg       = $weight * 0.453592;
            $wristCm        = $wrist * 2.54;
            $ankleCm        = $ankle * 2.54;
            $armCurrentCm   = $armCurrent   > 0 ? $armCurrent   * 2.54 : 0;
            $calfCurrentCm  = $calfCurrent  > 0 ? $calfCurrent  * 2.54 : 0;
            $chestCurrentCm = $chestCurrent > 0 ? $chestCurrent * 2.54 : 0;
        } else {
            // metric already
            $heightCm       = $height;
            $weightKg       = $weight;
            $wristCm        = $wrist;
            $ankleCm        = $ankle;
            $armCurrentCm   = $armCurrent;
            $calfCurrentCm  = $calfCurrent;
            $chestCurrentCm = $chestCurrent;
        }

        // ---- Simple demo formulas (tweak/replace later) ----
        // predicted arm = wrist * 2.4 + small height factor
        $armPredCm   = $wristCm * 2.4 + max(0, ($heightCm - 170) * 0.05);

        // predicted calf = ankle * 2.3 + height factor
        $calfPredCm  = $ankleCm * 2.3 + max(0, ($heightCm - 170) * 0.03);

        // predicted chest = height * 0.53 + wrist/ankle contribution
        $chestPredCm = $heightCm * 0.53 + ($wristCm + $ankleCm) * 0.25;

        // Clamp to reasonable ranges
        $armPredCm   = max(30, min($armPredCm, 60));
        $calfPredCm  = max(30, min($calfPredCm, 60));
        $chestPredCm = max(90, min($chestPredCm, 150));

        // Progress percentages (if current provided)
        $armPct   = ($armCurrentCm  > 0) ? min(100, round($armCurrentCm  / $armPredCm   * 100, 1)) : null;
        $calfPct  = ($calfCurrentCm > 0) ? min(100, round($calfCurrentCm / $calfPredCm  * 100, 1)) : null;
        $chestPct = ($chestCurrentCm> 0) ? min(100, round($chestCurrentCm/ $chestPredCm * 100, 1)) : null;

        // Overall frame score: average of available percentages or synthetic index
        $percentages = array_filter(
            [$armPct, $calfPct, $chestPct],
            function ($v) { return $v !== null; }     // old-style anonymous fn for compatibility
        );

        if ($percentages) {
            $frameScore = array_sum($percentages) / count($percentages);
        } else {
            // Synthetic frame score based on mass vs. frame
            $bmi = $weightKg / pow($heightCm / 100, 2);
            // Score centered around “muscular but not obese” ~25
            $frameScore = max(0, min(100, (25 - abs($bmi - 25)) * 5));
        }

        $frameScore = round($frameScore, 1);

        // ---- Optional: log to DB ----
        
        if (isset($conn) && $conn instanceof mysqli) {
            $userId = $_SESSION['user_id'] ?? null;
            $stmt = $conn->prepare("
                INSERT INTO frame_potential_logs
                  (user_id, height_cm, weight_kg, wrist_cm, ankle_cm, gender,
                   arm_current_cm, calf_current_cm, chest_current_cm,
                   arm_pred_cm, calf_pred_cm, chest_pred_cm, frame_score)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param(
                "iddddsddddddd",
                $userId,
                $heightCm,
                $weightKg,
                $wristCm,
                $ankleCm,
                $gender,
                $armCurrentCm,
                $calfCurrentCm,
                $chestCurrentCm,
                $armPredCm,
                $calfPredCm,
                $chestPredCm,
                $frameScore
            );
            $stmt->execute();
            $stmt->close();
        }
    
        $result = [
            'unit_system'      => $unitSystem,
            'gender'           => $gender,
            'height_cm'        => $heightCm,
            'weight_kg'        => $weightKg,
            'wrist_cm'         => $wristCm,
            'ankle_cm'         => $ankleCm,
            'arm_current_cm'   => $armCurrentCm,
            'calf_current_cm'  => $calfCurrentCm,
            'chest_current_cm' => $chestCurrentCm,
            'arm_pred_cm'      => $armPredCm,
            'calf_pred_cm'     => $calfPredCm,
            'chest_pred_cm'    => $chestPredCm,
            'arm_pct'          => $armPct,
            'calf_pct'         => $calfPct,
            'chest_pct'        => $chestPct,
            'frame_score'      => $frameScore,
        ];
    }
}

function exfit_effort_band(float $frameScore): array {
    // FrameScore is currently 0–100 “how close to your estimated frame ceiling”
    if ($frameScore < 40) {
        return [
            'label' => 'Underbuilt Frame',
            'tag'   => 'Foundations Zone',
            'desc'  => 'You have a lot of easy, low-risk progress available. With 2–3 consistent lifting sessions per week, you can add muscle for years without getting anywhere near your structural ceiling.'
        ];
    } elseif ($frameScore < 60) {
        return [
            'label' => 'Building Nicely',
            'tag'   => 'ExFIT Runway',
            'desc'  => 'You are somewhere in the middle of your potential. This is prime ExFIT territory: progressive overload, decent protein, and sleep will slowly “fill out” your frame over the next decade.'
        ];
    } elseif ($frameScore < 80) {
        return [
            'label' => 'ExFIT Sweet Spot',
            'tag'   => '75% Potential Zone',
            'desc'  => 'You are approaching the zone ExFIT is designed around: roughly 70–80% of your max potential. You look clearly trained, joints are usually happy, and the effort is sustainable for life, not just a 12-week sprint.'
        ];
    } elseif ($frameScore < 90) {
        return [
            'label' => 'High Realization',
            'tag'   => 'Advanced Territory',
            'desc'  => 'You are pushing into the high end of what your frame can comfortably support. Further gains will demand disproportionate effort and tighter life constraints (food, sleep, stress). This is optional, not required.'
        ];
    } else {
        return [
            'label' => 'Genetic Outlier Territory',
            'tag'   => 'Edge of the Map',
            'desc'  => 'You are very close to your estimated ceiling. Maintaining this look is a part-time job. ExFIT does not expect or demand this state; it exists for the tiny fraction of people who enjoy living at the edge.'
        ];
    }
}

$effortBand = null;
if ($result !== null) {
    $effortBand = exfit_effort_band($result['frame_score']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Frame Potential Lab | exFIT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
    /* Match the hub background for frame_potential */
    --module-bg-image: url('../assets/dumbellcurlman.png');
}

* { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    background: #000;
    color: #f5f5f5;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    min-height: 100%;
    overflow-x: hidden; /* no horizontal scroll */
}

/* Shell no longer controls scrolling */
.module-shell {
    position: relative;
    width: 100%;
    /* DO NOT set height or overflow here */
}

/* Background is fixed to viewport, page scrolls above it */
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

/* Foreground content stack */
.module-content {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Header stays at top of content flow */
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

/* Main content flows down the page; body scrolls */
.module-main {
    flex: 1;
    display: flex;
    align-items: flex-start;   /* top aligned */
    justify-content: center;   /* centered horizontally */
    padding: 1.2rem 1rem 1.8rem;
}

/* Card grows naturally; NO inner scroll */
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
    /* no overflow-y here */
}

.module-card h1 {
    font-size: 1.3rem;
    margin: 0 0 0.4rem;
}

.module-card p {
    font-size: 0.85rem;
    opacity: 0.9;
}

/* Keep/merge your old inner CSS below this line
   (.page-wrap, .card, .metrics-grid, etc.) */

        /* You can keep or merge your existing CSS below this line if you want
           to preserve the original card/grid/form styling.
           Just paste it here and it will apply inside .module-card. */
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
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: #020617;          /* base fallback */
    color: var(--text-main);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;           /* needed for ::before layering */
    overflow-x: hidden;
}

body::before {
    content: "";
    position: fixed;              /* stays put on scroll */
    inset: 0;
    z-index: -2;

    /* background image + a dark tint */
    background:
        linear-gradient(135deg, rgba(3,7,18,0.92), rgba(15,23,42,0.9)),
        url("/public/modules/assets/couple.png") center center / cover no-repeat;

    /* blur + darken so text pops */
    filter: blur(6px) brightness(0.5);
    transform: scale(1.05);       /* avoids blur edges at screen borders */
}

        .page-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        header {
            padding: 1.5rem 1.5rem 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
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
        main {
            padding: 1rem 1.5rem 2rem;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }
        .hero {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 2fr);
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }
        @media (max-width: 900px) {
            .hero { grid-template-columns: minmax(0,1fr); }
        }
        .hero-card {
            background: linear-gradient(145deg, rgba(15,23,42,0.9), rgba(3,7,18,0.95));
            border-radius: 1.25rem;
            padding: 1.5rem;
            border: 1px solid rgba(148,163,184,0.15);
            box-shadow:
              0 25px 40px rgba(0,0,0,0.8),
              0 0 0 1px rgba(15,23,42,0.6);
            position: relative;
            overflow: hidden;
        }
        .hero-card::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
              radial-gradient(circle at 10% 0%, rgba(255,122,26,0.12), transparent 60%),
              radial-gradient(circle at 90% 100%, rgba(168,85,255,0.18), transparent 60%);
            opacity: 0.9;
            pointer-events: none;
        }
        .hero-card > * {
            position: relative;
            background: rgba(10, 14, 25, 0.65);  /* semi-transparent */
        border-radius: 1.1rem;
        padding: 1.25rem 1.5rem;
        border: 1px solid rgba(148,163,184,0.35);
        box-shadow:
        0 18px 30px rgba(0,0,0,0.85),
        0 0 0 1px rgba(15,23,42,0.9);
        }
        .hero-title {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .hero-highlight {
            background: linear-gradient(90deg, var(--accent-orange), var(--accent-purple));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .hero-sub {
            color: var(--text-muted);
            font-size: 0.95rem;
            max-width: 36rem;
            line-height: 1.5;
        }
        .tagline {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #e5e7eb;
            border-left: 2px solid var(--accent-orange);
            padding-left: 0.75rem;
        }
        .score-card {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.25rem;
        }
        .score-gauge {
            position: relative;
            width: 170px;
            height: 170px;
            border-radius: 999px;
            background: radial-gradient(circle at 30% 10%, rgba(255,255,255,0.08), transparent 50%),
                        radial-gradient(circle at 80% 100%, rgba(168,85,255,0.35), transparent 60%);
            border: 1px solid rgba(156,163,175,0.35);
            box-shadow: 0 0 40px rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .score-ring {
            width: 84%;
            height: 84%;
            border-radius: 999px;
            border: 2px solid rgba(15,23,42,0.9);
            background: conic-gradient(
                from 225deg,
                var(--accent-orange) 0deg,
                var(--accent-purple) 140deg,
                rgba(30,64,175,0.2) 260deg,
                rgba(15,23,42,0.9) 320deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .score-inner {
            width: 70%;
            height: 70%;
            border-radius: 999px;
            background: rgba(15,23,42,0.95);
            border: 1px solid rgba(148,163,184,0.35);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.1rem;
        }
        .score-label {
            font-size: 0.7rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .score-value {
            font-size: 2.2rem;
            font-weight: 700;
        }
        .score-caption {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .score-pill {
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            border: 1px solid rgba(148,163,184,0.35);
            background: rgba(15,23,42,0.8);
        }
        .score-pill strong {
            color: var(--accent-orange);
        }

        .layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(0, 2fr);
            gap: 1.5rem;
        }
        @media (max-width: 1000px) {
            .layout-grid { grid-template-columns: minmax(0,1fr); }
        }
        .card {
            background: rgba(10, 14, 25, 0.65);  /* semi-transparent */
        border-radius: 1.1rem;
        padding: 1.25rem 1.5rem;
        border: 1px solid rgba(148,163,184,0.35);
        box-shadow:
        0 18px 30px rgba(0,0,0,0.85),
        0 0 0 1px rgba(15,23,42,0.9);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.9rem;
        }
        .card-title {
            font-size: 1rem;
            font-weight: 600;
        }
        .card-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem 1rem;
        }
        @media (max-width: 700px) {
            form { grid-template-columns: minmax(0,1fr); }
        }
        .full-span {
            grid-column: 1 / -1;
        }
        label {
            display: block;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            color: #e5e7eb;
        }
        .field-hint {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        input, select {
            width: 100%;
            padding: 0.55rem 0.65rem;
            border-radius: 0.6rem;
            border: 1px solid rgba(55,65,81,0.9);
            background: #020617;
            color: var(--text-main);
            font-size: 0.85rem;
            outline: none;
        }
        input:focus, select:focus {
            border-color: var(--accent-purple);
            box-shadow: 0 0 0 1px rgba(168,85,255,0.7);
        }
        .fieldset-inline {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .radio-pill {
            position: relative;
        }
        .radio-pill input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .radio-pill label {
            border-radius: 999px;
            border: 1px solid rgba(75,85,99,0.9);
            padding: 0.3rem 0.75rem;
            font-size: 0.75rem;
            cursor: pointer;
            background: #020617;
            transition: 0.15s all ease;
        }
        .radio-pill input[type="radio"]:checked + label {
            border-color: var(--accent-orange);
            color: var(--accent-orange);
            box-shadow: 0 0 12px rgba(248,113,113,0.3);
        }

        .btn-primary {
            border-radius: 999px;
            border: none;
            padding: 0.6rem 1.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            background: linear-gradient(135deg, var(--accent-orange), var(--accent-purple));
            color: #0b1020;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow:
                0 12px 25px rgba(0,0,0,0.9),
                0 0 18px rgba(248,113,113,0.45);
            margin-top: 0.3rem;
        }
        .btn-primary:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        .error-list {
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.5);
            color: var(--danger);
            border-radius: 0.75rem;
            padding: 0.7rem 0.9rem;
            font-size: 0.8rem;
            margin-bottom: 0.7rem;
        }
        .error-list ul {
            padding-left: 1.1rem;
            margin-top: 0.25rem;
        }

        /* Results styling */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
            margin-top: 0.5rem;
        }
        @media (max-width: 900px) {
            .metrics-grid { grid-template-columns: minmax(0,1fr); }
        }
        .metric {
            background: var(--bg-card-alt);
            border-radius: 0.9rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid rgba(55,65,81,0.9);
        }
        .metric-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-muted);
        }
        .metric-main {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-top: 0.2rem;
        }
        .metric-value {
            font-size: 1rem;
            font-weight: 600;
        }
        .metric-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .bar-shell {
            margin-top: 0.4rem;
            width: 100%;
            height: 0.4rem;
            border-radius: 999px;
            background: #020617;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--accent-orange), var(--accent-purple));
            transition: width 0.7s ease-out;
        }
        .bar-label {
            margin-top: 0.2rem;
            font-size: 0.73rem;
            color: var(--text-muted);
        }

        .fact-strip {
            margin-top: 1rem;
            padding: 0.6rem 0.75rem;
            border-radius: 0.75rem;
            background: radial-gradient(circle at 0% 0%, rgba(248,113,113,0.18), transparent 50%),
                        radial-gradient(circle at 100% 100%, rgba(129,140,248,0.18), transparent 50%);
            border: 1px solid rgba(148,163,184,0.4);
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .fact-strip strong {
            color: var(--accent-orange);
        }

        footer {
            padding: 0.75rem 1.5rem 1.25rem;
            font-size: 0.75rem;
            color: var(--text-muted);
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
                <div class="module-title">Frame Potential Lab</div>
                <div class="module-subtitle">
                    Estimate your natural canvas before you start painting muscle on it.
                </div>
            </div>
            <button class="module-back" type="button" onclick="window.location.href='../index.php'">
                ‹ Modules
            </button>
        </header>

        <main class="module-main">
            <section class="module-card">
                <!-- 🔽 BEGIN: paste your existing page body content here -->

                <!-- Take EVERYTHING currently between:
                     <body>
                       <div class="page-wrap"> ... </div>
                     </body>
                     and paste that <div class="page-wrap">...</div> here. -->

                <!-- Example structure (do not literally add this if you already have it):
                <div class="page-wrap">
                    ... your existing header, hero, form, metrics, etc ...
                </div>
                -->
                <div class="page-wrap">
    <header>
        <div class="brand">
            <div class="brand-logo">exFIT</div>
            <div class="brand-text">
                <h1>Frame Potential Lab</h1>
                <p>Estimate your natural canvas before you start painting muscle on it.</p>
            </div>
        </div>
        <div class="score-pill">
            Gray Mentality • <strong>Structure before size</strong>
        </div>
    </header>

    <main>
    <!--  Hero Section - 1st row left -->    
    
    <section class="hero">
            <div class="hero-card">
                <h2 class="hero-title">
                    How big <span class="hero-highlight">could</span> you be?
                </h2>
                <p class="hero-sub">
                    Your bone structure sets an upper ceiling for how much muscle you can realistically carry.
                    This lab uses your height, wrist, and ankle size to build a rough model of your
                    <strong>maximum muscular potential</strong> — then compares it to where you are right now.
                </p>
                <p class="tagline">
                    This isn’t a sentence. It’s a compass. You may never care about hitting “100%,”
                    but you should know which direction your frame wants to go.
                </p>
            </div>
         <!-- Frame score card - 1st row right -->

            <div class="hero-card score-card">
                <div class="score-gauge">
                    <div class="score-ring">
                        <div class="score-inner">
                            <div class="score-label">Frame Score</div>
                            <div class="score-value" id="scoreValue">
                                <?= $result ? e((string)$result['frame_score']) : '–'; ?>
                            </div>
                            <div class="score-caption">
                                <?= $result ? 'of 100' : 'Awaiting input'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-subtitle">
                    <strong>How to read this:</strong><br>
                    0–40 = foundations • 40–70 = building nicely • 70–85 = ExFIT sweet spot • 85+ = edge-of-the-map effort.
                </div>
                <div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Why We Measure Wrist, Upper Calf & Upper Arm</h2>
            <p class="card-subtitle">Anthropometry • Frame Capacity • Muscular Expression</p>
        </div>
        <button type="button"
        class="btn btn-small btn-outline"
        data-fp-open-article>
    View scientific article on Frame Potential
</button>

    </div>

</div>
            </div>
        </section>

        <section class="layout-grid">
        
        <!-- Input Form Card - 2nd row left -->

        <article class="card">
                <div class="card-header">
                    <h3 class="card-title">Frame Inputs</h3>
                    <p class="card-subtitle">We’ll normalize everything to cm / kg behind the scenes.</p>
                </div>

                <?php if ($errors): ?>
                    <div class="error-list">
                        <strong>Fix these, then try again:</strong>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="full-span">
                        <label>Units</label>
                        <div class="fieldset-inline">
                            <div class="radio-pill">
                                <input type="radio" id="unit_metric" name="unit_system" value="metric"
                                    <?= empty($_POST['unit_system']) || ($_POST['unit_system'] ?? '') === 'metric' ? 'checked' : '' ?>>
                                <label for="unit_metric">Metric (cm / kg)</label>
                            </div>
                            <div class="radio-pill">
                                <input type="radio" id="unit_imperial" name="unit_system" value="imperial"
                                    <?= ($_POST['unit_system'] ?? '') === 'imperial' ? 'checked' : '' ?>>
                                <label for="unit_imperial">Imperial (in / lb)</label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="height">Height</label>
                        <input type="number" step="0.1" min="1" id="height" name="height"
                               value="<?= isset($_POST['height']) ? e((string)$_POST['height']) : '' ?>"
                               placeholder="e.g. 178">
                        <div class="field-hint">cm or inches, depending on units</div>
                    </div>

                    <div>
                        <label for="weight">Body weight</label>
                        <input type="number" step="0.1" min="1" id="weight" name="weight"
                               value="<?= isset($_POST['weight']) ? e((string)$_POST['weight']) : '' ?>"
                               placeholder="e.g. 80">
                        <div class="field-hint">kg or lbs</div>
                    </div>

                    <div>
                        <label for="wrist">Wrist circumference</label>
                        <input type="number" step="0.1" min="1" id="wrist" name="wrist"
                               value="<?= isset($_POST['wrist']) ? e((string)$_POST['wrist']) : '' ?>"
                               placeholder="e.g. 17">
                        <div class="field-hint">Measure over the styloid process.</div>
                    </div>

                    <div>
                        <label for="ankle">Ankle circumference</label>
                        <input type="number" step="0.1" min="1" id="ankle" name="ankle"
                               value="<?= isset($_POST['ankle']) ? e((string)$_POST['ankle']) : '' ?>"
                               placeholder="e.g. 22">
                        <div class="field-hint">Just above the ankle bones.</div>
                    </div>

                    <div class="full-span">
                        <label>Gender (for context only)</label>
                        <div class="fieldset-inline">
                            <div class="radio-pill">
                                <input type="radio" id="gender_m" name="gender" value="male"
                                    <?= ($_POST['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                                <label for="gender_m">Male</label>
                            </div>
                            <div class="radio-pill">
                                <input type="radio" id="gender_f" name="gender" value="female"
                                    <?= ($_POST['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                                <label for="gender_f">Female</label>
                            </div>
                            <div class="radio-pill">
                                <input type="radio" id="gender_o" name="gender" value="other"
                                    <?= empty($_POST['gender']) || ($_POST['gender'] ?? '') === 'other' ? 'checked' : '' ?>>
                                <label for="gender_o">Prefer not to say</label>
                            </div>
                        </div>
                    </div>

                    <div class="full-span">
                        <label>Current measurements <span class="field-hint">(optional but makes this interesting)</span></label>
                    </div>

                    <div>
                        <label for="arm_current">Upper arm (flexed)</label>
                        <input type="number" step="0.1" id="arm_current" name="arm_current"
                               value="<?= isset($_POST['arm_current']) ? e((string)$_POST['arm_current']) : '' ?>"
                               placeholder="e.g. 40">
                    </div>

                    <div>
                        <label for="calf_current">Calf</label>
                        <input type="number" step="0.1" id="calf_current" name="calf_current"
                               value="<?= isset($_POST['calf_current']) ? e((string)$_POST['calf_current']) : '' ?>"
                               placeholder="e.g. 38">
                    </div>

                    <div class="full-span">
                        <label for="chest_current">Chest (nipple line)</label>
                        <input type="number" step="0.1" id="chest_current" name="chest_current"
                               value="<?= isset($_POST['chest_current']) ? e((string)$_POST['chest_current']) : '' ?>"
                               placeholder="e.g. 105">
                    </div>

                    <div class="full-span">
                        <button type="submit" class="btn-primary">
                            Run frame analysis
                            <span>➜</span>
                        </button>
                    </div>
                </form>
            </article>

            <!-- Results Card - 2nd row right -->

            <article class="card">
                <div class="card-header">
                    <h3 class="card-title">Breakdown</h3>
                    <p class="card-subtitle">Where you are vs. where your skeleton says you could be.</p>
                </div>

                <?php if ($result): ?>
                    <div class="metrics-grid" id="metricsGrid">
                        <div class="metric" data-pct="<?= $result['arm_pct'] !== null ? $result['arm_pct'] : 0 ?>">
                <div class="metric-label">Upper Arm</div>
                <div class="metric-main">
                    <div class="metric-value">
                        <?php if ($result['arm_current_cm'] > 0): ?>
                        <?php
                            $lenUnit = length_unit_label($result['unit_system']);
                            $armCurrentDisp = format_length($result['arm_current_cm'], $result['unit_system']);
                        ?>
                <?= e($armCurrentDisp) . ' ' . e($lenUnit) ?>
                <?php else: ?>
                Not logged
            <?php endif; ?>
        </div>
        <div class="metric-sub">
            <?php
                $lenUnit = length_unit_label($result['unit_system']);
                $armPredDisp = format_length($result['arm_pred_cm'], $result['unit_system']);
            ?>
            Ceiling: <?= e($armPredDisp) . ' ' . e($lenUnit) ?>
        </div>
    </div>
    <div class="bar-shell">
        <div class="bar-fill"></div>
    </div>
    <div class="bar-label">
        <?= $result['arm_pct'] !== null
            ? e((string)$result['arm_pct']) . "% of your estimated ceiling"
            : "Add your arm size to see your %." ?>
    </div>
</div>
                        <div class="metric" data-pct="<?= $result['calf_pct'] !== null ? $result['calf_pct'] : 0 ?>">
    <div class="metric-label">Calf</div>
    <div class="metric-main">
        <div class="metric-value">
            <?php if ($result['calf_current_cm'] > 0): ?>
                <?php
                    $lenUnit = length_unit_label($result['unit_system']);
                    $calfCurrentDisp = format_length($result['calf_current_cm'], $result['unit_system']);
                ?>
                <?= e($calfCurrentDisp) . ' ' . e($lenUnit) ?>
            <?php else: ?>
                Not logged
            <?php endif; ?>
        </div>
        <div class="metric-sub">
            <?php
                $lenUnit = length_unit_label($result['unit_system']);
                $calfPredDisp = format_length($result['calf_pred_cm'], $result['unit_system']);
            ?>
            Ceiling: <?= e($calfPredDisp) . ' ' . e($lenUnit) ?>
        </div>
    </div>
    <div class="bar-shell">
        <div class="bar-fill"></div>
    </div>
    <div class="bar-label">
        <?= $result['calf_pct'] !== null
            ? e((string)$result['calf_pct']) . "% of your estimated ceiling"
            : "Add your calf size to see your %." ?>
    </div>
</div>


                        <div class="metric" data-pct="<?= $result['chest_pct'] !== null ? $result['chest_pct'] : 0 ?>">
    <div class="metric-label">Chest</div>
    <div class="metric-main">
        <div class="metric-value">
            <?php if ($result['chest_current_cm'] > 0): ?>
                <?php
                    $lenUnit = length_unit_label($result['unit_system']);
                    $chestCurrentDisp = format_length($result['chest_current_cm'], $result['unit_system']);
                ?>
                <?= e($chestCurrentDisp) . ' ' . e($lenUnit) ?>
            <?php else: ?>
                Not logged
            <?php endif; ?>
        </div>
        <div class="metric-sub">
            <?php
                $lenUnit = length_unit_label($result['unit_system']);
                $chestPredDisp = format_length($result['chest_pred_cm'], $result['unit_system']);
            ?>
            Ceiling: <?= e($chestPredDisp) . ' ' . e($lenUnit) ?>
        </div>
    </div>
    <div class="bar-shell">
        <div class="bar-fill"></div>
    </div>
    <div class="bar-label">
        <?= $result['chest_pct'] !== null
            ? e((string)$result['chest_pct']) . "% of your estimated ceiling"
            : "Add your chest size to see your %." ?>
    </div>
</div>

                    </div>

                    <div class="fact-strip">
                        <div>
                            <strong>Reminder:</strong> These ceilings are based on simple frame rules,
                            not destiny. They assume you’re lean, consistent, and lifting like someone who
                            wants to stay dangerous in their 60s, not just inflated for a summer.
                        </div>
                        <div>
                            Use this as a <strong>context tool</strong>: it tells you whether adding
                            2–3 more inches of arm over the next decade is plausible, not whether you’re
                            “good enough” right now.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="fact-strip">
                        <div>
                            Enter your frame details on the left. We’ll map out your <strong>maximum
                            realistic arm, calf, and chest size</strong> at lean levels, then show how
                            close you are to those targets.
                        </div>
                        <div>
                            This pairs nicely with your xFit macrocycles: as strength climbs, you can
                            watch your <strong>Frame Score</strong> creep toward your ceiling.
                        </div>
                    </div>
                <?php endif; ?>
            </article>

            <!-- Effort Curve Card - 3rd row left -->

            <article class="card">
                <div class="card-header">
                    <h3 class="card-title">Expectations & Effort Curve</h3>
                    <p class="card-subtitle">
                        Where ExFIT aims: roughly 75% of your structural potential, earned slowly and kept for life.
                    </p>
                </div>

                <div class="fact-strip" style="margin-top:0.9rem;">
                    <div><strong>Most humans aren’t “average” – they’re underbuilt.</strong></div>
                    <div style="margin-top:0.3rem;">
                        When you see graphs about “average strength” or “average body weight,” remember:
                        the modern average human is <strong>not</strong> a healthy baseline. We’re looking at a
                        population that sits most of the day, eats ultra-processed food, sleeps badly, and rarely
                        trains with intent. From the perspective of what a human frame can do, the
                        <strong>vast majority of people are negative outliers</strong> – way below their built-in potential.
                    </div>
                    <div style="margin-top:0.4rem;">
                        Rough mental model:
                    </div>
                    <ul style="margin-top:0.3rem; padding-left:1.1rem; font-size:0.8rem; color:var(--text-muted);">
                        <li><strong>Bottom 60–70%:</strong> Under-trained, under-muscled, often over-fat. Not “normal,” just common.</li>
                        <li><strong>Middle band:</strong> People who move a bit, lift sometimes, and flirt with progress but don’t stay consistent.</li>
                        <li><strong>Top 10–15%:</strong> Anyone who trains with basic structure, eats enough protein, and sticks with it for years.</li>
                        <li><strong>Top 1–2%:</strong> Genetic lottery winners and/or people who make training their main hobby and identity.</li>
                    </ul>
                    <div style="margin-top:0.4rem;">
                        ExFIT assumes you’re aiming for that <strong>top 10–15%</strong>: clearly trained, strong, and capable,
                        without needing to live like a full-time athlete. Hitting roughly <strong>70–80% of your genetic potential</strong>
                        already puts you miles to the right of the real-world curve.
                    </div>
                    <div style="margin-top:0.4rem;">
                        In other words: if you just show up, lift sensibly, and repeat that for years,
                        <strong>you are the outlier</strong> – in the good direction.
                    </div>
                </div>

                <?php if ($result && $effortBand): ?>
                    <div class="metric" style="margin-bottom: 0.9rem;">
                        <div class="metric-label">Current Band</div>
                        <div class="metric-main">
                            <div class="metric-value">
                                <?= e($effortBand['label']) ?>
                            </div>
                            <div class="metric-sub">
                                <?= e($effortBand['tag']) ?> • Frame score <?= e((string)$result['frame_score']) ?>/100
                            </div>
                        </div>
                    </div>

                    <div class="fact-strip">
                        <div><strong>ExFIT Philosophy:</strong></div>
                        <div>
                            We treat your <strong>frame potential</strong> as the ceiling, not the assignment.
                            ExFIT is intentionally tuned to help you live around
                            <strong>~75% of that potential</strong>:
                        </div>
                        <ul style="margin-top:0.4rem; padding-left:1.1rem; font-size:0.8rem; color:var(--text-muted);">
                            <li>3–4 resistance sessions per week, most weeks of the year</li>
                            <li>Reasonable protein, mostly decent food, no food neurosis</li>
                            <li>Enough sleep to recover, not monk-level perfection</li>
                            <li>Joints, heart, and brain as first-class citizens</li>
                        </ul>
                    </div>

                    <div class="fact-strip" style="margin-top:0.9rem;">
                        <div><strong>Effort vs. payoff:</strong></div>
                        <ul style="margin-top:0.3rem; padding-left:1.1rem; font-size:0.8rem; color:var(--text-muted);">
                            <li><strong>0–50% of potential:</strong> Big visible change for “normal” effort.</li>
                            <li><strong>50–75%:</strong> Slow but satisfying progress — ExFIT’s home base.</li>
                            <li><strong>75–90%:</strong> Serious lifestyle prioritization for smaller returns.</li>
                            <li><strong>90–100%:</strong> Edge-case obsession. Impressive, not required.</li>
                        </ul>
                        <div style="margin-top:0.4rem;">
                            The program will <strong>never shame</strong> you for living at 60–80%.
                            That’s already outstanding for a human who also has a life.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="fact-strip">
                        <div>
                            Once you run the analysis, we’ll place you into an
                            <strong>effort band</strong> and explain what ExFIT expects from you:
                            not perfection, but <strong>simple consistency over years</strong>.
                        </div>
                        <div style="margin-top:0.3rem;">
                            The philosophy here: your goal isn’t to max out the slider.
                            It’s to live comfortably in a zone where strength, health, and aesthetics
                            stay high with <strong>reasonable, boring effort</strong>.
                        </div>
                    </div>
                <?php endif; ?>
            </article>

        <!-- Info Card - 3rd row right -->

            <article class="card">
                <div class="card-header">
                    <h3 class="card-title">Frame Potential - Scientific Overview</h3>
                    <p class="card-subtitle">
                        Biomechanics • Anthropometry • Capacity
                    </p>
                </div>

                <div class="fact-strip" style="margin-top:0.9rem;">
                    <div><strong>Most humans aren’t “average” – they’re underbuilt.</strong></div>
                    <div style="margin-top:0.3rem;">
                        <strong>Frame Potential</strong> refers to the theoretical upper boundary of lean mass, strengthcapacity, and mechanical output that an individual’s skeletal structure can support. In biomechanics, the frame—comprised of height, limb lengths, bone diameters, and joint leverage—sets the physical constraints within which muscle tissue can hypertrophy and produce force. Structural variables such as wrist and ankle circumference correlate with bone mineral density and overall fat-free mass potential, while clavicle width and femur length influence muscle attachment leverage and torque efficiency across major joints.
                    </div>
                    <div style="margin-top:0.4rem;">
                        These characteristics are largely genetically anchored in adulthood, establishing a stable baseline for maximum attainable muscle cross-sectional area and peak strength expression. Frame Potential does <strong>not</strong> predict actual performance; instead, it defines the envelope in which adaptations occur under progressive overload, appropriate nutrition, and sufficient recovery. Understanding this envelope improves long-term forecasting of strength and body composition changes and helps explain why individuals respond differently to identical training stimuli. Within exFIT, Frame Potential is used to calibrate expectations and guide programming so that training demands are aligned with each user’s measurable biomechanical capacity, emphasizing sustainable progress over unrealistic comparison.
                    </div>
                    
                </div>
                    <div class="fact-strip">
                        <div><strong><h2 class="card-title">Why We Measure Wrist, Upper Calf & Upper Arm</h2></strong>
            <p class="card-subtitle">Anthropometry • Frame Capacity • Muscular Expression</p>
        </div></div>
                        <div><p>
                            
    <div class="fact-strip">
        <p>To understand your Frame Potential, exFIT uses three key anthropometric measurements: <strong>wrist circumference</strong>, <strong>upper-calf circumference</strong>, and <strong>upper-arm circumference</strong>. Together, these provide a high-signal snapshot of your skeletal robustness, muscular capacity, and how much of that capacity you’ve already expressed through training.</p>
        <p><strong>Wrist circumference</strong> is one of the strongest predictors of upper-body fat-free mass potential. Because it reflects the thickness of the radius and ulna, it acts as a proxy for bone diameter, tendon load tolerance, and the structural “platform” available for muscle attachment. Thicker wrists generally support more natural muscle mass through the arms, chest, shoulders, and back.</p>
        <p><strong>Upper-calf circumference</strong> is uniquely valuable because calf size is strongly influenced by genetics and less responsive to training in most people. The upper calf reveals inherent muscle belly thickness, tibia/fibula robustness, and Achilles tendon length—factors that shape lower-body load capacity and calf growth potential. In simple terms, calves tell us what nature gave you, not just what you’ve built.</p>
        <p><strong>Upper-arm circumference</strong> (measured relaxed) indicates how much muscularity you are currently expressing relative to your structural potential. When interpreted alongside wrist size, it helps estimate how far along your progression you are and how much realistic hypertrophy remains.</p>
        <p>Combined, these three measurements give exFIT a clear picture of your biomechanical ceiling, enabling personalized expectations, sustainable goal-setting, and training programs aligned with your unique frame.</p>
    </div>
         </div>
            </div>
                
                    <div class="fact-strip">
                        <div>
                            Once you run the analysis, we’ll place you into an
                            <strong>effort band</strong> and explain what ExFIT expects from you:
                            not perfection, but <strong>simple consistency over years</strong>.
                        </div>
                        <div style="margin-top:0.3rem;">
                            The philosophy here: your goal isn’t to max out the slider.
                            It’s to live comfortably in a zone where strength, health, and aesthetics
                            stay high with <strong>reasonable, boring effort</strong>.
                        </div>
                    </div>
                
            </article>
        </section>
    </main>

    <footer>
        exFIT • Gray Mentality — There’s no finish line, just better classifications.
    </footer>
</div>      
                <!-- 🔼 END: existing content -->
            </section>
        </main>
    </div>
</div>

<!-- 🔽 If you had JS at the end of the old file (e.g. bar-fill animations),
     paste those <script> blocks down here so they still run. -->

<!-- Example:
<script>
    // your existing JS from frame potential page
</script>
-->
<script>
// Animate the progress bars after load
document.addEventListener('DOMContentLoaded', function () {
    const metrics = document.querySelectorAll('#metricsGrid .metric');
    metrics.forEach((metric, idx) => {
        const pct = parseFloat(metric.getAttribute('data-pct') || '0');
        const fill = metric.querySelector('.bar-fill');
        if (!fill) return;
        setTimeout(() => {
            fill.style.width = (isNaN(pct) ? 0 : pct) + '%';
        }, 200 + idx * 120);
    });
});
</script>
<?php renderFramePotentialArticleModal(); ?>

</body>
</html>
