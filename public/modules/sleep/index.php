<?php
declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * exFIT — Sleep Dashboard (Module)
 * -------------------------------------------------------------------------
 * FILE: /public/modules/sleep_dashboard/index.php
 *
 * PURPOSE
 *  - Mobile/desktop friendly sleep dashboard that:
 *      1) Stores daily sleep logs in a dedicated table (sleep_logs)
 *      2) Computes indicators:
 *          • Last night sleep duration
 *          • Sleep efficiency (simple estimate)
 *          • 7-day average sleep
 *          • Sleep debt vs target
 *          • Consistency (midsleep variability)
 *          • Hygiene score (behavior + environment signals)
 *      3) Provides calculators:
 *          • Optimal bedtime suggestions based on 90-minute cycles
 *
 * ASSUMPTIONS
 *  - You have:
 *      • /config/config.php that creates $conn (mysqli)
 *      • A session login setting $_SESSION['user_id']
 *  - You already use an exFIT “module shell” aesthetic elsewhere.
 *
 * SECURITY
 *  - Prepared statements
 *  - Basic CSRF token
 *  - Server-side validation
 * -------------------------------------------------------------------------
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../includes/session_guard.php';

$user_id = (int)($authUser['id'] ?? 0);

// --- CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// --- Helpers
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function timeToMinutes(?string $timeHHMMSS): ?int {
    if (!$timeHHMMSS) return null;
    // Accept HH:MM or HH:MM:SS
    $parts = explode(':', $timeHHMMSS);
    if (count($parts) < 2) return null;
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    return $h * 60 + $m;
}

/**
 * Given bedtime + wake_time (TIME), compute time-in-bed minutes.
 * Handles crossing midnight (bedtime > wake_time means sleep passed midnight).
 */
function computeTimeInBedMinutes(?string $bedtime, ?string $wake_time): ?int {
    $b = timeToMinutes($bedtime);
    $w = timeToMinutes($wake_time);
    if ($b === null || $w === null) return null;

    if ($w >= $b) return $w - $b;
    // crossed midnight
    return (24 * 60 - $b) + $w;
}

function clampInt(int $v, int $min, int $max): int {
    return max($min, min($max, $v));
}

function formatHoursMinutes(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return sprintf('%dh %02dm', $h, $m);
}

function minutesToTimeString(int $mins): string {
    $mins = ($mins % (24 * 60) + (24 * 60)) % (24 * 60);
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return sprintf('%02d:%02d', $h, $m);
}

/**
 * Sleep cycles calculator:
 * - Typical cycle = 90 minutes
 * - Assume ~15 minutes to fall asleep
 * - Returns suggested bedtimes for N cycles given a wake time (in minutes).
 */
function calcBedtimesFromWake(int $wakeMins, int $fallAsleepMins = 15, array $cycles = [6,5,4]): array {
    $out = [];
    foreach ($cycles as $c) {
        $total = $fallAsleepMins + ($c * 90);
        $bed = $wakeMins - $total;
        $out[] = [
            'cycles' => $c,
            'bedtime' => minutesToTimeString($bed),
            'sleep_time' => formatHoursMinutes($c * 90)
        ];
    }
    return $out;
}

/**
 * Hygiene Score (0–100)
 * This is intentionally simple and transparent.
 * Adjust weights later as you build more sophistication.
 */
function computeHygieneScore(array $row, ?int $timeInBed): int {
    $score = 100;

    $minutes_awake = (int)($row['minutes_awake'] ?? 0);
    $naps_minutes  = (int)($row['naps_minutes'] ?? 0);
    $screen_minutes = (int)($row['screen_minutes'] ?? 0);
    $caffeine_after_2pm = (int)($row['caffeine_after_2pm'] ?? 0);
    $alcohol = (int)($row['alcohol'] ?? 0);
    $temp_c = isset($row['room_temp_c']) ? (float)$row['room_temp_c'] : null;

    // Night awakenings penalty (cap)
    $score -= clampInt((int)round($minutes_awake * 0.35), 0, 25);

    // Screens late (proxy: screen_minutes)
    $score -= clampInt((int)round($screen_minutes * 0.08), 0, 20);

    // Caffeine after 2pm
    if ($caffeine_after_2pm === 1) $score -= 12;

    // Alcohol (sleep fragmentation)
    if ($alcohol === 1) $score -= 12;

    // Naps (can be fine; large naps reduce sleep drive)
    $score -= clampInt((int)round($naps_minutes * 0.05), 0, 10);

    // Temperature sweet spot approx: 15.5–19.5°C (60–67°F)
    if ($temp_c !== null) {
        if ($temp_c < 14.0) $score -= 6;
        if ($temp_c > 21.0) $score -= 10;
        if ($temp_c >= 15.5 && $temp_c <= 19.5) $score += 3;
    }

    // If time-in-bed is extremely low, penalize (basic sanity)
    if ($timeInBed !== null && $timeInBed < 300) $score -= 10; // <5h

    return clampInt($score, 0, 100);
}

/**
 * Consistency metric using "midsleep" time variability over recent days.
 * Lower variability = better consistency.
 */
function midsleepMinutes(?string $bedtime, ?string $wake_time): ?int {
    $tib = computeTimeInBedMinutes($bedtime, $wake_time);
    if ($tib === null) return null;

    $b = timeToMinutes($bedtime);
    if ($b === null) return null;

    // midsleep = bedtime + TIB/2 (wrap)
    return ($b + (int)round($tib / 2)) % (24 * 60);
}

function stddev(array $values): float {
    $n = count($values);
    if ($n < 2) return 0.0;
    $mean = array_sum($values) / $n;
    $var = 0.0;
    foreach ($values as $v) {
        $var += ($v - $mean) ** 2;
    }
    return sqrt($var / ($n - 1));
}

// --- POST: upsert sleep log
$errors = [];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, $posted_csrf)) {
        $errors[] = 'Security check failed. Please refresh and try again.';
    } else {
        $sleep_date = trim((string)($_POST['sleep_date'] ?? ''));
        $bedtime = trim((string)($_POST['bedtime'] ?? ''));
        $wake_time = trim((string)($_POST['wake_time'] ?? ''));
        $minutes_awake = (int)($_POST['minutes_awake'] ?? 0);
        $naps_minutes = (int)($_POST['naps_minutes'] ?? 0);
        $caffeine_after_2pm = isset($_POST['caffeine_after_2pm']) ? 1 : 0;
        $alcohol = isset($_POST['alcohol']) ? 1 : 0;
        $screen_minutes = (int)($_POST['screen_minutes'] ?? 0);
        $room_temp_c_raw = trim((string)($_POST['room_temp_c'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        // Validation
        if ($sleep_date === '') $errors[] = 'Sleep date is required.';
        if ($sleep_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sleep_date)) $errors[] = 'Sleep date must be YYYY-MM-DD.';

        $bedtime = ($bedtime === '') ? null : $bedtime;
        $wake_time = ($wake_time === '') ? null : $wake_time;

        $minutes_awake = clampInt($minutes_awake, 0, 600);
        $naps_minutes = clampInt($naps_minutes, 0, 600);
        $screen_minutes = clampInt($screen_minutes, 0, 600);
        $notes = ($notes === '') ? null : mb_substr($notes, 0, 500);

        $room_temp_c = null;
        if ($room_temp_c_raw !== '') {
            $room_temp_c = (float)$room_temp_c_raw;
            if ($room_temp_c < 5 || $room_temp_c > 30) $errors[] = 'Room temp (°C) should be between 5 and 30.';
        }

        if (!$errors) {
            // Upsert by (user_id, sleep_date)
            $sql = "
                INSERT INTO sleep_logs
                    (user_id, sleep_date, bedtime, wake_time, minutes_awake, naps_minutes,
                     caffeine_after_2pm, alcohol, screen_minutes, room_temp_c, notes)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    bedtime = VALUES(bedtime),
                    wake_time = VALUES(wake_time),
                    minutes_awake = VALUES(minutes_awake),
                    naps_minutes = VALUES(naps_minutes),
                    caffeine_after_2pm = VALUES(caffeine_after_2pm),
                    alcohol = VALUES(alcohol),
                    screen_minutes = VALUES(screen_minutes),
                    room_temp_c = VALUES(room_temp_c),
                    notes = VALUES(notes)
            ";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $errors[] = 'DB prepare failed: ' . h($conn->error);
            } else {
                // bind types: i s s s i i i i i d s
                // BUT note: nullable strings/double => use variables and pass null OK in mysqli? (it will pass as "")
                // We'll handle nulls explicitly by using set to null and binding as string/double with null allowed.
                $bedtime_b = $bedtime;
                $wake_time_b = $wake_time;
                $notes_b = $notes;
                $room_temp_c_b = $room_temp_c;

                // If your mysqli doesn't handle null well in bind_param for "s"/"d", convert nulls to NULL via stmt->send_long_data not needed.
                // In practice, many setups accept null, but to be safe:
                // We'll insert nulls by using conditional placeholders would be more complex; keep simple and coerce empty to null in SQL via NULLIF.
                // Easiest: rewrite SQL with NULLIF for bedtime/wake_time/notes.
                $stmt->close();

                $sql = "
                    INSERT INTO sleep_logs
                        (user_id, sleep_date, bedtime, wake_time, minutes_awake, naps_minutes,
                         caffeine_after_2pm, alcohol, screen_minutes, room_temp_c, notes)
                    VALUES
                        (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?, NULLIF(?, ''))
                    ON DUPLICATE KEY UPDATE
                        bedtime = NULLIF(VALUES(bedtime), ''),
                        wake_time = NULLIF(VALUES(wake_time), ''),
                        minutes_awake = VALUES(minutes_awake),
                        naps_minutes = VALUES(naps_minutes),
                        caffeine_after_2pm = VALUES(caffeine_after_2pm),
                        alcohol = VALUES(alcohol),
                        screen_minutes = VALUES(screen_minutes),
                        room_temp_c = VALUES(room_temp_c),
                        notes = NULLIF(VALUES(notes), '')
                ";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $errors[] = 'DB prepare failed: ' . h($conn->error);
                } else {
                    $bedtime_s = $bedtime ?? '';
                    $wake_time_s = $wake_time ?? '';
                    $notes_s = $notes ?? '';

                    // room_temp_c: bind as double; if null, pass 0 and allow NULL? Not possible with bind_param directly.
                    // We'll use NULLIF trick by sending empty string for temp and using NULLIF in SQL too.
                    $stmt->close();

                    $sql = "
                        INSERT INTO sleep_logs
                            (user_id, sleep_date, bedtime, wake_time, minutes_awake, naps_minutes,
                             caffeine_after_2pm, alcohol, screen_minutes, room_temp_c, notes)
                        VALUES
                            (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
                        ON DUPLICATE KEY UPDATE
                            bedtime = NULLIF(VALUES(bedtime), ''),
                            wake_time = NULLIF(VALUES(wake_time), ''),
                            minutes_awake = VALUES(minutes_awake),
                            naps_minutes = VALUES(naps_minutes),
                            caffeine_after_2pm = VALUES(caffeine_after_2pm),
                            alcohol = VALUES(alcohol),
                            screen_minutes = VALUES(screen_minutes),
                            room_temp_c = NULLIF(VALUES(room_temp_c), ''),
                            notes = NULLIF(VALUES(notes), '')
                    ";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        $errors[] = 'DB prepare failed: ' . h($conn->error);
                    } else {
                        $temp_s = ($room_temp_c === null) ? '' : (string)$room_temp_c;

                        $stmt->bind_param(
                            'isssiiiiiss',
                            $user_id,
                            $sleep_date,
                            $bedtime_s,
                            $wake_time_s,
                            $minutes_awake,
                            $naps_minutes,
                            $caffeine_after_2pm,
                            $alcohol,
                            $screen_minutes,
                            $temp_s,
                            $notes_s
                        );

                        if (!$stmt->execute()) {
                            $errors[] = 'DB execute failed: ' . h($stmt->error);
                        } else {
                            $flash = 'Saved sleep log.';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// --- Fetch last 14 logs for dashboard + compute metrics
$logs = [];
$stmt = $conn->prepare("
    SELECT sleep_date, bedtime, wake_time, minutes_awake, naps_minutes,
           caffeine_after_2pm, alcohol, screen_minutes, room_temp_c, notes
    FROM sleep_logs
    WHERE user_id = ?
    ORDER BY sleep_date DESC
    LIMIT 14
");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $logs[] = $r;
    $stmt->close();
}

// --- Compute headline indicators using most recent log (if exists)
$latest = $logs[0] ?? null;

$last_duration = null;
$last_tib = null;
$last_eff = null;
$last_hygiene = null;

if ($latest) {
    $last_tib = computeTimeInBedMinutes($latest['bedtime'], $latest['wake_time']);
    if ($last_tib !== null) {
        $last_duration = max(0, $last_tib - (int)$latest['minutes_awake']);
        $last_eff = ($last_tib > 0) ? round(($last_duration / $last_tib) * 100) : null;
    }
    $last_hygiene = computeHygieneScore($latest, $last_tib);
}

// --- 7-day average and sleep debt vs target
$target_hours = 7.5;
$target_minutes = (int)round($target_hours * 60);

$durations = [];
$midsleeps = [];
foreach (array_slice($logs, 0, 7) as $r) {
    $tib = computeTimeInBedMinutes($r['bedtime'], $r['wake_time']);
    if ($tib === null) continue;
    $dur = max(0, $tib - (int)$r['minutes_awake']);
    $durations[] = $dur;

    $ms = midsleepMinutes($r['bedtime'], $r['wake_time']);
    if ($ms !== null) $midsleeps[] = $ms;
}

$avg7 = null;
$debt7 = null;
$consistency_sd = null;
if (count($durations) > 0) {
    $avg7 = (int)round(array_sum($durations) / count($durations));
    // debt = (target * days) - actual
    $debt7 = max(0, ($target_minutes * count($durations)) - array_sum($durations));
}
if (count($midsleeps) >= 3) {
    // circularity is a thing; this is a simple SD that works well if your schedule isn't spanning midnight wildly.
    $consistency_sd = stddev($midsleeps); // minutes
}

// --- Bedtime suggestions (calculator) using chosen wake time
$calc_wake = $_GET['calc_wake'] ?? ($latest['wake_time'] ?? '07:00');
$wakeMins = timeToMinutes($calc_wake) ?? (7 * 60);
$bedSuggestions = calcBedtimesFromWake($wakeMins, 15, [6,5,4]);

// --- Defaults for the form
$today = (new DateTime('now'))->format('Y-m-d');
$default_date = $today;
$default_bed = $latest['bedtime'] ?? '';
$default_wake = $latest['wake_time'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>exFIT • Sleep Dashboard</title>
  <style>
    :root{
      --bg0:#070A12;
      --bg1:#0B1020;
      --card: rgba(255,255,255,.06);
      --card2: rgba(255,255,255,.08);
      --stroke: rgba(255,255,255,.12);
      --text: rgba(255,255,255,.92);
      --muted: rgba(255,255,255,.70);
      --muted2: rgba(255,255,255,.55);
      --orange:#ff6a00;
      --purple:#7c4dff;
      --good:#3cff9a;
      --warn:#ffd166;
      --bad:#ff4d6d;
      --shadow: 0 20px 70px rgba(0,0,0,.55);
      --r: 18px;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      color:var(--text);
      background: radial-gradient(1200px 700px at 20% 10%, rgba(124,77,255,.22), transparent 60%),
                  radial-gradient(1000px 650px at 80% 15%, rgba(255,106,0,.18), transparent 55%),
                  linear-gradient(180deg, var(--bg0), var(--bg1));
      min-height:100vh;
      padding:20px;
    }
    .shell{max-width:1100px;margin:0 auto}
    .topbar{
      display:flex;align-items:center;justify-content:space-between;
      gap:12px;margin-bottom:16px;
    }
    .title{
      display:flex;flex-direction:column;gap:2px;
    }
    .title h1{margin:0;font-size:20px;letter-spacing:.4px}
    .title p{margin:0;color:var(--muted2);font-size:13px}
    .btn{
      display:inline-flex;align-items:center;gap:10px;
      padding:10px 14px;border-radius:14px;
      border:1px solid var(--stroke);
      background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));
      color:var(--text);text-decoration:none;
      box-shadow: 0 10px 30px rgba(0,0,0,.35);
      cursor:pointer;
      font-weight:600;
      white-space:nowrap;
    }
    .grid{
      display:grid;
      grid-template-columns: 1.1fr .9fr;
      gap:16px;
    }
    @media (max-width: 980px){
      .grid{grid-template-columns: 1fr}
    }
    .card{
      border:1px solid var(--stroke);
      background: var(--card);
      border-radius: var(--r);
      box-shadow: var(--shadow);
      overflow:hidden;
    }
    .cardHead{
      padding:14px 16px;
      border-bottom:1px solid var(--stroke);
      background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
      display:flex;align-items:center;justify-content:space-between;gap:12px;
    }
    .cardHead h2{margin:0;font-size:15px}
    .cardBody{padding:16px}
    .muted{color:var(--muted)}
    .row{display:flex;gap:10px;flex-wrap:wrap}
    .kpis{
      display:grid;
      grid-template-columns: repeat(4, 1fr);
      gap:12px;
    }
    @media (max-width: 980px){
      .kpis{grid-template-columns: repeat(2, 1fr)}
    }
    .kpi{
      padding:12px;border-radius:16px;
      border:1px solid var(--stroke);
      background: var(--card2);
    }
    .kpi .label{font-size:12px;color:var(--muted2);margin-bottom:6px}
    .kpi .value{font-size:18px;font-weight:800;letter-spacing:.2px}
    .pill{
      font-size:12px;
      padding:4px 10px;border-radius:999px;
      border:1px solid var(--stroke);
      color:var(--muted);
      background: rgba(0,0,0,.22);
      white-space:nowrap;
    }
    .pill.good{color:rgba(60,255,154,.95); border-color: rgba(60,255,154,.25)}
    .pill.warn{color:rgba(255,209,102,.95); border-color: rgba(255,209,102,.25)}
    .pill.bad{color:rgba(255,77,109,.95); border-color: rgba(255,77,109,.25)}
    form .field{
      display:flex;flex-direction:column;gap:6px;margin-bottom:12px;min-width:160px;
    }
    label{font-size:12px;color:var(--muted2)}
    input, textarea{
      width:100%;
      padding:10px 12px;
      border-radius: 14px;
      border:1px solid var(--stroke);
      background: rgba(0,0,0,.25);
      color: var(--text);
      outline:none;
    }
    textarea{min-height:84px;resize:vertical}
    .checks{display:flex;gap:14px;flex-wrap:wrap;margin:6px 0 10px}
    .check{
      display:flex;align-items:center;gap:8px;
      padding:10px 12px;border-radius:14px;
      border:1px solid var(--stroke);
      background: rgba(0,0,0,.20);
    }
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 10px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;font-size:13px}
    th{color:var(--muted2);font-weight:700}
    .small{font-size:12px;color:var(--muted2)}
    .flash{padding:10px 12px;border-radius:14px;border:1px solid rgba(60,255,154,.25);background:rgba(60,255,154,.08);color:rgba(60,255,154,.95);margin-bottom:12px}
    .error{padding:10px 12px;border-radius:14px;border:1px solid rgba(255,77,109,.25);background:rgba(255,77,109,.08);color:rgba(255,77,109,.95);margin-bottom:12px}
    .divider{height:1px;background:rgba(255,255,255,.10);margin:14px 0}
    .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
  </style>
</head>
<body>
  <div class="shell">

    <div class="topbar">
      <div class="title">
        <h1>Sleep Dashboard</h1>
        <p>Track recovery, consistency, and behaviors that affect sleep quality.</p>
      </div>
      <div class="row">
        <a class="btn" href="/modules/index.php">‹ Modules</a>
        <a class="btn" href="/modules/index.php">Dashboard</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="flash"><?= h($flash) ?></div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
      <div class="error"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="grid">

      <!-- LEFT: KPIs + Calculator + Recent logs -->
      <section class="card">
        <div class="cardHead">
          <h2>Indicators</h2>
          <span class="pill"><?= $latest ? 'Latest: ' . h($latest['sleep_date']) : 'No logs yet' ?></span>
        </div>
        <div class="cardBody">

          <div class="kpis">
            <div class="kpi">
              <div class="label">Last night sleep</div>
              <div class="value"><?= $last_duration !== null ? h(formatHoursMinutes($last_duration)) : '—' ?></div>
              <div class="small">Net sleep (time in bed minus awake minutes)</div>
            </div>

            <div class="kpi">
              <div class="label">Efficiency</div>
              <div class="value"><?= $last_eff !== null ? h((string)$last_eff) . '%' : '—' ?></div>
              <div class="small">Simple estimate</div>
            </div>

            <div class="kpi">
              <div class="label">7-day avg</div>
              <div class="value"><?= $avg7 !== null ? h(formatHoursMinutes($avg7)) : '—' ?></div>
              <div class="small">Rolling average</div>
            </div>

            <div class="kpi">
              <div class="label">Sleep debt (7d)</div>
              <div class="value"><?= $debt7 !== null ? h(formatHoursMinutes($debt7)) : '—' ?></div>
              <div class="small">Target: <?= h((string)$target_hours) ?>h/night</div>
            </div>
          </div>

          <div class="divider"></div>

          <?php
            $consPill = 'pill';
            $consText = '—';
            if ($consistency_sd !== null) {
              $consText = (int)round($consistency_sd) . ' min';
              if ($consistency_sd <= 35) { $consPill .= ' good'; }
              elseif ($consistency_sd <= 60) { $consPill .= ' warn'; }
              else { $consPill .= ' bad'; }
            }

            $hygPill = 'pill';
            $hygText = '—';
            if ($last_hygiene !== null) {
              $hygText = $last_hygiene . '/100';
              if ($last_hygiene >= 80) { $hygPill .= ' good'; }
              elseif ($last_hygiene >= 65) { $hygPill .= ' warn'; }
              else { $hygPill .= ' bad'; }
            }
          ?>

          <div class="row" style="justify-content:space-between;align-items:center">
            <div>
              <div class="small">Consistency (midsleep variability, last 7 logs)</div>
              <div class="<?= $consPill ?>"><?= h($consText) ?></div>
            </div>
            <div>
              <div class="small">Hygiene score (latest)</div>
              <div class="<?= $hygPill ?>"><?= h($hygText) ?></div>
            </div>
          </div>

          <div class="divider"></div>

          <div class="card" style="box-shadow:none">
            <div class="cardHead">
              <h2>Bedtime Calculator</h2>
              <span class="pill mono">wake: <?= h(substr((string)$calc_wake, 0, 5)) ?></span>
            </div>
            <div class="cardBody">
              <form method="get" class="row" style="align-items:flex-end">
                <div class="field">
                  <label for="calc_wake">Wake time</label>
                  <input id="calc_wake" name="calc_wake" type="time" value="<?= h(substr((string)$calc_wake, 0, 5)) ?>" />
                </div>
                <button class="btn" type="submit">Calculate</button>
              </form>

              <div class="small muted" style="margin-top:8px">
                Assumes ~15 minutes to fall asleep and 90-minute cycles. Pick a bedtime that lands near the end of a cycle.
              </div>

              <div class="divider"></div>

              <table>
                <thead>
                  <tr>
                    <th>Cycles</th>
                    <th>Sleep time</th>
                    <th>Suggested bedtime</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($bedSuggestions as $s): ?>
                    <tr>
                      <td><?= (int)$s['cycles'] ?></td>
                      <td><?= h($s['sleep_time']) ?></td>
                      <td class="mono"><?= h($s['bedtime']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <div class="small muted" style="margin-top:10px">
                Pro tip: consistency (same wake time) beats perfection.
              </div>
            </div>
          </div>

          <div class="divider"></div>

          <h2 style="margin:0 0 8px;font-size:15px">Recent Logs</h2>
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Bed → Wake</th>
                <th>Net Sleep</th>
                <th>Awake</th>
                <th>Temp</th>
                <th>Flags</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$logs): ?>
                <tr><td colspan="6" class="muted">No entries yet. Add your first log on the right.</td></tr>
              <?php else: ?>
                <?php foreach ($logs as $r): ?>
                  <?php
                    $tib = computeTimeInBedMinutes($r['bedtime'], $r['wake_time']);
                    $dur = ($tib !== null) ? max(0, $tib - (int)$r['minutes_awake']) : null;
                    $flags = [];
                    if ((int)$r['caffeine_after_2pm'] === 1) $flags[] = 'caffeine';
                    if ((int)$r['alcohol'] === 1) $flags[] = 'alcohol';
                    if ((int)$r['screen_minutes'] >= 60) $flags[] = 'screens';
                    if ((int)$r['minutes_awake'] >= 45) $flags[] = 'fragmented';
                    $temp = ($r['room_temp_c'] !== null && $r['room_temp_c'] !== '') ? ((float)$r['room_temp_c']) : null;
                  ?>
                  <tr>
                    <td class="mono"><?= h($r['sleep_date']) ?></td>
                    <td class="mono">
                      <?= $r['bedtime'] ? h(substr($r['bedtime'],0,5)) : '—' ?>
                      →
                      <?= $r['wake_time'] ? h(substr($r['wake_time'],0,5)) : '—' ?>
                    </td>
                    <td><?= $dur !== null ? h(formatHoursMinutes($dur)) : '—' ?></td>
                    <td><?= h((string)(int)$r['minutes_awake']) ?>m</td>
                    <td><?= $temp !== null ? h(number_format($temp,1)) . "°C" : '—' ?></td>
                    <td class="small"><?= $flags ? h(implode(', ', $flags)) : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

        </div>
      </section>

      <!-- RIGHT: Daily entry form + basic info -->
      <aside class="card">
        <div class="cardHead">
          <h2>Log Sleep</h2>
          <span class="pill">1 entry per day</span>
        </div>
        <div class="cardBody">

          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />

            <div class="row">
              <div class="field" style="flex:1">
                <label for="sleep_date">Sleep date (usually the wake date)</label>
                <input id="sleep_date" name="sleep_date" type="date" value="<?= h($default_date) ?>" required />
              </div>
            </div>

            <div class="row">
              <div class="field" style="flex:1">
                <label for="bedtime">Bedtime</label>
                <input id="bedtime" name="bedtime" type="time" value="<?= h($default_bed ? substr($default_bed,0,5) : '') ?>" />
              </div>
              <div class="field" style="flex:1">
                <label for="wake_time">Wake time</label>
                <input id="wake_time" name="wake_time" type="time" value="<?= h($default_wake ? substr($default_wake,0,5) : '') ?>" />
              </div>
            </div>

            <div class="row">
              <div class="field" style="flex:1">
                <label for="minutes_awake">Minutes awake during night</label>
                <input id="minutes_awake" name="minutes_awake" type="number" min="0" max="600" value="0" />
              </div>
              <div class="field" style="flex:1">
                <label for="naps_minutes">Naps (minutes)</label>
                <input id="naps_minutes" name="naps_minutes" type="number" min="0" max="600" value="0" />
              </div>
            </div>

            <div class="row">
              <div class="field" style="flex:1">
                <label for="screen_minutes">Screens in last 90 min (minutes)</label>
                <input id="screen_minutes" name="screen_minutes" type="number" min="0" max="600" value="0" />
              </div>
              <div class="field" style="flex:1">
                <label for="room_temp_c">Room temp (°C)</label>
                <input id="room_temp_c" name="room_temp_c" type="number" step="0.1" min="5" max="30" placeholder="e.g. 18.0" />
              </div>
            </div>

            <div class="checks">
              <label class="check">
                <input type="checkbox" name="caffeine_after_2pm" />
                Caffeine after 2pm
              </label>
              <label class="check">
                <input type="checkbox" name="alcohol" />
                Alcohol
              </label>
            </div>

            <div class="field">
              <label for="notes">Notes (optional)</label>
              <textarea id="notes" name="notes" maxlength="500" placeholder="Stress, travel, soreness, meds, room changes, etc."></textarea>
            </div>

            <button class="btn" type="submit">Save Log</button>
          </form>

          <div class="divider"></div>

          <h2 style="margin:0 0 8px;font-size:15px">Basic Sleep Targets</h2>
          <div class="small muted">
            <ul style="margin:8px 0 0 18px; padding:0; line-height:1.5">
              <li><b>Room temp:</b> ~15.5–19.5°C (60–67°F) for most adults.</li>
              <li><b>Anchor wake time</b> is the strongest lever for circadian stability.</li>
              <li><b>Cut caffeine</b> early (many do best with none after ~2pm).</li>
              <li><b>Alcohol</b> often increases fragmentation even if you fall asleep faster.</li>
              <li><b>Consistency</b> (same wake time) beats occasional long “catch-up” sleep.</li>
            </ul>
          </div>

        </div>
      </aside>

    </div>
  </div>
</body>
</html>
