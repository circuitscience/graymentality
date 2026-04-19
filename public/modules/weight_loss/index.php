<?php

// include_once __DIR__ . '/../../../api/weight_loss_api/pages/weight_trajectory_lab.php';

/**
 * Weight Trajectory Lab (exFIT)
 * ------------------------------------------------------------------------
 * FILE:  /public/modules/weight_loss/index.php
 *
 * PURPOSE
 *  - Compare TWO long-term weight scenarios side-by-side (Scenario A vs B).
 *  - Each scenario is defined by:
 *      1) Daily calorie drift (surplus or deficit, kcal/day)
 *      2) Holiday / “big day” spikes (days/year * extra kcal/day)
 *  - Outputs projected total change + year-by-year breakdown.
 *
 * CORE IDEA (WHY THIS EXISTS)
 *  - Real life includes birthdays, long weekends, weddings, restaurants, etc.
 *  - This lab models those “spikes” explicitly instead of pretending they
 *    don’t happen—then shows the long-term slope caused by the pattern.
 *
 * UNITS + ASSUMPTIONS
 *  - User chooses either:
 *      • Metric  → weight in kg, conversion ≈ 7700 kcal per kg
 *      • Imperial → weight in lb, conversion ≈ 3500 kcal per lb
 *  - Holiday spikes are averaged across the timeline:
 *      holiday_total_kcal ≈ holiday_days_per_year * extra_kcal_per_day * years
 *  - Timeline uses ~365.25 days/year to account for leap years.
 *
 * OUTPUTS
 *  - For each scenario:
 *      • base_total_kcal     = daily_delta * total_days
 *      • holiday_total_kcal  = holiday_days * holiday_extra * years
 *      • total_kcal          = base_total_kcal + holiday_total_kcal
 *      • total_change_units  = total_kcal / kcal_per_unit
 *      • final_weight        = start_weight + total_change_units
 *      • yearly table        = per-year breakdown of the same math
 *
 * PRESETS (UX FEATURE)
 *  - Buttons like “Light social”, “Party-heavy”, “Dialed-in cut” autofill:
 *      • daily_delta
 *      • holiday_days/year
 *      • holiday_extra kcal/day
 *  - These are convenience templates, not “recommendations.”
 *
 * BMR LAB ACCESS (UX FEATURE)
 *  - Provides a link to the BMR module so the user can estimate maintenance.
 *  - Intended workflow:
 *      1) Use BMR Lab to estimate maintenance calories.
 *      2) Decide an intake / drift for Scenario A & B.
 *      3) Compare long-term outcomes here.
 *
 * NOTES / LIMITATIONS
 *  - This is NOT a medical tool. It’s an education / decision-support lab.
 *  - It does NOT model adaptive thermogenesis, changing expenditure,
 *    lean mass changes, medication effects, or training volume changes.
 *  - The value is in the *direction* and *magnitude* of trends over time.
 *
 * PATHS
 *  - “Back to Modules” button returns to: /modules/index.php
 *  - BMR link assumes: /modules/bmr/index.php (adjust if needed)
 *
 * SECURITY / SAFETY
 *  - No SQL writes in this file currently.
 *  - All output uses escaping helper e() for safe HTML rendering.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// Simple HTML escape
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Compute one weight trajectory scenario (A or B)
 */
function computeScenario(
    string $label,
    string $unitSystem,
    float $currentWeight,
    float $years,
    ?string $dailyDeltaRaw,
    ?string $holidayDaysRaw,
    ?string $holidayExtraRaw
): ?array {
    // If everything is empty, treat as "scenario not used"
    if (
        ($dailyDeltaRaw === '' || $dailyDeltaRaw === null) &&
        ($holidayDaysRaw === '' || $holidayDaysRaw === null) &&
        ($holidayExtraRaw === '' || $holidayExtraRaw === null)
    ) {
        return null;
    }

    $dailyDelta   = $dailyDeltaRaw   !== null && $dailyDeltaRaw   !== '' ? (float)$dailyDeltaRaw   : 0.0;
    $holidayDays  = $holidayDaysRaw  !== null && $holidayDaysRaw  !== '' ? (float)$holidayDaysRaw  : 0.0;
    $holidayExtra = $holidayExtraRaw !== null && $holidayExtraRaw !== '' ? (float)$holidayExtraRaw : 0.0;

    $days      = (int)round($years * 365.25);
    $perUnitKc = ($unitSystem === 'imperial') ? 3500.0 : 7700.0; // kcal per lb / kg

    // Base drift over the whole period
    $baseTotalKcal    = $dailyDelta * $days;
    // Holiday spikes over the whole period (approx, averaged per year)
    $holidayTotalKcal = $holidayDays * $holidayExtra * $years;
    $totalKcal        = $baseTotalKcal + $holidayTotalKcal;

    $totalChangeUnits = $totalKcal / $perUnitKc;
    $finalWeight      = $currentWeight + $totalChangeUnits;

    // Year-by-year breakdown
    $yearly            = [];
    $runningDeltaUnits = 0.0;
    $ceiledYears       = (int)ceil($years);

    for ($i = 1; $i <= $ceiledYears; $i++) {
        $yearFrac = ($i < $ceiledYears)
            ? 1.0
            : ($years - floor($years) ?: 1.0); // last partial year

        $yearDays        = $yearFrac * 365.25;
        $yearBaseKcal    = $dailyDelta * $yearDays;
        $yearHolidayKcal = $holidayDays * $holidayExtra * $yearFrac;
        $yearTotalKcal   = $yearBaseKcal + $yearHolidayKcal;
        $yearDeltaUnits  = $yearTotalKcal / $perUnitKc;
        $runningDeltaUnits += $yearDeltaUnits;
        $weightAfter     = $currentWeight + $runningDeltaUnits;

        $yearly[] = [
            'year'           => $i,
            'year_fraction'  => $yearFrac,
            'base_kcal'      => $yearBaseKcal,
            'holiday_kcal'   => $yearHolidayKcal,
            'total_kcal'     => $yearTotalKcal,
            'delta_units'    => $yearDeltaUnits,
            'weight_after'   => $weightAfter,
        ];
    }

    return [
        'label'             => $label,
        'unit_system'       => $unitSystem,
        'current_weight'    => $currentWeight,
        'years'             => $years,
        'days'              => $days,
        'kcal_per_unit'     => $perUnitKc,
        'daily_delta'       => $dailyDelta,
        'holiday_days'      => $holidayDays,
        'holiday_extra'     => $holidayExtra,
        'base_total_kcal'   => $baseTotalKcal,
        'holiday_total_kcal'=> $holidayTotalKcal,
        'total_kcal'        => $totalKcal,
        'total_change'      => $totalChangeUnits,
        'final_weight'      => $finalWeight,
        'yearly'            => $yearly,
    ];
}

$resultScenarios = [
    'A' => null,
    'B' => null,
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unitSystem = $_POST['unit_system'] ?? 'metric'; // metric | imperial
    $currentRaw = $_POST['current_weight'] ?? '';
    $yearsRaw   = $_POST['years'] ?? '';

    // Shared validation
    if ($currentRaw === '' || !is_numeric($currentRaw) || (float)$currentRaw <= 0) {
        $errors[] = "Please enter a valid current weight greater than zero.";
    }
    if ($yearsRaw === '' || !is_numeric($yearsRaw) || (float)$yearsRaw <= 0) {
        $errors[] = "Please enter a time frame in years (e.g. 1–5).";
    }

    if (!$errors) {
        $unitSystem    = $unitSystem === 'imperial' ? 'imperial' : 'metric';
        $currentWeight = (float)$currentRaw;
        $years         = max(0.25, min((float)$yearsRaw, 5.0)); // clamp to 0.25–5

        // Scenario A inputs
        $sA_daily = $_POST['scenario_a_daily_delta']   ?? '';
        $sA_days  = $_POST['scenario_a_holiday_days']  ?? '';
        $sA_extra = $_POST['scenario_a_holiday_extra'] ?? '';
        $sA_label = trim($_POST['scenario_a_label']    ?? 'Scenario A');

        // Scenario B inputs
        $sB_daily = $_POST['scenario_b_daily_delta']   ?? '';
        $sB_days  = $_POST['scenario_b_holiday_days']  ?? '';
        $sB_extra = $_POST['scenario_b_holiday_extra'] ?? '';
        $sB_label = trim($_POST['scenario_b_label']    ?? 'Scenario B');

        // At least one scenario should be “filled”
        $scenarioAFilled = !($sA_daily === '' && $sA_days === '' && $sA_extra === '');
        $scenarioBFilled = !($sB_daily === '' && $sB_days === '' && $sB_extra === '');

        if (!$scenarioAFilled && !$scenarioBFilled) {
            $errors[] = "Please fill in at least Scenario A or Scenario B.";
        }

        if (!$errors) {
            if ($scenarioAFilled) {
                $resultScenarios['A'] = computeScenario(
                    $sA_label ?: 'Scenario A',
                    $unitSystem,
                    $currentWeight,
                    $years,
                    $sA_daily,
                    $sA_days,
                    $sA_extra
                );
            }

            if ($scenarioBFilled) {
                $resultScenarios['B'] = computeScenario(
                    $sB_label ?: 'Scenario B',
                    $unitSystem,
                    $currentWeight,
                    $years,
                    $sB_daily,
                    $sB_days,
                    $sB_extra
                );
            }

            if (!$resultScenarios['A'] && !$resultScenarios['B']) {
                $errors[] = "Unable to compute scenarios. Please check your inputs.";
            }
        }
    }
}

$hasResults = ($resultScenarios['A'] !== null || $resultScenarios['B'] !== null);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Weight Trajectory Lab | exFIT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            /* Match the hub background for weight_loss */
            --module-bg-image: url('../assets/couple.png');
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

        .module-card h1 {
            font-size: 1.3rem;
            margin: 0 0 0.4rem;
        }

        .module-card p {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* ===== exFIT lab layout styles (mirroring Frame Potential) ===== */

        .page-wrap {
            display: flex;
            flex-direction: column;
            gap: 1.3rem;
        }

        header.lab-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: radial-gradient(circle at top left, #ff7a1a, #ff3b6a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 0.03em;
        }

        .brand-text h1 {
            margin: 0;
            font-size: 1.2rem;
        }

        .brand-text p {
            margin: 0.15rem 0 0;
            font-size: 0.78rem;
            opacity: 0.8;
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

        @media (max-width: 800px) {
            .hero {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .hero-card {
            border-radius: 0.9rem;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.12);
            background: radial-gradient(circle at top left, rgba(255,122,26,0.12), transparent),
                        radial-gradient(circle at bottom right, rgba(190,70,255,0.1), transparent),
                        rgba(10,10,10,0.9);
        }

        .hero-title {
            margin: 0 0 0.4rem;
            font-size: 1.1rem;
        }

        .hero-highlight {
            color: #ffb347;
        }

        .hero-sub {
            margin: 0;
            font-size: 0.83rem;
            opacity: 0.9;
        }

        .tagline {
            margin-top: 0.6rem;
            font-size: 0.8rem;
            opacity: 0.85;
        }

        .score-card {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            align-items: stretch;
        }

        .score-gauge {
            width: 100%;
        }

        .score-ring {
            border-radius: 0.9rem;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 0.7rem 0.8rem;
            background: rgba(0,0,0,0.6);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .score-label {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .scenario-rows {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .scenario-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.78rem;
        }

        .scenario-name {
            opacity: 0.85;
        }

        .scenario-change {
            font-weight: 600;
        }

        .score-caption {
            font-size: 0.72rem;
            opacity: 0.75;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.6fr);
            gap: 1rem;
        }

        @media (max-width: 900px) {
            .layout-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .card {
            border-radius: 0.9rem;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(12,12,12,0.95);
        }

        .card-header {
            margin-bottom: 0.6rem;
        }

        .card-title {
            margin: 0;
            font-size: 0.95rem;
        }

        .card-subtitle {
            margin: 0.25rem 0 0;
            font-size: 0.78rem;
            opacity: 0.8;
        }

        form.weight-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem 0.8rem;
            margin-top: 0.6rem;
        }

        form.weight-form .full-span {
            grid-column: 1 / -1;
        }

        label {
            font-size: 0.78rem;
            display: block;
            margin-bottom: 0.15rem;
        }

        input[type="number"],
        input[type="text"] {
            width: 100%;
            padding: 0.4rem 0.5rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(148,163,184,0.5);
            background: rgba(15,15,15,0.9);
            color: #f5f5f5;
            font-size: 0.82rem;
        }

        .field-hint {
            margin-top: 0.1rem;
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .fieldset-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .radio-pill input {
            display: none;
        }

        .radio-pill label {
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.5);
            font-size: 0.75rem;
            cursor: pointer;
            background: rgba(15,15,15,0.8);
        }

        .radio-pill input:checked + label {
            border-color: #ff7a1a;
            background: radial-gradient(circle at top left, rgba(255,122,26,0.35), rgba(15,15,15,0.9));
        }

        .preset-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.25rem;
        }

        .preset-label {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-right: 0.3rem;
        }

        .preset-btn {
            border: 1px solid rgba(148,163,184,0.6);
            border-radius: 999px;
            background: rgba(15,15,15,0.9);
            padding: 0.15rem 0.55rem;
            font-size: 0.7rem;
            cursor: pointer;
        }

        .preset-btn:hover {
            border-color: #ff7a1a;
        }

        .btn-primary {
            border: none;
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            background: linear-gradient(135deg, #ff7a1a, #ff3b6a);
            color: #050505;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            box-shadow: 0 8px 24px rgba(255,122,26,0.5);
        }

        .btn-primary span {
            font-size: 0.9rem;
        }

        .btn-link {
            border: none;
            background: transparent;
        }

        .link-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.6);
            font-size: 0.72rem;
            text-decoration: none;
            color: #e5e7eb;
            background: rgba(15,23,42,0.85);
        }

        .link-pill:hover {
            border-color: #ff7a1a;
        }

        .error-list {
            border-radius: 0.6rem;
            border: 1px solid rgba(248,113,113,0.6);
            background: rgba(127,29,29,0.8);
            padding: 0.6rem 0.7rem;
            font-size: 0.75rem;
            margin-bottom: 0.6rem;
        }

        .error-list ul {
            margin: 0.3rem 0 0;
            padding-left: 1.1rem;
        }

        .fact-strip {
            border-radius: 0.7rem;
            padding: 0.7rem 0.75rem;
            background: rgba(15,23,42,0.9);
            border: 1px solid rgba(148,163,184,0.6);
            font-size: 0.78rem;
            margin-top: 0.6rem;
        }

        .summary-grid-dual {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.7rem 0.8rem;
            margin-top: 0.6rem;
            font-size: 0.8rem;
        }

        .summary-block {
            border-radius: 0.6rem;
            padding: 0.6rem 0.7rem;
            background: rgba(15,23,42,0.85);
            border: 1px solid rgba(51,65,85,0.9);
        }

        .summary-block h4 {
            margin: 0 0 0.3rem;
            font-size: 0.8rem;
        }

        .summary-item {
            font-size: 0.78rem;
        }

        .summary-item strong {
            display: inline-block;
            min-width: 96px;
        }

        table.trajectory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.6rem;
            font-size: 0.78rem;
        }

        table.trajectory-table th,
        table.trajectory-table td {
            padding: 0.35rem 0.4rem;
            border-bottom: 1px solid rgba(51,65,85,0.8);
            text-align: left;
        }

        table.trajectory-table th {
            font-weight: 600;
            font-size: 0.75rem;
        }

        footer.lab-footer {
            margin-top: 1rem;
            font-size: 0.72rem;
            opacity: 0.7;
            text-align: center;
        }

        @media (max-width: 700px) {
            .summary-grid-dual {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
</head>
<body>

<div class="module-shell">
    <div class="module-bg"></div>

    <div class="module-content">
        <header class="module-header">
            <div class="module-header-left">
                <div class="module-title">Weight Trajectory Lab</div>
                <div class="module-subtitle">
                    Compare two long-term patterns — including real-world holiday spikes.
                </div>
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
                            <div class="brand-logo">ex</div>
                            <div class="brand-text">
                                <h1>Weight Trajectory Lab</h1>
                                <p>Two scenarios. Same you. Different long-term slope.</p>
                            </div>
                        </div>
                        <div class="score-pill">
                            Gray Mentality • <strong>Tiny habits, huge arcs</strong>
                        </div>
                    </header>

                    <section class="hero">
                        <div class="hero-card">
                            <h2 class="hero-title">
                                How much damage do <span class="hero-highlight">“big days”</span> really do?
                            </h2>
                            <p class="hero-sub">
                                It’s not the birthday or the long weekend that changes you. It’s the pattern
                                underneath them — and how often you let “this doesn’t count” show up.
                            </p>
                            <p class="tagline">
                                This lab lets you compare two versions of your life: maybe your current pattern
                                vs. a slightly cleaner one. Both can include holiday spikes — parties, trips,
                                restaurant nights — baked into the math instead of ignored.
                            </p>
                        </div>

                        <div class="hero-card score-card">
                            <div class="score-gauge">
                                <div class="score-ring">
                                    <div class="score-label">Projected change over your chosen period</div>
                                    <div class="scenario-rows">
                                        <?php
                                        $unitLabelGlobal = (($_POST['unit_system'] ?? 'metric') === 'imperial') ? 'lb' : 'kg';
                                        ?>
                                        <div class="scenario-row">
                                            <span class="scenario-name">
                                                <?= e($_POST['scenario_a_label'] ?? 'Scenario A'); ?>
                                            </span>
                                            <span class="scenario-change">
                                                <?php if ($resultScenarios['A']): ?>
                                                    <?php
                                                        $deltaA = $resultScenarios['A']['total_change'];
                                                        $signA  = $deltaA > 0 ? '+' : '';
                                                    ?>
                                                    <?= $signA . number_format($deltaA, 1) . ' ' . $unitLabelGlobal ?>
                                                <?php else: ?>
                                                    –
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="scenario-row">
                                            <span class="scenario-name">
                                                <?= e($_POST['scenario_b_label'] ?? 'Scenario B'); ?>
                                            </span>
                                            <span class="scenario-change">
                                                <?php if ($resultScenarios['B']): ?>
                                                    <?php
                                                        $deltaB = $resultScenarios['B']['total_change'];
                                                        $signB  = $deltaB > 0 ? '+' : '';
                                                    ?>
                                                    <?= $signB . number_format($deltaB, 1) . ' ' . $unitLabelGlobal ?>
                                                <?php else: ?>
                                                    –
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="score-caption">
                                        The difference between these two rows is the long-term price (or benefit)
                                        of the choices you’re comparing.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="layout-grid">
                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Shared inputs + scenarios</h3>
                                <p class="card-subtitle">
                                    Same body, same time frame — but different daily drift and holiday behavior.
                                </p>
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

                            <form method="post" class="weight-form">
                                <!-- Shared -->
                                <div class="full-span">
                                    <label>Units</label>
                                    <div class="fieldset-inline">
                                        <div class="radio-pill">
                                            <input type="radio" id="unit_metric" name="unit_system" value="metric"
                                                <?= empty($_POST['unit_system']) || ($_POST['unit_system'] ?? '') === 'metric' ? 'checked' : '' ?>>
                                            <label for="unit_metric">Metric (kg)</label>
                                        </div>
                                        <div class="radio-pill">
                                            <input type="radio" id="unit_imperial" name="unit_system" value="imperial"
                                                <?= ($_POST['unit_system'] ?? '') === 'imperial' ? 'checked' : '' ?>>
                                            <label for="unit_imperial">Imperial (lb)</label>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label for="current_weight">Current weight</label>
                                    <input type="number" step="0.1" min="1"
                                           id="current_weight" name="current_weight"
                                           value="<?= isset($_POST['current_weight']) ? e((string)$_POST['current_weight']) : '' ?>"
                                           placeholder="e.g. 80">
                                    <div class="field-hint">
                                        In <?= (($_POST['unit_system'] ?? 'metric') === 'imperial') ? 'lb' : 'kg'; ?>.
                                    </div>
                                </div>

                                <div>
                                    <label for="years">Time frame (years)</label>
                                    <input type="number" step="0.25" min="0.25" max="5"
                                           id="years" name="years"
                                           value="<?= isset($_POST['years']) ? e((string)$_POST['years']) : '3' ?>"
                                           placeholder="e.g. 3">
                                    <div class="field-hint">
                                        Between 0.25 and 5 years.
                                    </div>
                                </div>

                                <div class="full-span">
                                    <a href="/modules/bmr/index.php" class="link-pill" target="_blank" rel="noopener">
                                        <span>Need help estimating maintenance? Open the BMR Lab ↗</span>
                                    </a>
                                </div>

                                <!-- Scenario A -->
                                <div class="full-span">
                                    <label>Scenario A label</label>
                                    <input type="text" name="scenario_a_label"
                                           value="<?= e($_POST['scenario_a_label'] ?? 'Current pattern') ?>">
                                    <div class="field-hint">
                                        Example: “Current pattern” or “With nightly snack”.
                                    </div>
                                </div>

                                <div>
                                    <label for="scenario_a_daily_delta">Scenario A: daily kcal surplus / deficit</label>
                                    <input type="number" step="1" id="scenario_a_daily_delta" name="scenario_a_daily_delta"
                                           value="<?= isset($_POST['scenario_a_daily_delta']) ? e((string)$_POST['scenario_a_daily_delta']) : '' ?>"
                                           placeholder="e.g. +150 or -250">
                                    <div class="field-hint">
                                        Negative for weight loss, positive for gain.
                                    </div>
                                </div>

                                <div>
                                    <label for="scenario_a_holiday_days">Scenario A: “big” days per year</label>
                                    <input type="number" step="1" min="0" id="scenario_a_holiday_days" name="scenario_a_holiday_days"
                                           value="<?= isset($_POST['scenario_a_holiday_days']) ? e((string)$_POST['scenario_a_holiday_days']) : '20' ?>"
                                           placeholder="e.g. 20">
                                    <div class="field-hint">
                                        Birthdays, long weekends, weddings, trips, restaurant nights…
                                    </div>
                                </div>

                                <div class="full-span">
                                    <label for="scenario_a_holiday_extra">Scenario A: extra kcal on those days</label>
                                    <input type="number" step="10" id="scenario_a_holiday_extra" name="scenario_a_holiday_extra"
                                           value="<?= isset($_POST['scenario_a_holiday_extra']) ? e((string)$_POST['scenario_a_holiday_extra']) : '800' ?>"
                                           placeholder="e.g. 800">
                                    <div class="field-hint">
                                        Approx extra intake vs your normal day on those spikes.
                                    </div>
                                    <div class="preset-row">
                                        <span class="preset-label">Quick presets:</span>
                                        <button type="button" class="preset-btn"
                                                data-target="a" data-preset="maintain">
                                            Mostly maintenance
                                        </button>
                                        <button type="button" class="preset-btn"
                                                data-target="a" data-preset="light_social">
                                            Light social
                                        </button>
                                        <button type="button" class="preset-btn"
                                                data-target="a" data-preset="party_heavy">
                                            Party-heavy
                                        </button>
                                    </div>
                                </div>

                                <!-- Scenario B -->
                                <div class="full-span">
                                    <label>Scenario B label</label>
                                    <input type="text" name="scenario_b_label"
                                           value="<?= e($_POST['scenario_b_label'] ?? 'Cleaner pattern') ?>">
                                    <div class="field-hint">
                                        Example: “Cleaner pattern” or “Cut 1 drink + 1 snack”.
                                    </div>
                                </div>

                                <div>
                                    <label for="scenario_b_daily_delta">Scenario B: daily kcal surplus / deficit</label>
                                    <input type="number" step="1" id="scenario_b_daily_delta" name="scenario_b_daily_delta"
                                           value="<?= isset($_POST['scenario_b_daily_delta']) ? e((string)$_POST['scenario_b_daily_delta']) : '' ?>"
                                           placeholder="e.g. 0 or -200">
                                    <div class="field-hint">
                                        This might be your “fixed” version of you.
                                    </div>
                                </div>

                                <div>
                                    <label for="scenario_b_holiday_days">Scenario B: “big” days per year</label>
                                    <input type="number" step="1" min="0" id="scenario_b_holiday_days" name="scenario_b_holiday_days"
                                           value="<?= isset($_POST['scenario_b_holiday_days']) ? e((string)$_POST['scenario_b_holiday_days']) : '12' ?>"
                                           placeholder="e.g. 12">
                                    <div class="field-hint">
                                        Maybe fewer binges, or better guardrails.
                                    </div>
                                </div>

                                <div class="full-span">
                                    <label for="scenario_b_holiday_extra">Scenario B: extra kcal on those days</label>
                                    <input type="number" step="10" id="scenario_b_holiday_extra" name="scenario_b_holiday_extra"
                                           value="<?= isset($_POST['scenario_b_holiday_extra']) ? e((string)$_POST['scenario_b_holiday_extra']) : '600' ?>"
                                           placeholder="e.g. 600">
                                    <div class="field-hint">
                                        Maybe you still enjoy them, just a bit more controlled.
                                    </div>
                                    <div class="preset-row">
                                        <span class="preset-label">Quick presets:</span>
                                        <button type="button" class="preset-btn"
                                                data-target="b" data-preset="dialed_cut">
                                            Dialed-in cut
                                        </button>
                                        <button type="button" class="preset-btn"
                                                data-target="b" data-preset="light_social">
                                            Light social
                                        </button>
                                        <button type="button" class="preset-btn"
                                                data-target="b" data-preset="party_heavy">
                                            Party-heavy
                                        </button>
                                    </div>
                                </div>

                                <div class="full-span">
                                    <button type="submit" class="btn-primary">
                                        Compare scenarios
                                        <span>➜</span>
                                    </button>
                                </div>
                            </form>

                            <div class="fact-strip">
                                <div><strong>Assumptions:</strong></div>
                                <ul style="margin:0.3rem 0 0; padding-left:1.1rem; font-size:0.75rem;">
                                    <li>Metric: ~7,700 kcal per kg of fat mass.</li>
                                    <li>Imperial: ~3,500 kcal per lb of fat mass.</li>
                                    <li>Big days are averaged over the year (their impact is spread out in the trend).</li>
                                </ul>
                                <div style="margin-top:0.4rem;">
                                    Reality will wiggle more than this graph — but the direction is accurate enough
                                    to be a <strong>behavior compass</strong>.
                                </div>
                            </div>
                        </article>

                        <article class="card">
                            <div class="card-header">
                                <h3 class="card-title">Projection & ExFIT view</h3>
                                <p class="card-subtitle">
                                    Two versions of your life, both including parties and holidays — just with different rules.
                                </p>
                            </div>

                            <?php if ($hasResults): ?>
                                <?php $uLabel = $unitLabelGlobal; ?>

                                <div class="summary-grid-dual">
                                    <?php if ($resultScenarios['A']): ?>
                                        <?php $A = $resultScenarios['A']; ?>
                                        <div class="summary-block">
                                            <h4><?= e($A['label']) ?></h4>
                                            <div class="summary-item">
                                                <strong>Total change:</strong>
                                                <?php
                                                    $signA = $A['total_change'] > 0 ? '+' : '';
                                                    echo $signA . number_format($A['total_change'], 1) . ' ' . $uLabel;
                                                ?>
                                            </div>
                                            <div class="summary-item">
                                                <strong>Final weight:</strong>
                                                <?= number_format($A['final_weight'], 1) . ' ' . $uLabel ?>
                                            </div>
                                            <div class="summary-item">
                                                <strong>Base drift:</strong>
                                                <?= number_format($A['daily_delta'], 0) ?> kcal/day
                                            </div>
                                            <div class="summary-item">
                                                <strong>Holidays:</strong>
                                                <?= number_format($A['holiday_days'], 0) ?> days/year @
                                                <?= number_format($A['holiday_extra'], 0) ?> kcal
                                            </div>
                                            <div class="summary-item">
                                                <strong>Holiday impact:</strong>
                                                <?= number_format($A['holiday_total_kcal'], 0) ?> kcal total
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($resultScenarios['B']): ?>
                                        <?php $B = $resultScenarios['B']; ?>
                                        <div class="summary-block">
                                            <h4><?= e($B['label']) ?></h4>
                                            <div class="summary-item">
                                                <strong>Total change:</strong>
                                                <?php
                                                    $signB = $B['total_change'] > 0 ? '+' : '';
                                                    echo $signB . number_format($B['total_change'], 1) . ' ' . $uLabel;
                                                ?>
                                            </div>
                                            <div class="summary-item">
                                                <strong>Final weight:</strong>
                                                <?= number_format($B['final_weight'], 1) . ' ' . $uLabel ?>
                                            </div>
                                            <div class="summary-item">
                                                <strong>Base drift:</strong>
                                                <?= number_format($B['daily_delta'], 0) ?> kcal/day
                                            </div>
                                            <div class="summary-item">
                                                <strong>Holidays:</strong>
                                                <?= number_format($B['holiday_days'], 0) ?> days/year @
                                                <?= number_format($B['holiday_extra'], 0) ?> kcal
                                            </div>
                                            <div class="summary-item">
                                                <strong>Holiday impact:</strong>
                                                <?= number_format($B['holiday_total_kcal'], 0) ?> kcal total
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($resultScenarios['A']): ?>
                                    <?php $A = $resultScenarios['A']; ?>
                                    <h4 style="margin-top:0.9rem; font-size:0.8rem;">
                                        Scenario <?= e($A['label']) ?> — year-by-year
                                    </h4>
                                    <table class="trajectory-table">
                                        <thead>
                                            <tr>
                                                <th>Year</th>
                                                <th>Base kcal</th>
                                                <th>Holiday kcal</th>
                                                <th>Δ weight</th>
                                                <th>Weight after</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($A['yearly'] as $row): ?>
                                                <?php
                                                    $yDelta = $row['delta_units'];
                                                    $signY  = $yDelta > 0 ? '+' : '';
                                                ?>
                                                <tr>
                                                    <td><?= (int)$row['year'] ?></td>
                                                    <td><?= number_format($row['base_kcal']) ?></td>
                                                    <td><?= number_format($row['holiday_kcal']) ?></td>
                                                    <td><?= $signY . number_format($yDelta, 1) . ' ' . $uLabel ?></td>
                                                    <td><?= number_format($row['weight_after'], 1) . ' ' . $uLabel ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>

                                <?php if ($resultScenarios['B']): ?>
                                    <?php $B = $resultScenarios['B']; ?>
                                    <h4 style="margin-top:0.9rem; font-size:0.8rem;">
                                        Scenario <?= e($B['label']) ?> — year-by-year
                                    </h4>
                                    <table class="trajectory-table">
                                        <thead>
                                            <tr>
                                                <th>Year</th>
                                                <th>Base kcal</th>
                                                <th>Holiday kcal</th>
                                                <th>Δ weight</th>
                                                <th>Weight after</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($B['yearly'] as $row): ?>
                                                <?php
                                                    $yDelta = $row['delta_units'];
                                                    $signY  = $yDelta > 0 ? '+' : '';
                                                ?>
                                                <tr>
                                                    <td><?= (int)$row['year'] ?></td>
                                                    <td><?= number_format($row['base_kcal']) ?></td>
                                                    <td><?= number_format($row['holiday_kcal']) ?></td>
                                                    <td><?= $signY . number_format($yDelta, 1) . ' ' . $uLabel ?></td>
                                                    <td><?= number_format($row['weight_after'], 1) . ' ' . $uLabel ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>

                                <div class="fact-strip" style="margin-top:0.7rem;">
                                    <div><strong>ExFIT interpretation:</strong></div>
                                    <div style="margin-top:0.3rem;">
                                        Holiday spikes themselves aren’t the villain. The real story is:
                                        <strong>how many</strong> there are, <strong>how big</strong> they are,
                                        and whether your normal days lean slightly positive or slightly negative.
                                    </div>
                                    <div style="margin-top:0.4rem;">
                                        If Scenario B shows a calmer slope with only modest changes to your
                                        base drift and “big” days, that’s the entire point of ExFIT:
                                        <strong>real life still happens</strong>, but the long-term trend is
                                        pushed in your favour and held there by muscle and structure.
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="fact-strip">
                                    <div>
                                        Fill in at least one scenario on the left (daily drift + holiday pattern)
                                        and we’ll show you what that version of your life wants to do to your
                                        weight over the next few years.
                                    </div>
                                    <div style="margin-top:0.3rem;">
                                        Then give it a rival: a slightly more disciplined version of you
                                        with fewer or smaller spikes. The gap between those two curves is the
                                        <strong>power of unsexy consistency</strong>.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </article>
                    </section>

                    <footer class="lab-footer">
                        exFIT • Gray Mentality — The scale is a trend, not a verdict.
                    </footer>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
// Quick presets for scenarios A & B
document.addEventListener('DOMContentLoaded', function () {
    function applyPreset(target, preset) {
        const dailyField   = document.getElementById('scenario_' + target + '_daily_delta');
        const daysField    = document.getElementById('scenario_' + target + '_holiday_days');
        const extraField   = document.getElementById('scenario_' + target + '_holiday_extra');
        if (!dailyField || !daysField || !extraField) return;

        let daily = 0, days = 0, extra = 0;

        switch (preset) {
            case 'maintain':
                daily = 0;    // roughly maintenance
                days  = 20;   // a couple of big things per month
                extra = 800;  // heavy but not wild
                break;
            case 'light_social':
                daily = 100;  // small surplus
                days  = 30;   // social a bit more often
                extra = 600;  // decent but not insane
                break;
            case 'party_heavy':
                daily = 250;  // clear surplus
                days  = 40;   // a lot of big days
                extra = 1000; // big blowouts
                break;
            case 'dialed_cut':
                daily = -250; // modest deficit
                days  = 15;   // still some big days
                extra = 500;  // more restrained
                break;
            default:
                return;
        }

        dailyField.value = daily;
        daysField.value  = days;
        extraField.value = extra;
    }

    document.querySelectorAll('.preset-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = btn.getAttribute('data-target');  // 'a' or 'b'
            const preset = btn.getAttribute('data-preset');  // e.g. 'maintain'
            if (!target || !preset) return;
            applyPreset(target.toLowerCase(), preset);
        });
    });
});
</script>

</body>
</html>
