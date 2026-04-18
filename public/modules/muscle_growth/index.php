<?php
declare(strict_types=1);

/**
 * Muscle Growth Signals + Daily Check-In (exFIT)
 * ------------------------------------------------------------------------
 * FILE: /public/modules/muscle_growth/index.php
 *
 * PURPOSE
 *  - Educational module: “How do you know your muscles are growing?”
 *  - Daily check-in logger that tracks:
 *      • strength_progress (1–5)
 *      • recovery_score (1–5)
 *      • soreness_score (1–5)
 *      • notes (optional)
 *  - Displays recent check-ins (latest 10)
 *
 * UX / LAYOUT
 *  - Uses the same module shell pattern as Frame Potential:
 *      • blurred full-screen background image (module-specific)
 *      • translucent centered content card
 *      • sticky header with “‹ Modules” back button
 *  - Content uses an accordion for “Signals” and a form for “Daily Check-In”.
 *
 * DATABASE EXPECTATIONS
 *  - Table: muscle_growth_logs
 *    Columns used:
 *      id (PK), created_at (datetime), strength_progress (int),
 *      recovery_score (int), soreness_score (int), notes (text)
 *
 * NOTES
 *  - This file currently READS logs only (your original code).
 *  - The check-in form is included; if you want it to SAVE via PHP submit,
 *    add a POST handler (or keep your existing JS/AJAX endpoint).
 */

require_once __DIR__ . '/../../../config/config.php';

// Fetch last 10 logs
$logs = [];
$sql = "SELECT id, created_at, strength_progress, recovery_score, soreness_score, notes
        FROM muscle_growth_logs
        ORDER BY created_at DESC
        LIMIT 10";
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $result->free();
}

// helper
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Muscle Growth Signals | exFIT</title>
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
            --module-bg-image: url('../assets/muscle_growth.png');
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

        /* ===== Frame Potential shell ===== */
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
            position: sticky;
            top: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(to bottom, rgba(0,0,0,0.82), rgba(0,0,0,0.35));
            backdrop-filter: blur(12px);
        }
        .module-header-left { display:flex; flex-direction:column; gap:0.15rem; }
        .module-title { font-size: 1rem; font-weight: 600; letter-spacing: 0.03em; }
        .module-subtitle { font-size: 0.75rem; opacity: 0.7; }

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

        /* ===== Inner content styling (Frame-Potential vibe) ===== */
        .page-wrap { display:flex; flex-direction:column; gap:1.15rem; }

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
            margin-top: 0.75rem;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(0, 1.55fr);
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

        /* Accordion */
        .accordion { display:flex; flex-direction:column; gap:0.6rem; }
        .accordion-item { border-radius: 0.85rem; overflow:hidden; }
        .accordion-header {
            width: 100%;
            text-align: left;
            background: rgba(0,0,0,0.35);
            border: none;
            color: #f5f5f5;
            padding: 0.75rem 0.8rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
            cursor:pointer;
        }
        .accordion-header h3 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 650;
        }
        .accordion-toggle {
            font-size: 1.1rem;
            opacity: 0.85;
            width: 28px;
            text-align: center;
        }
        .accordion-body {
            display: none;
            padding: 0.75rem 0.85rem 0.9rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            font-size: 0.82rem;
            opacity: 0.95;
            line-height: 1.45;
        }
        .accordion-body ul { margin: 0.4rem 0 0; padding-left: 1.1rem; }
        .accordion-body li { margin: 0.22rem 0; }

        /* Form */
        label { font-size:0.78rem; display:block; margin:0.55rem 0 0.15rem; opacity:0.85; }
        select, textarea {
            width: 100%;
            padding: 0.45rem 0.55rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(148,163,184,0.5);
            background: rgba(15,15,15,0.9);
            color: #f5f5f5;
            font-size: 0.82rem;
        }
        textarea { min-height: 80px; resize: vertical; }
        .muted { opacity: 0.75; font-size: 0.78rem; }

        /* History cards */
        .history-grid {
            display:grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem;
        }
        @media (max-width: 700px) { .history-grid { grid-template-columns: 1fr; } }
        .history-date { font-size:0.78rem; opacity:0.8; }
        .history-metrics { margin: 0.6rem 0 0; padding-left: 1.1rem; font-size: 0.8rem; opacity: 0.92; }
        .history-notes { margin: 0.6rem 0 0; font-size: 0.8rem; opacity: 0.85; white-space: pre-wrap; }

        footer.lab-footer {
            margin-top: 0.7rem;
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
                <div class="module-title">Muscle Growth Signals</div>
                <div class="module-subtitle">Track strength, recovery, and consistency—stop guessing.</div>
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
                                <h1>How do you know your muscles are growing?</h1>
                                <p>Don’t guess. Track the signals. Let the trend tell the truth.</p>
                            </div>
                        </div>
                        <div class="score-pill">
                            Gray Mentality • <strong>Growth is a pattern</strong>
                        </div>
                    </header>

                    <section class="hero">
                        <div class="hero-card">
                            <h2 class="hero-title">
                                The signal isn’t one thing — it’s the <span class="hero-highlight">stack</span>
                            </h2>
                            <p class="hero-sub">
                                Strength drift, better RIR, faster recovery, improved contraction quality, and consistent sessions.
                                One data point lies. A trend doesn’t.
                            </p>
                            <button class="btn-primary" id="scrollToCheckin" type="button">
                                Log Today’s Check-In <span>➜</span>
                            </button>
                        </div>

                        <div class="hero-card">
                            <h2 class="hero-title">Quick rule</h2>
                            <p class="hero-sub">
                                If you can do the same work with less grind, and you recover faster,
                                you’re adapting — and growth usually follows.
                            </p>
                            <p class="tagline">
                                Sweet spot soreness is often 2–3: sore but functional.
                            </p>
                        </div>
                    </section>

                    <section class="layout-grid">
                        <!-- Signals -->
                        <article class="card" id="content">
                            <div class="card-header">
                                <h3 class="card-title">Key Signals</h3>
                                <p class="card-subtitle">Tap each section. Keep it simple. Keep it honest.</p>
                            </div>

                            <div class="accordion" id="accordion">

                                <?php
                                $items = [
                                    [
                                        't' => '1. Strength is increasing (with good form)',
                                        'b' => '<p>Small jumps matter. More reps at the same weight, or the same reps at slightly heavier weight, with clean form: that’s adaptation.</p>
                                                <ul>
                                                  <li>More reps at the same weight, same form</li>
                                                  <li>Same reps at slightly heavier weight</li>
                                                  <li>Less “grinding” at previous maxes</li>
                                                </ul>'
                                    ],
                                    [
                                        't' => '2. More reps before fatigue (RIR improves)',
                                        'b' => '<p>If your RIR improves at the same weight, your system is getting more efficient. That often precedes visible change.</p>'
                                    ],
                                    [
                                        't' => '3. Recovery gets faster',
                                        'b' => '<p>DOMS that used to last 3 days fades to 1. You’re sore, but functional. That’s a classic sign the body is adapting.</p>
                                                <ul>
                                                  <li>Less soreness between identical workouts</li>
                                                  <li>Fewer “crushed” days</li>
                                                  <li>More energy to train again on schedule</li>
                                                </ul>'
                                    ],
                                    [
                                        't' => '4. Visual changes over time',
                                        'b' => '<p>Photos beat mirrors. Lighting and pumps lie. Weekly photos (same pose, same light) reveal truth.</p>'
                                    ],
                                    [
                                        't' => '5. Muscle density & contraction quality',
                                        'b' => '<p>Does a muscle “snap” to a hard contraction? Feel thicker? That’s neuromuscular efficiency improving — a strong precursor.</p>'
                                    ],
                                    [
                                        't' => '6. System signals: appetite, sleep, drive',
                                        'b' => '<p>When training is working, the system often responds: deeper sleep, more hunger, more drive. Not always — but often.</p>'
                                    ],
                                    [
                                        't' => '7. Numbers don’t lie',
                                        'b' => '<p>Track what matters: loads, reps, RIR, bodyweight, circumference, and compliance. Growth isn’t a feeling. It’s a pattern.</p>'
                                    ],
                                ];

                                foreach ($items as $idx => $it):
                                ?>
                                    <div class="card accordion-item">
                                        <button class="accordion-header" type="button" aria-expanded="false">
                                            <h3><?php echo e($it['t']); ?></h3>
                                            <span class="accordion-toggle">+</span>
                                        </button>
                                        <div class="accordion-body">
                                            <?php echo $it['b']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            </div>
                        </article>

                        <!-- Daily Check-In -->
                        <article class="card" id="checkin">
                            <div class="card-header">
                                <h3 class="card-title">Daily Muscle Growth Check-In</h3>
                                <p class="card-subtitle">Rate today. Save it. Over time, you’ll see your pattern.</p>
                            </div>

                            <!-- Keep as-is (JS/AJAX) or convert to standard POST later -->
                            <form id="checkinForm" class="form-card">
                                <label for="strength_progress">Strength Progress Today</label>
                                <select id="strength_progress" name="strength_progress" required>
                                    <option value="">Select…</option>
                                    <option value="1">1 – Weak / Regressed</option>
                                    <option value="2">2 – Flat</option>
                                    <option value="3">3 – Slight progress</option>
                                    <option value="4">4 – Solid progress</option>
                                    <option value="5">5 – Big jump</option>
                                </select>

                                <label for="recovery_score">Recovery / Energy</label>
                                <select id="recovery_score" name="recovery_score" required>
                                    <option value="">Select…</option>
                                    <option value="1">1 – Wrecked</option>
                                    <option value="2">2 – Dragging</option>
                                    <option value="3">3 – Okay</option>
                                    <option value="4">4 – Good</option>
                                    <option value="5">5 – Fresh & ready</option>
                                </select>

                                <label for="soreness_score">Soreness</label>
                                <select id="soreness_score" name="soreness_score" required>
                                    <option value="">Select…</option>
                                    <option value="1">1 – None</option>
                                    <option value="2">2 – Light</option>
                                    <option value="3">3 – Moderate</option>
                                    <option value="4">4 – Heavy</option>
                                    <option value="5">5 – Brutal</option>
                                </select>
                                <div class="muted">Sweet spot is usually 2–3: sore but functional.</div>

                                <label for="notes">Notes (optional)</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="PRs, poor sleep, stress, nutrition, etc."></textarea>

                                <button type="submit" class="btn-primary">Save Check-In <span>➜</span></button>
                                <div id="formMessage" class="muted" style="margin-top:0.5rem;"></div>
                            </form>

                            <div class="muted" style="margin-top:0.7rem;">
                                If your saving is handled by an AJAX endpoint, keep your existing JS and point it here.
                            </div>
                        </article>
                    </section>

                    <!-- History -->
                    <article class="card" id="history">
                        <div class="card-header">
                            <h3 class="card-title">Recent Check-Ins</h3>
                            <p class="card-subtitle">Last 10 entries.</p>
                        </div>

                        <?php if (empty($logs)): ?>
                            <div class="muted">No check-ins yet. Log your first one above.</div>
                        <?php else: ?>
                            <div class="history-grid">
                                <?php foreach ($logs as $log): ?>
                                    <div class="card">
                                        <div class="history-date"><?php echo e((string)$log['created_at']); ?></div>
                                        <ul class="history-metrics">
                                            <li><strong>Strength:</strong> <?php echo (int)$log['strength_progress']; ?>/5</li>
                                            <li><strong>Recovery:</strong> <?php echo (int)$log['recovery_score']; ?>/5</li>
                                            <li><strong>Soreness:</strong> <?php echo (int)$log['soreness_score']; ?>/5</li>
                                        </ul>
                                        <?php if (!empty($log['notes'])): ?>
                                            <div class="history-notes"><?php echo e((string)$log['notes']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <footer class="lab-footer">
                        exFIT • Gray Mentality — Die Living.
                    </footer>

                </div>
            </section>
        </main>
    </div>
</div>

<script>
    // Scroll CTA
    document.getElementById('scrollToCheckin')?.addEventListener('click', () => {
        document.getElementById('checkin')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Accordion behavior
    document.querySelectorAll('.accordion-header').forEach(btn => {
        btn.addEventListener('click', () => {
            const body = btn.parentElement.querySelector('.accordion-body');
            const isOpen = body.style.display === 'block';
            document.querySelectorAll('.accordion-body').forEach(b => b.style.display = 'none');
            document.querySelectorAll('.accordion-header').forEach(h => {
                h.setAttribute('aria-expanded', 'false');
                const t = h.querySelector('.accordion-toggle');
                if (t) t.textContent = '+';
            });

            if (!isOpen) {
                body.style.display = 'block';
                btn.setAttribute('aria-expanded', 'true');
                const t = btn.querySelector('.accordion-toggle');
                if (t) t.textContent = '–';
            }
        });
    });

    // NOTE: Your original page used assets/script.js to SAVE the form.
    // If you have an existing endpoint, wire it in here.
    document.getElementById('checkinForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const msg = document.getElementById('formMessage');
        msg.textContent = 'Saving…';

        // TODO: Replace with your real endpoint (example):
        // const resp = await fetch('save_checkin.php', { method:'POST', body:new FormData(e.target) });
        // const data = await resp.json();

        // Placeholder feedback (remove when wired)
        setTimeout(() => {
            msg.textContent = 'Saved (placeholder). Wire this to your save endpoint.';
        }, 450);
    });
</script>

</body>
</html>
