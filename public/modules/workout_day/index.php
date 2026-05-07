<?php
declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * MODULE: Workout Day Protocol
 * FILE: /public/modules/workout_day/index.php
 * -------------------------------------------------------------------------
 * PURPOSE
 *  - Guides user through the full xFit workout day lifecycle
 *  - Enforces phase transitions:
 *      Pre → Train → Hard Cut → Recovery
 *
 * NOTES
 *  - This module does NOT generate workouts
 *  - It wraps around the existing workout engine
 * -------------------------------------------------------------------------
 */

require_once __DIR__ . '/../../includes/session_guard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>xFit • Workout Day</title>
<link rel="stylesheet" href="/modules/workout_day/workout_day.css">
<?php
$workoutCssPath = __DIR__ . '/workout_day.css';
if (is_file($workoutCssPath)) {
    echo '<style>' . PHP_EOL;
    readfile($workoutCssPath);
    echo PHP_EOL . '</style>' . PHP_EOL;
}
?>
<script defer src="/modules/workout_day/workout_day.js"></script>
</head>

<body class="xfit-module">

<main class="workout-shell">
    <nav class="module-nav" aria-label="Module navigation">
        <a href="/modules/index.php">Dashboard</a>
        <span>xFit</span>
    </nav>

    <header class="module-header">
        <p class="eyebrow">Training Execution</p>
        <h1>Workout Day Protocol</h1>
        <p>Controlled stress. Honest execution. Deliberate recovery.</p>
    </header>

    <section id="phase-pre" class="phase active">
        <span class="phase-label">Phase 01</span>
        <h2>Pre-Workout Check</h2>
        <ul>
            <li><label><input type="checkbox"> Slept at least 6 hours</label></li>
            <li><label><input type="checkbox"> Hydrated</label></li>
            <li><label><input type="checkbox"> Fuel available</label></li>
            <li><label><input type="checkbox"> No injury escalation</label></li>
        </ul>
        <button data-next="phase-train">Enter Training Mode</button>
    </section>

    <section id="phase-train" class="phase">
        <span class="phase-label">Phase 02</span>
        <h2>Training Mode</h2>
        <p>Music active. Focused execution.</p>
        <button data-next="phase-cut">Session Complete</button>
    </section>

    <section id="phase-cut" class="phase">
        <span class="phase-label">Phase 03</span>
        <h2>Hard Cut</h2>
        <p>Music stops now.</p>
        <button data-next="phase-recovery">Enter Recovery</button>
    </section>

    <section id="phase-recovery" class="phase">
        <span class="phase-label">Phase 04</span>
        <h2>Recovery Mode</h2>
        <p>Down-regulate. Restore.</p>
        <button data-next="done">Finish</button>
    </section>
</main>

</body>
</html>
