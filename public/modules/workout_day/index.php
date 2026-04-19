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
<title>xFit • Workout Day</title>
<link rel="stylesheet" href="workout_day.css">
<script defer src="workout_day.js"></script>
</head>

<body class="xfit-module">

<header class="module-header">
    <h1>Workout Day Protocol</h1>
    <p>Controlled stress. Honest execution. Deliberate recovery.</p>
</header>

<section id="phase-pre" class="phase active">
    <h2>Pre-Workout Check</h2>
    <ul>
        <li><input type="checkbox"> Slept ≥ 6 hours</li>
        <li><input type="checkbox"> Hydrated</li>
        <li><input type="checkbox"> Fuel available</li>
        <li><input type="checkbox"> No injury escalation</li>
    </ul>
    <button data-next="phase-train">Enter Training Mode</button>
</section>

<section id="phase-train" class="phase">
    <h2>Training Mode</h2>
    <p>Music active. Focused execution.</p>
    <button data-next="phase-cut">Session Complete</button>
</section>

<section id="phase-cut" class="phase">
    <h2>Hard Cut</h2>
    <p>Music stops now.</p>
    <button data-next="phase-recovery">Enter Recovery</button>
</section>

<section id="phase-recovery" class="phase">
    <h2>Recovery Mode</h2>
    <p>Down-regulate. Restore.</p>
    <button data-next="done">Finish</button>
</section>

</body>
</html>
