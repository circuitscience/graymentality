<?php
declare(strict_types=1);

/**
 * =============================================================================
 * FILE: /public/modules/motivation/recovery/index.php
 * =============================================================================
 * MODULE: exFIT Motivation • RECOVERY (Downshift Protocol)
 *
 * PURPOSE
 *  - Paired module designed to run immediately after Angry Mode (or any workout).
 *  - Forces nervous-system down-regulation and protects recovery / sleep:
 *      • Guided breathing timer (4–2–6 default; also 4–4–4 and box breathing)
 *      • Random short prompts from DB (jaw/shoulders/slow exhale/hydrate/walk)
 *      • Logs recovery sessions (duration, pattern, timestamps, completed)
 *
 * PHILOSOPHY
 *  - Hard Cut ends aggressive state instantly.
 *  - Recovery begins with silence and a structured downshift.
 *  - Positivity belongs here: reflection, gratitude, calming cues.
 *
 * UI / FLOW
 *  1) User optionally lands here with:
 *      ?motivation_id=123   (from Angry Mode redirect)
 *  2) User selects breathing pattern + duration and presses START:
 *      • Creates recovery_sessions row
 *      • Pulls 1 prompt from recovery_prompts (weighted randomness is optional later)
 *      • Runs breathing countdown (JS)
 *  3) User presses END + LOG (or auto-finish):
 *      • Updates recovery_sessions ended_at + completed=1
 *      • Can redirect to dashboard / workout summary
 *
 * POST ENDPOINTS (AJAX)
 *  - action=start -> creates recovery session and returns {recovery_id, prompt}
 *  - action=end   -> ends recovery session
 *
 * DATABASE EXPECTATIONS
 *  - Tables required:
 *      • recovery_sessions
 *      • recovery_prompts
 *  - Optional linkage:
 *      • recovery_sessions.motivation_id references motivation_sessions.id
 *
 * AUDIO POLICY (IMPORTANT)
 *  - No aggressive music here.
 *  - If you add audio, keep it in a separate “recovery” playlist with calm rules.
 *
 * SECURITY
 *  - Recommended: protect with session guard.
 *  - End requests validate ownership of recovery_id.
 *
 * NOTES / EXTENSIONS
 *  - Add a “post-workout reflection” logger (1–2 questions, stored in DB).
 *  - Add hydration/steps reminders as a follow-up CTA.
 *  - Add an automatic redirect back to workout summary after completion.
 * =============================================================================
 */

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../auth_functions.php';

function json_out(array $data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

$isPost = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
if ($isPost) {
  if (auth_session_has_timed_out()) {
    logout_user();
    json_out([
      'ok' => false,
      'error' => 'session_timeout',
      'redirect' => auth_login_url([
        'reason' => 'timeout',
        'message' => auth_timeout_message(),
      ]),
    ], 401);
  }

  $authUser = check_auth();
  if (!$authUser) {
    json_out([
      'ok' => false,
      'error' => 'not_logged_in',
      'redirect' => auth_login_url([
        'reason' => 'auth_required',
        'message' => auth_login_message_for_reason('auth_required'),
      ]),
    ], 401);
  }
} else {
  $authUser = require_auth();
}

$userId = (int)($authUser['id'] ?? 0);

$motivationId = isset($_GET['motivation_id']) ? (int)$_GET['motivation_id'] : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($userId <= 0) json_out(['ok'=>false,'error'=>'not_logged_in'], 401);

  if ($action === 'start') {
    $duration = max(60, min(600, (int)($_POST['duration_sec'] ?? 180)));
    $pattern  = (string)($_POST['pattern'] ?? '4-2-6');
    if (!in_array($pattern, ['4-2-6','4-4-4','box'], true)) $pattern = '4-2-6';
    $motId = (int)($_POST['motivation_id'] ?? 0);
    $motId = $motId > 0 ? $motId : null;

    if ($motId !== null) {
      // Ownership check
      $stmt = $conn->prepare("SELECT id FROM motivation_sessions WHERE id=? AND user_id=?");
      $stmt->bind_param("ii", $motId, $userId);
      $stmt->execute();
      $ok = (bool)$stmt->get_result()->fetch_assoc();
      $stmt->close();
      if (!$ok) $motId = null;
    }

    $stmt = $conn->prepare("INSERT INTO recovery_sessions (user_id, motivation_id, duration_sec, breath_pattern) VALUES (?, ?, ?, ?)");
    // bind_param doesn't accept null directly unless using "i" with variable that can be null? We'll do workaround:
    if ($motId === null) {
      $null = null;
      $stmt->bind_param("iiis", $userId, $null, $duration, $pattern);
    } else {
      $stmt->bind_param("iiis", $userId, $motId, $duration, $pattern);
    }
    $stmt->execute();
    $rid = (int)$stmt->insert_id;
    $stmt->close();

    // Pull a prompt
    $prompt = null;
    $res = $conn->query("SELECT prompt FROM recovery_prompts WHERE is_active=1 ORDER BY RAND() LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) $prompt = $row['prompt'];

    json_out(['ok'=>true,'recovery_id'=>$rid,'prompt'=>$prompt]);
  }

  if ($action === 'end') {
    $rid = (int)($_POST['recovery_id'] ?? 0);

    $stmt = $conn->prepare("SELECT id FROM recovery_sessions WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $rid, $userId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ok) json_out(['ok'=>false,'error'=>'bad_session'], 403);

    $stmt = $conn->prepare("UPDATE recovery_sessions SET ended_at=NOW(), completed=1 WHERE id=?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $stmt->close();

    json_out(['ok'=>true]);
  }

  json_out(['ok'=>false,'error'=>'unknown_action'], 400);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>exFIT • Recovery Downshift</title>
  <style>
    :root{
      --bg:#070a12; --txt:#eef0f6; --muted:#a3a7b7; --line:rgba(255,255,255,.12);
      --vio:#8b5cf6; --cool:#2dd4bf;
    }
    body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;color:var(--txt);background:var(--bg);}
    .bg{position:fixed;inset:0;background:
      linear-gradient(180deg,rgba(0,0,0,.25),rgba(0,0,0,.80)),
      url('/assets/images/recovery_room_dark.jpg') center/cover no-repeat;
      filter:saturate(.9) contrast(1.05); z-index:-2;}
    .grain{position:fixed; inset:0; background-image:url('/assets/images/grain.png'); opacity:.10; z-index:-1; pointer-events:none;}
    .wrap{max-width:980px;margin:0 auto;padding:18px;}
    .top{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px;}
    .title{font-weight:900;letter-spacing:.4px;}
    .sub{color:var(--muted);font-size:13px;margin-top:4px}
    .pill{border:1px solid var(--line);border-radius:999px;padding:10px 12px;background:rgba(0,0,0,.35);backdrop-filter: blur(10px);}
    .pill a{color:var(--txt);text-decoration:none}
    .grid{display:grid;gap:12px;grid-template-columns:repeat(12,1fr)}
    .box{grid-column:span 12;border:1px solid var(--line);border-radius:16px;background:rgba(0,0,0,.35);backdrop-filter: blur(10px);padding:14px}
    @media(min-width:900px){ .half{grid-column:span 6} }
    label{display:block;color:var(--muted);font-size:12px;margin-bottom:6px}
    select,input{width:100%;padding:12px;border-radius:12px;border:1px solid rgba(255,255,255,.16);
      background:rgba(0,0,0,.35);color:var(--txt);}
    .btn{border:1px solid var(--line);border-radius:14px;padding:12px 14px;background:rgba(0,0,0,.35);
      color:var(--txt);cursor:pointer;font-weight:800}
    .btn.vio{border-color:rgba(139,92,246,.35);box-shadow:0 0 0 1px rgba(139,92,246,.12) inset;}
    .btn.cool{border-color:rgba(45,212,191,.30);box-shadow:0 0 0 1px rgba(45,212,191,.10) inset;}
    .stage{
      min-height:160px; display:flex; flex-direction:column; align-items:center; justify-content:center;
      border-radius:18px;border:1px solid var(--line);
      background:linear-gradient(180deg,rgba(45,212,191,.10),rgba(0,0,0,.45));
      text-align:center;
    }
    .big{font-size:clamp(28px, 5vw, 46px);font-weight:900;letter-spacing:.6px}
    .small{font-size:12px;color:var(--muted)}
    .prompt{margin-top:10px;color:#d8fdf8;font-weight:700}
  </style>
</head>
<body>
<div class="bg"></div><div class="grain"></div>

<div class="wrap">
  <div class="top">
    <div>
      <div class="title">RECOVERY • DOWN-SHIFT PROTOCOL</div>
      <div class="sub">Anger ends. Silence begins. You keep the gains by turning the system off.</div>
    </div>
    <div class="pill">
      <a href="/modules/motivation/angry/index.php">‹ Angry</a>
      <span style="opacity:.4">|</span>
      <a href="/modules/Library/learning_hub.php">Library</a>
    </div>
  </div>

  <div class="grid">
    <div class="box half">
      <label>Breathing pattern</label>
      <select id="pattern">
        <option value="4-2-6" selected>4–2–6 (inhale-hold-exhale)</option>
        <option value="4-4-4">4–4–4</option>
        <option value="box">Box (4–4–4–4)</option>
      </select>
    </div>
    <div class="box half">
      <label>Duration</label>
      <select id="duration">
        <option value="120">2 minutes</option>
        <option value="180" selected>3 minutes</option>
        <option value="300">5 minutes</option>
      </select>
    </div>

    <div class="box">
      <div class="stage">
        <div class="big" id="breathStage">READY.</div>
        <div class="prompt" id="prompt"></div>
        <div class="small" id="timerMeta">Press START.</div>
      </div>
    </div>

    <div class="box half">
      <button class="btn cool" id="btnStart" onclick="startRecovery()">START</button>
      <button class="btn vio" id="btnEnd" onclick="endRecovery()" disabled>END + LOG</button>
      <div class="small" style="margin-top:10px">Optional: add calm audio if you want — but keep it separate from Angry Mode.</div>
    </div>

    <div class="box half">
      <div class="small"><strong>Rule:</strong> No aggressive music here. This is parasympathetic territory.</div>
      <div class="small" style="margin-top:8px">After this, you can redirect to dashboard or workout summary.</div>
      <button class="btn" style="margin-top:12px" onclick="goHome()">Return Home</button>
    </div>
  </div>
</div>

<script>
  const motivationId = <?= (int)($motivationId ?? 0) ?>;

  let recoveryId=null;
  let totalSec=0;
  let tLeft=0;
  let timer=null;

  const stage = document.getElementById('breathStage');
  const meta  = document.getElementById('timerMeta');
  const promptEl = document.getElementById('prompt');

  function stopTimer(){
    if(timer){ clearInterval(timer); timer=null; }
  }

  function patternSteps(p){
    // returns list of [label, seconds]
    if(p === '4-4-4') return [['INHALE',4],['HOLD',4],['EXHALE',4]];
    if(p === 'box')   return [['INHALE',4],['HOLD',4],['EXHALE',4],['HOLD',4]];
    return [['INHALE',4],['HOLD',2],['EXHALE',6]]; // 4-2-6 default
  }

  async function startRecovery(){
    document.getElementById('btnStart').disabled = true;

    const pattern = document.getElementById('pattern').value;
    totalSec = parseInt(document.getElementById('duration').value || '180', 10);
    tLeft = totalSec;

    const form = new FormData();
    form.append('action','start');
    form.append('duration_sec', totalSec);
    form.append('pattern', pattern);
    if(motivationId) form.append('motivation_id', motivationId);

    const res = await fetch('', { method:'POST', body: form });
    const data = await res.json();

    if(!data.ok){
      stage.textContent = 'ERROR.';
      meta.textContent = 'Could not start recovery session.';
      document.getElementById('btnStart').disabled = false;
      return;
    }

    recoveryId = data.recovery_id;
    promptEl.textContent = data.prompt ? data.prompt : '';
    document.getElementById('btnEnd').disabled = false;

    runBreathing(pattern);
  }

  function runBreathing(pattern){
    stopTimer();

    const steps = patternSteps(pattern);
    let si = 0;         // step index
    let sLeft = steps[0][1];

    function tick(){
      const [label] = steps[si];
      stage.textContent = label;
      meta.textContent = `Remaining: ${format(tLeft)} • Session #${recoveryId || '—'}`;

      tLeft--;
      sLeft--;

      if(tLeft <= 0){
        stopTimer();
        stage.textContent = 'DONE.';
        meta.textContent = 'Downshift complete. End + Log.';
        return;
      }

      if(sLeft <= 0){
        si = (si + 1) % steps.length;
        sLeft = steps[si][1];
      }
    }

    tick();
    timer = setInterval(tick, 1000);
  }

  function format(sec){
    sec = Math.max(0, sec);
    const m = Math.floor(sec/60);
    const s = sec%60;
    return `${m}:${s.toString().padStart(2,'0')}`;
  }

  async function endRecovery(){
    stopTimer();

    if(recoveryId){
      const form = new FormData();
      form.append('action','end');
      form.append('recovery_id', recoveryId);
      await fetch('', { method:'POST', body: form }).catch(()=>{});
    }

    stage.textContent = 'RECOVER.';
    meta.textContent  = 'Logged. Return when ready.';
    document.getElementById('btnEnd').disabled = true;
  }

  function goHome(){
    window.location.href = '/index.php';
  }
</script>
</body>
</html>
