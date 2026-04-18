<?php
declare(strict_types=1);

/**
 * =============================================================================
 * FILE: /public/modules/motivation/angry/index.php
 * =============================================================================
 * MODULE: exFIT Motivation • ANGRY MODE (State Engineering)
 *
 * PURPOSE
 *  - Creates a controlled, high-stress “battle state” immediately prior to training:
 *      • DB-driven chant loops (repeatable head-chanting phrases)
 *      • DB-driven audio playlist (minor-key only) filtered by lift-type BPM windows
 *      • Session logging (chants + tracks chosen, intensity, lift type, timestamps)
 *      • Mandatory “Hard Cut” rule to instantly stop audio at end
 *
 * PHILOSOPHY (GRAY MENTALITY)
 *  - Positivity is scheduled for recovery.
 *  - Negativity/anger is used as a short-duration tool for action.
 *  - Minor keys + BPM selection maintain unresolved tension and urgency.
 *  - Hard Cut ends the war instantly to protect recovery and sleep.
 *
 * UI / FLOW
 *  1) User selects:
 *      • Intensity: 1 (Pressure), 2 (Aggression), 3 (War Mode)
 *      • Lift Type: DEADLIFT / SQUAT / BENCH / VOLUME / ACCESSORY / CONDITIONING
 *  2) User presses START:
 *      • Creates a new motivation_sessions row
 *      • Picks N chants (anti-repeat) from motivation_chants
 *      • Picks N tracks (anti-repeat) from audio_tracks filtered by:
 *          - is_minor_key = 1
 *          - lift_type matches
 *          - bpm BETWEEN min/max for that lift type
 *          - intensity within track’s intensity_min/intensity_max
 *      • Returns payload via JSON:
 *          { session_id, bpm_window, chants[], tracks[] }
 *  3) JS runs the chant loop:
 *      • Pulses the text on cadence_ms
 *      • Repeats each chant for a fixed repeat count
 *  4) Audio plays through queued tracks (no crossfades / no fades)
 *  5) HARD CUT + END:
 *      • Immediately stops audio (pause, reset time, clear src)
 *      • Ends session in DB (ended_at, completed=1)
 *      • Redirects into Recovery module with ?motivation_id=...
 *
 * POST ENDPOINTS (AJAX)
 *  - This same file handles POST requests:
 *      • action=start  -> creates session, selects chants/tracks, returns JSON payload
 *      • action=end    -> marks session ended, returns ok
 *      • action=state  -> optional ping
 *
 * DATABASE EXPECTATIONS
 *  - Tables required:
 *      • motivation_sessions
 *      • motivation_chants
 *      • motivation_session_chants
 *      • audio_tracks
 *      • motivation_session_tracks
 *
 * BPM WINDOWS (ENFORCED)
 *  - Deadlift:   95–115 BPM
 *  - Squat:     105–125 BPM
 *  - Bench:     115–135 BPM
 *  - Volume:    120–145 BPM
 *  - Accessory: 135–155 BPM
 *  - Conditioning: 150–170 BPM
 *
 * AUDIO STORAGE
 *  - audio_tracks.file_path must point to a playable URL from the browser, e.g.:
 *      /assets/audio/angry/track.mp3
 *  - If no tracks exist, UI still runs chant loop and displays a warning.
 *
 * SECURITY
 *  - Recommended: protect with session guard (user must be logged in).
 *  - All session end requests validate ownership (session_id + user_id).
 *  - File paths are not user-submitted; they come from DB.
 *
 * NOTES / EXTENSIONS
 *  - You can tie lift type automatically from workout context (today’s scheduled work).
 *  - You can escalate intensity for users with low compliance.
 *  - You can add admin CRUD for chants/tracks later.
 * =============================================================================
 */

session_start();

require_once __DIR__ . '/../../../../config/config.php'; // $conn (mysqli)

// If you want this protected, uncomment your guard:
// require_once __DIR__ . '/../../../includes/session_guard.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0 && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  // If you want it public, remove this block.
  header('Location: /login.php');
  exit;
}

function json_out(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function bpm_window(string $liftType, int $intensity): array {
  // intensity nudges toward low/mid/high of the range in selection (done in SQL ordering)
  // Windows are fixed by doctrine:
  switch ($liftType) {
    case 'DEADLIFT': return [95, 115];
    case 'SQUAT':    return [105, 125];
    case 'BENCH':    return [115, 135];
    case 'ACCESSORY':return [135, 155];
    case 'CONDITIONING': return [150, 170];
    case 'VOLUME':
    default:         return [120, 145];
  }
}

function clamp_int(int $v, int $min, int $max): int { return max($min, min($max, $v)); }

function pick_weighted_unique(array $rows, int $count): array {
  // Build a weighted pool (simple + effective)
  $pool = [];
  foreach ($rows as $r) {
    $w = clamp_int((int)($r['weight'] ?? 5), 1, 10);
    for ($i=0; $i<$w; $i++) $pool[] = $r;
  }
  shuffle($pool);
  $picked = [];
  $seen = [];
  foreach ($pool as $r) {
    if (count($picked) >= $count) break;
    $id = (int)$r['id'];
    if (isset($seen[$id])) continue;
    $seen[$id]=true;
    $picked[]=$r;
  }
  return $picked;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($userId <= 0) json_out(['ok'=>false,'error'=>'not_logged_in'], 401);

  if ($action === 'start') {
    $intensity = clamp_int((int)($_POST['intensity'] ?? 2), 1, 3);
    $liftType  = strtoupper((string)($_POST['lift_type'] ?? 'VOLUME'));
    $allowedLift = ['DEADLIFT','SQUAT','BENCH','VOLUME','ACCESSORY','CONDITIONING'];
    if (!in_array($liftType, $allowedLift, true)) $liftType = 'VOLUME';

    [$bpmMin, $bpmMax] = bpm_window($liftType, $intensity);

    // Create session
    $stmt = $conn->prepare("INSERT INTO motivation_sessions (user_id, mode, intensity, lift_type) VALUES (?, 'ANGRY', ?, ?)");
    $stmt->bind_param("iis", $userId, $intensity, $liftType);
    $stmt->execute();
    $sessionId = (int)$stmt->insert_id;
    $stmt->close();

    // ---- Pick chants (avoid last 3 sessions)
    $chantCount = 5;
    $chantRows = [];
    $sql = "
      SELECT mc.id, mc.phrase, mc.cadence_ms, mc.weight
      FROM motivation_chants mc
      WHERE mc.is_active=1 AND mc.mode='ANGRY' AND mc.intensity=?
        AND mc.id NOT IN (
          SELECT msc.chant_id
          FROM motivation_session_chants msc
          JOIN motivation_sessions ms ON ms.id = msc.session_id
          WHERE ms.user_id=? AND ms.mode='ANGRY'
          ORDER BY ms.started_at DESC
          LIMIT 25
        )
      ORDER BY RAND()
      LIMIT 60
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $intensity, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $chantRows[] = $row;
    $stmt->close();

    // Fallback if pool too small: allow repeats
    if (count($chantRows) < $chantCount) {
      $chantRows = [];
      $stmt = $conn->prepare("SELECT id, phrase, cadence_ms, weight FROM motivation_chants WHERE is_active=1 AND mode='ANGRY' AND intensity=? ORDER BY RAND() LIMIT 60");
      $stmt->bind_param("i", $intensity);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) $chantRows[] = $row;
      $stmt->close();
    }

    $chants = pick_weighted_unique($chantRows, $chantCount);

    // Log chants
    $ins = $conn->prepare("INSERT INTO motivation_session_chants (session_id, chant_id, ordinal, repeats) VALUES (?, ?, ?, ?)");
    $ordinal = 1;
    foreach ($chants as $c) {
      $chantId = (int)$c['id'];
      $repeats = 6; // fixed for v1; you can make it intensity-based
      $ins->bind_param("iiii", $sessionId, $chantId, $ordinal, $repeats);
      $ins->execute();
      $ordinal++;
    }
    $ins->close();

    // ---- Pick tracks (enforce lift-type BPM windows + minor key + intensity range)
    $trackCount = 4;
    $tracks = [];
    $trackRows = [];
    $sql = "
      SELECT t.id, t.title, t.artist, t.bpm, t.energy_score, t.file_path
      FROM audio_tracks t
      WHERE t.is_active=1
        AND t.is_minor_key=1
        AND t.lift_type=?
        AND t.bpm BETWEEN ? AND ?
        AND ? BETWEEN t.intensity_min AND t.intensity_max
        AND t.id NOT IN (
          SELECT mst.track_id
          FROM motivation_session_tracks mst
          JOIN motivation_sessions ms ON ms.id = mst.session_id
          WHERE ms.user_id=? AND ms.mode='ANGRY'
          ORDER BY ms.started_at DESC
          LIMIT 25
        )
      ORDER BY
        t.energy_score DESC,
        CASE
          WHEN ?=1 THEN t.bpm ASC
          WHEN ?=2 THEN ABS(t.bpm - ((?+?)/2)) ASC
          ELSE t.bpm DESC
        END,
        RAND()
      LIMIT 25
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiiiiiii", $liftType, $bpmMin, $bpmMax, $intensity, $userId, $intensity, $intensity, $bpmMin, $bpmMax);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $trackRows[] = $row;
    $stmt->close();

    // Fallback: broaden to VOLUME if no tracks for lift type
    if (count($trackRows) < 1 && $liftType !== 'VOLUME') {
      $liftType2 = 'VOLUME';
      [$bpmMin2, $bpmMax2] = bpm_window($liftType2, $intensity);
      $stmt = $conn->prepare("
        SELECT id,title,artist,bpm,energy_score,file_path
        FROM audio_tracks
        WHERE is_active=1 AND is_minor_key=1
          AND lift_type=?
          AND bpm BETWEEN ? AND ?
          AND ? BETWEEN intensity_min AND intensity_max
        ORDER BY energy_score DESC, RAND()
        LIMIT 25
      ");
      $stmt->bind_param("siii", $liftType2, $bpmMin2, $bpmMax2, $intensity);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) $trackRows[] = $row;
      $stmt->close();
    }

    // Pick top N unique (energy already sorted)
    $seenT = [];
    foreach ($trackRows as $r) {
      if (count($tracks) >= $trackCount) break;
      $id = (int)$r['id'];
      if (isset($seenT[$id])) continue;
      $seenT[$id]=true;
      $tracks[] = $r;
    }

    // Log tracks
    if (count($tracks) > 0) {
      $insT = $conn->prepare("INSERT INTO motivation_session_tracks (session_id, track_id, ordinal) VALUES (?, ?, ?)");
      $o=1;
      foreach ($tracks as $t) {
        $tid = (int)$t['id'];
        $insT->bind_param("iii", $sessionId, $tid, $o);
        $insT->execute();
        $o++;
      }
      $insT->close();
    }

    // Return payload
    json_out([
      'ok' => true,
      'session_id' => $sessionId,
      'intensity' => $intensity,
      'lift_type' => $liftType,
      'bpm_window' => ['min'=>$bpmMin,'max'=>$bpmMax],
      'chants' => array_map(fn($c)=>[
        'phrase'=>$c['phrase'],
        'cadence_ms'=>(int)$c['cadence_ms'],
        'repeats'=>6
      ], $chants),
      'tracks' => array_map(fn($t)=>[
        'title'=>$t['title'],
        'artist'=>$t['artist'],
        'bpm'=>(int)$t['bpm'],
        'file_path'=>$t['file_path']
      ], $tracks),
    ]);
  }

  if ($action === 'state') {
    // lightweight ping endpoint if you want (optional)
    json_out(['ok'=>true,'t'=>date('c')]);
  }

  if ($action === 'end') {
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $completed = (int)($_POST['completed'] ?? 1);

    // Ensure ownership
    $stmt = $conn->prepare("SELECT id FROM motivation_sessions WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $sessionId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = (bool)$res->fetch_assoc();
    $stmt->close();
    if (!$ok) json_out(['ok'=>false,'error'=>'bad_session'], 403);

    $stmt = $conn->prepare("UPDATE motivation_sessions SET ended_at=NOW(), completed=? WHERE id=?");
    $stmt->bind_param("ii", $completed, $sessionId);
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
  <title>exFIT • Angry Mode</title>
  <style>
    :root{
      --bg:#070810; --txt:#f1f1f6; --muted:#a5a5b2; --line:rgba(255,255,255,.12);
      --hot:#ff4b2b; --vio:#8b5cf6;
    }
    body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;color:var(--txt);background:var(--bg);}
    .bg{position:fixed;inset:0;background:
      linear-gradient(180deg,rgba(0,0,0,.20),rgba(0,0,0,.78)),
      url('/assets/images/angry_mode_gym.jpg') center/cover no-repeat;
      filter:saturate(.95) contrast(1.06); z-index:-2;}
    .grain{position:fixed; inset:0; background-image:url('/assets/images/grain.png'); opacity:.10; z-index:-1; pointer-events:none;}
    .wrap{max-width:980px;margin:0 auto;padding:18px;}
    .top{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px;}
    .title{font-weight:900;letter-spacing:.4px;}
    .sub{color:var(--muted);font-size:13px;margin-top:4px}
    .pill{border:1px solid var(--line);border-radius:999px;padding:10px 12px;background:rgba(0,0,0,.35);backdrop-filter: blur(10px);}
    .pill a{color:var(--txt);text-decoration:none}
    .panel{border:1px solid var(--line);border-radius:18px;background:rgba(0,0,0,.35);backdrop-filter: blur(10px);padding:14px;}
    .grid{display:grid;gap:12px;grid-template-columns:repeat(12,1fr)}
    .box{grid-column:span 12;border:1px solid var(--line);border-radius:16px;background:rgba(0,0,0,.35);padding:14px}
    @media(min-width:900px){
      .box.half{grid-column:span 6}
    }
    label{display:block;color:var(--muted);font-size:12px;margin-bottom:6px}
    select,input{width:100%;padding:12px;border-radius:12px;border:1px solid rgba(255,255,255,.16);
      background:rgba(0,0,0,.35);color:var(--txt);}
    .btn{border:1px solid var(--line);border-radius:14px;padding:12px 14px;background:rgba(0,0,0,.35);
      color:var(--txt);cursor:pointer;font-weight:700}
    .btn.hot{border-color:rgba(255,75,43,.35);box-shadow:0 0 0 1px rgba(255,75,43,.12) inset;}
    .btn.vio{border-color:rgba(139,92,246,.35);box-shadow:0 0 0 1px rgba(139,92,246,.12) inset;}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .chantStage{
      min-height:150px; display:flex; align-items:center; justify-content:center;
      border-radius:18px;border:1px solid var(--line);
      background:linear-gradient(180deg,rgba(255,75,43,.14),rgba(0,0,0,.45));
      text-align:center;
      font-size:clamp(28px, 5vw, 44px);
      font-weight:900; letter-spacing:.7px;
    }
    .meta{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;color:var(--muted);font-size:12px;margin-top:10px}
    .pulse{
      transform: translateY(-1px) scale(1.01);
      box-shadow: 0 0 0 1px rgba(255,75,43,.18) inset, 0 0 34px rgba(255,75,43,.08);
    }
    .track{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid rgba(255,255,255,.10);border-radius:14px;background:rgba(0,0,0,.25)}
    .small{color:var(--muted);font-size:12px}
    audio{width:100%}
    .warn{color:#ffd2c8}
  </style>
</head>
<body>
<div class="bg"></div><div class="grain"></div>

<div class="wrap">
  <div class="top">
    <div>
      <div class="title">ANGRY MODE • PRE-WORK STATE ENGINEERING</div>
      <div class="sub">Positivity is for recovery. Anger is for battle. Minor keys only. Hard cut at end.</div>
    </div>
    <div class="pill">
      <a href="/library/motivation_state_engineering/index.php">‹ Library</a>
      <span style="opacity:.4">|</span>
      <a href="/modules/motivation/recovery/index.php">Recovery</a>
    </div>
  </div>

  <div class="grid">
    <div class="box half">
      <label>Intensity</label>
      <select id="intensity">
        <option value="1">1 — Pressure</option>
        <option value="2" selected>2 — Aggression</option>
        <option value="3">3 — War Mode</option>
      </select>
    </div>
    <div class="box half">
      <label>Lift Type (controls BPM window)</label>
      <select id="liftType">
        <option value="DEADLIFT">Deadlift (95–115)</option>
        <option value="SQUAT">Squat (105–125)</option>
        <option value="BENCH">Bench (115–135)</option>
        <option value="VOLUME" selected>Volume (120–145)</option>
        <option value="ACCESSORY">Accessory (135–155)</option>
        <option value="CONDITIONING">Conditioning (150–170)</option>
      </select>
    </div>

    <div class="box">
      <div class="chantStage" id="chantStage">READY.</div>
      <div class="meta">
        <div id="metaLeft">Select intensity + lift type.</div>
        <div id="metaRight">Hard cut enforced at end.</div>
      </div>
    </div>

    <div class="box half">
      <button class="btn hot" id="btnStart" onclick="startSession()">START</button>
      <button class="btn" id="btnStop" onclick="hardCutAndEnd()" disabled>HARD CUT + END</button>
      <div class="small" style="margin-top:10px">
        If you hear nothing, you haven’t added tracks yet. Add rows to <code>audio_tracks</code> with valid <code>file_path</code>.
      </div>
      <div class="small warn" id="warn"></div>
    </div>

    <div class="box half">
      <div class="small" style="margin-bottom:8px">Audio (minor key only)</div>
      <audio id="audio" controls preload="auto"></audio>
      <div id="trackList" style="margin-top:10px;display:flex;flex-direction:column;gap:10px"></div>
    </div>
  </div>
</div>

<script>
  let sessionId = null;
  let chantQueue = [];
  let trackQueue = [];
  let chantIndex = 0;
  let repeatIndex = 0;
  let chantTimer = null;
  let currentCad = 850;
  let repeats = 6;
  let trackIndex = 0;

  const chantEl = document.getElementById('chantStage');
  const audioEl = document.getElementById('audio');
  const metaLeft = document.getElementById('metaLeft');
  const warnEl = document.getElementById('warn');

  function setWarn(msg){ warnEl.textContent = msg || ''; }

  function pulse(){
    chantEl.classList.add('pulse');
    setTimeout(()=>chantEl.classList.remove('pulse'), 120);
  }

  function stopChantLoop(){
    if(chantTimer){ clearInterval(chantTimer); chantTimer=null; }
  }

  function renderTracks(){
    const wrap = document.getElementById('trackList');
    wrap.innerHTML = '';
    trackQueue.forEach((t, i)=>{
      const div = document.createElement('div');
      div.className='track';
      const left = document.createElement('div');
      left.innerHTML = `<div style="font-weight:800">${escapeHtml(t.title || 'Track')}</div>
                        <div class="small">${escapeHtml(t.artist || '')}</div>`;
      const right = document.createElement('div');
      right.innerHTML = `<div style="font-weight:800">${t.bpm} BPM</div>
                         <div class="small">#${i+1}</div>`;
      div.appendChild(left); div.appendChild(right);
      wrap.appendChild(div);
    });
  }

  function escapeHtml(s){
    return (s||'').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  async function startSession(){
    setWarn('');
    document.getElementById('btnStart').disabled = true;

    const intensity = document.getElementById('intensity').value;
    const lift_type = document.getElementById('liftType').value;

    const form = new FormData();
    form.append('action','start');
    form.append('intensity', intensity);
    form.append('lift_type', lift_type);

    const res = await fetch('', { method:'POST', body: form });
    const data = await res.json();

    if(!data.ok){
      setWarn('Failed to start session: ' + (data.error || 'unknown'));
      document.getElementById('btnStart').disabled = false;
      return;
    }

    sessionId = data.session_id;
    chantQueue = data.chants || [];
    trackQueue = data.tracks || [];
    chantIndex = 0;
    repeatIndex = 0;
    trackIndex = 0;

    metaLeft.textContent = `Session #${sessionId} • ${data.lift_type} • BPM ${data.bpm_window.min}-${data.bpm_window.max} • Intensity ${data.intensity}`;
    renderTracks();

    if(trackQueue.length === 0){
      setWarn('No tracks found for your filters. Add tracks or broaden lift types.');
    } else {
      loadTrack(0);
    }

    document.getElementById('btnStop').disabled = false;
    runChantLoop();
  }

  function runChantLoop(){
    stopChantLoop();
    if(chantQueue.length === 0){
      chantEl.textContent = 'NO CHANTS.';
      return;
    }
    const current = chantQueue[chantIndex];
    currentCad = Math.max(300, Math.min(2000, parseInt(current.cadence_ms || 850,10)));
    repeats = Math.max(1, Math.min(20, parseInt(current.repeats || 6,10)));
    chantEl.textContent = current.phrase || 'MOVE.';

    chantTimer = setInterval(()=>{
      pulse();
      repeatIndex++;

      // “chant beat” behavior: keep same phrase, pulse and re-affirm
      chantEl.textContent = (current.phrase || 'MOVE.');

      if(repeatIndex >= repeats){
        repeatIndex = 0;
        chantIndex++;

        if(chantIndex >= chantQueue.length){
          // End of chant program
          stopChantLoop();
          chantEl.textContent = 'DONE. START WORK.';
          // Optional: automatically pause audio here? (I leave it running until user ends or moves to workout)
          return;
        }

        // Next chant
        const next = chantQueue[chantIndex];
        currentCad = Math.max(300, Math.min(2000, parseInt(next.cadence_ms || 850,10)));
        repeats = Math.max(1, Math.min(20, parseInt(next.repeats || 6,10)));
        chantEl.textContent = next.phrase || 'MOVE.';
        // Update timer to new cadence
        stopChantLoop();
        runChantLoop();
      }
    }, currentCad);
  }

  function loadTrack(i){
    if(i < 0 || i >= trackQueue.length) return;
    const t = trackQueue[i];
    audioEl.src = t.file_path;
    audioEl.play().catch(()=>{});
  }

  audioEl.addEventListener('ended', ()=>{
    trackIndex++;
    if(trackIndex < trackQueue.length){
      loadTrack(trackIndex);
    }
  });

  // HARD CUT rule: instant stop, reset, clear src
  function hardCutAudio(){
    try{
      audioEl.pause();
      audioEl.currentTime = 0;
      audioEl.removeAttribute('src');
      audioEl.load();
    }catch(e){}
  }

  async function hardCutAndEnd(){
    // 1) stop visuals
    stopChantLoop();

    // 2) HARD CUT (instant, no fade)
    hardCutAudio();
    chantEl.textContent = 'SILENCE. DOWNSHIFT.';
    metaLeft.textContent = 'Hard cut executed. Ending session...';

    // 3) end session in DB
    if(sessionId){
      const form = new FormData();
      form.append('action','end');
      form.append('session_id', sessionId);
      form.append('completed', 1);
      await fetch('', { method:'POST', body: form }).catch(()=>{});
    }

    // 4) redirect immediately to Recovery module (paired system)
    const url = `/modules/motivation/recovery/index.php?motivation_id=${encodeURIComponent(sessionId||'')}`;
    window.location.href = url;
  }
</script>
</body>
</html>
