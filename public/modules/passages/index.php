<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth_functions.php';

$authUser = require_auth();
$db = get_db_connection();

function gm_passage_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function gm_passage_ensure_tables(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS passages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            depth ENUM('short', 'deep', 'long') NOT NULL DEFAULT 'deep',
            category VARCHAR(80) NOT NULL DEFAULT 'general',
            status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'active',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS passage_steps (
            id INT PRIMARY KEY AUTO_INCREMENT,
            passage_id INT NOT NULL,
            step_order INT NOT NULL,
            step_type ENUM('interrupt', 'recognition', 'tension', 'observation', 'expansion', 'release') NOT NULL,
            body TEXT NOT NULL,
            cta_label VARCHAR(80) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_passage_step (passage_id, step_order),
            CONSTRAINT fk_passage_steps_passage
                FOREIGN KEY (passage_id) REFERENCES passages(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS user_passage_events (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            passage_id INT NOT NULL,
            event_type ENUM('start', 'step', 'complete') NOT NULL,
            step_order INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_passage_created (user_id, passage_id, created_at),
            CONSTRAINT fk_user_passage_events_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_user_passage_events_passage
                FOREIGN KEY (passage_id) REFERENCES passages(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function gm_passage_seed(PDO $db): void
{
    $passages = [
        [
            'title' => 'The Narrowing',
            'slug' => 'the-narrowing-short',
            'depth' => 'short',
            'category' => 'continuity',
            'steps' => [
                ['interrupt', 'Most people do not notice life narrowing while it is happening.', 'Continue'],
                ['tension', "Experience creates wisdom.\n\nIt also creates avoidance.", 'Continue'],
                ['release', 'Notice where routine speaks louder than curiosity today.', 'Return to Dashboard'],
            ],
        ],
        [
            'title' => 'The Narrowing',
            'slug' => 'the-narrowing',
            'depth' => 'deep',
            'category' => 'continuity',
            'steps' => [
                ['interrupt', 'Most people do not notice life narrowing while it is happening.', 'Continue'],
                ['recognition', 'The mind often preserves identity long after behavior changes.', 'Continue'],
                ['tension', "Experience creates wisdom.\n\nIt also creates avoidance.", 'Continue'],
                ['observation', "Which parts of your current personality were discovered...\nand which were constructed?", 'Continue'],
                ['expansion', 'Some people continue expanding long after society expects contraction.', 'Continue'],
                ['release', 'Pay attention today to the moments where routine speaks louder than curiosity.', 'Return to Dashboard'],
            ],
        ],
        [
            'title' => 'The Narrowing',
            'slug' => 'the-narrowing-long',
            'depth' => 'long',
            'category' => 'continuity',
            'steps' => [
                ['interrupt', 'Most people do not notice life narrowing while it is happening.', 'Continue'],
                ['recognition', 'A routine can begin as discipline and end as protection.', 'Continue'],
                ['recognition', 'The mind often preserves identity long after behavior changes.', 'Continue'],
                ['tension', "Experience creates wisdom.\n\nIt also creates avoidance.", 'Continue'],
                ['observation', "Which parts of your current personality were discovered...\nand which were constructed?", 'Continue'],
                ['expansion', 'Curiosity may be one of the last forms of resistance.', 'Continue'],
                ['release', 'Pay attention today to the moments where routine speaks louder than curiosity.', 'Return to Dashboard'],
            ],
        ],
    ];

    foreach ($passages as $passage) {
        $stmt = $db->prepare("SELECT id FROM passages WHERE slug = ? LIMIT 1");
        $stmt->execute([$passage['slug']]);
        $passageId = (int)($stmt->fetchColumn() ?: 0);

        if ($passageId <= 0) {
            $insert = $db->prepare(
                "INSERT INTO passages (title, slug, depth, category, status)
                 VALUES (?, ?, ?, ?, 'active')"
            );
            $insert->execute([$passage['title'], $passage['slug'], $passage['depth'], $passage['category']]);
            $passageId = (int)$db->lastInsertId();
        }

        $stepStmt = $db->prepare(
            "INSERT IGNORE INTO passage_steps (passage_id, step_order, step_type, body, cta_label)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($passage['steps'] as $index => $step) {
            $stepStmt->execute([$passageId, $index + 1, $step[0], $step[1], $step[2]]);
        }
    }
}

function gm_passage_depth_from_request(): string
{
    $depth = strtolower(trim((string)($_GET['depth'] ?? 'deep')));
    return in_array($depth, ['short', 'deep', 'long'], true) ? $depth : 'deep';
}

function gm_passage_find(PDO $db, string $depth): array
{
    $stmt = $db->prepare(
        "SELECT id, title, slug, depth, category
         FROM passages
         WHERE status = 'active'
           AND depth = ?
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at IS NULL OR ends_at >= NOW())
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->execute([$depth]);
    $passage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$passage && $depth !== 'deep') {
        $stmt->execute(['deep']);
        $passage = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!is_array($passage)) {
        return [];
    }

    $stepsStmt = $db->prepare(
        "SELECT step_order, step_type, body, cta_label
         FROM passage_steps
         WHERE passage_id = ?
         ORDER BY step_order ASC"
    );
    $stepsStmt->execute([(int)$passage['id']]);
    $steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);
    $passage['steps'] = is_array($steps) ? $steps : [];

    return $passage;
}

function gm_passage_log(PDO $db, int $userId, int $passageId, string $eventType, ?int $stepOrder = null): void
{
    if (!in_array($eventType, ['start', 'step', 'complete'], true)) {
        return;
    }

    try {
        $stmt = $db->prepare(
            "INSERT INTO user_passage_events (user_id, passage_id, event_type, step_order)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $passageId, $eventType, $stepOrder]);
    } catch (Throwable $e) {
        error_log('[passages.event] ' . $e->getMessage());
    }
}

try {
    gm_passage_ensure_tables($db);
    gm_passage_seed($db);
    $depth = gm_passage_depth_from_request();
    $passage = gm_passage_find($db, $depth);
} catch (Throwable $e) {
    error_log('[passages] ' . $e->getMessage());
    $depth = 'deep';
    $passage = [];
}

if ($passage) {
    gm_passage_log($db, (int)$authUser['id'], (int)$passage['id'], 'start');
}

$steps = isset($passage['steps']) && is_array($passage['steps']) ? $passage['steps'] : [];
$eventUrl = '/modules/passages/log_event.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Today&apos;s Passage | Gray Mentality</title>
    <style>
        :root {
            --bg: #050506;
            --panel: rgba(9, 10, 12, 0.82);
            --line: rgba(255, 255, 255, 0.12);
            --line-hot: rgba(255, 106, 0, 0.48);
            --text: #f2efe8;
            --muted: #9e9990;
            --soft: #6f6a63;
            --orange: #ff6a00;
            --purple: #9b5cff;
        }

        * { box-sizing: border-box; }
        html { color-scheme: dark; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background:
                linear-gradient(rgba(255, 255, 255, 0.018) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.014) 1px, transparent 1px),
                radial-gradient(circle at 50% 120%, rgba(255, 106, 0, 0.18), transparent 40%),
                linear-gradient(180deg, #030304 0%, #09090c 50%, #050506 100%);
            background-size: 96px 96px, 96px 96px, auto, auto;
            overflow: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        body::before {
            background:
                linear-gradient(115deg, transparent 0 34%, rgba(255, 106, 0, 0.10) 34.25%, transparent 34.7% 100%),
                linear-gradient(245deg, transparent 0 34%, rgba(155, 92, 255, 0.09) 34.25%, transparent 34.7% 100%),
                linear-gradient(65deg, transparent 0 42%, rgba(255, 255, 255, 0.045) 42.15%, transparent 42.45% 100%),
                linear-gradient(295deg, transparent 0 42%, rgba(255, 255, 255, 0.038) 42.15%, transparent 42.45% 100%);
            background-size: 100% 100%;
            opacity: 0.72;
        }

        body::after {
            background:
                repeating-radial-gradient(ellipse at center, transparent 0 46px, rgba(255, 255, 255, 0.035) 47px, transparent 49px),
                linear-gradient(180deg, transparent 0%, rgba(255, 255, 255, 0.035) 50%, transparent 100%);
            transform: perspective(900px) rotateX(58deg) translateY(14vh) scale(1.35);
            transform-origin: 50% 100%;
            opacity: 0.32;
            animation: passage-field-drift 18s ease-in-out infinite alternate;
        }

        a, button { color: inherit; font: inherit; }
        button { cursor: pointer; }

        .passage-shell {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            width: min(1040px, calc(100vw - 32px));
            margin: 0 auto;
            padding: 22px 0;
        }

        .passage-top,
        .passage-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            color: var(--soft);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .quiet-link {
            text-decoration: none;
            border: 1px solid var(--line);
            padding: 10px 12px;
            background: rgba(255, 255, 255, 0.03);
        }

        .depth-switch {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .depth-switch a {
            text-decoration: none;
            border: 1px solid var(--line);
            padding: 8px 10px;
        }

        .depth-switch a[aria-current="true"] {
            border-color: var(--line-hot);
            color: var(--text);
        }

        .passage-stage {
            position: relative;
            display: grid;
            place-items: center;
            min-height: 0;
        }

        .passage-stage::before,
        .passage-stage::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: min(780px, 76vw);
            aspect-ratio: 1.62;
            border: 1px solid rgba(255, 255, 255, 0.06);
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        .passage-stage::before {
            box-shadow:
                0 0 0 38px rgba(255, 255, 255, 0.012),
                0 0 0 78px rgba(255, 106, 0, 0.018),
                0 0 0 132px rgba(155, 92, 255, 0.014);
            opacity: 0.9;
        }

        .passage-stage::after {
            width: min(560px, 62vw);
            border-color: rgba(255, 106, 0, 0.12);
            transform: translate(-50%, -50%) skewX(-7deg);
            opacity: 0.72;
        }

        .passage-step {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            opacity: 0;
            transform: translateY(16px) scale(1.055);
            pointer-events: none;
            transition:
                opacity 980ms cubic-bezier(0.22, 1, 0.36, 1),
                transform 980ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        .passage-step.is-active {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .passage-step.is-exiting {
            opacity: 0;
            transform: translateY(-10px) scale(0.84);
            pointer-events: none;
        }

        .passage-card {
            position: relative;
            width: min(780px, 100%);
            min-height: min(440px, 64vh);
            display: grid;
            align-content: center;
            gap: clamp(28px, 6vw, 58px);
            padding: clamp(26px, 6vw, 74px);
            border: 1px solid var(--line);
            background: var(--panel);
            box-shadow: 0 34px 120px rgba(0, 0, 0, 0.45);
            overflow: hidden;
        }

        .passage-card::before,
        .passage-card::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .passage-card::before {
            border: 1px solid transparent;
            background:
                linear-gradient(var(--panel), var(--panel)) padding-box,
                linear-gradient(115deg, rgba(255, 106, 0, 0.62), rgba(255, 255, 255, 0.08) 28%, rgba(155, 92, 255, 0.48) 52%, rgba(255, 255, 255, 0.06) 74%, rgba(255, 106, 0, 0.48)) border-box;
            opacity: 0.78;
            mask:
                linear-gradient(#000 0 0) padding-box,
                linear-gradient(#000 0 0);
            mask-composite: exclude;
        }

        .passage-card::after {
            inset: 18px;
            border: 1px solid rgba(255, 255, 255, 0.055);
            transform: skewX(-2deg);
            opacity: 0.9;
        }

        .step-type,
        .step-body,
        .step-action {
            position: relative;
            z-index: 1;
        }

        .step-type {
            color: var(--orange);
            font-size: 0.74rem;
            font-weight: 900;
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }

        .step-body {
            max-width: 680px;
            white-space: pre-line;
            font-size: clamp(1.55rem, 4.2vw, 3.2rem);
            line-height: 1.15;
            letter-spacing: 0;
        }

        .step-action {
            justify-self: start;
            min-height: 46px;
            border: 1px solid var(--line-hot);
            background: rgba(255, 106, 0, 0.08);
            padding: 0 18px;
            color: var(--text);
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .step-action:hover,
        .quiet-link:hover,
        .depth-switch a:hover {
            border-color: var(--orange);
            background: rgba(255, 106, 0, 0.09);
            color: var(--text);
        }

        .progress-track {
            position: relative;
            width: min(260px, 38vw);
            height: 1px;
            background: var(--line);
            overflow: hidden;
        }

        .progress-fill {
            display: block;
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, var(--orange), var(--purple));
            transition: width 700ms ease;
        }

        .empty-state {
            width: min(720px, 100%);
            border: 1px solid var(--line);
            padding: clamp(24px, 6vw, 58px);
            background: var(--panel);
        }

        .empty-state h1 {
            margin: 0 0 16px;
            font-size: clamp(2rem, 6vw, 4rem);
            line-height: 0.95;
            text-transform: uppercase;
        }

        .empty-state p {
            margin: 0 0 24px;
            color: var(--muted);
            line-height: 1.6;
        }

        @media (max-width: 700px) {
            body { overflow-y: auto; }
            .passage-shell {
                width: min(100vw - 22px, 1040px);
                min-height: 100svh;
            }

            .passage-top,
            .passage-bottom {
                align-items: flex-start;
                flex-direction: column;
            }

            .depth-switch {
                justify-content: flex-start;
            }

            .passage-card {
                min-height: 560px;
            }

            .progress-track {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            body::after {
                animation: none;
            }

            .passage-step {
                transition-duration: 1ms;
                transform: none;
            }

            .passage-step.is-exiting {
                transform: none;
            }
        }

        @keyframes passage-field-drift {
            from {
                opacity: 0.24;
                transform: perspective(900px) rotateX(58deg) translateY(16vh) scale(1.32);
            }

            to {
                opacity: 0.38;
                transform: perspective(900px) rotateX(58deg) translateY(10vh) scale(1.42);
            }
        }
    </style>
</head>
<body>
    <main class="passage-shell">
        <header class="passage-top">
            <a class="quiet-link" href="/modules/index.php">Dashboard</a>
            <nav class="depth-switch" aria-label="Passage depth">
                <a href="?depth=short" aria-current="<?= $depth === 'short' ? 'true' : 'false' ?>">Short</a>
                <a href="?depth=deep" aria-current="<?= $depth === 'deep' ? 'true' : 'false' ?>">Deep</a>
                <a href="?depth=long" aria-current="<?= $depth === 'long' ? 'true' : 'false' ?>">Long</a>
            </nav>
        </header>

        <section class="passage-stage" aria-live="polite">
            <?php if (!$passage || !$steps): ?>
                <div class="empty-state">
                    <h1>No Passage</h1>
                    <p>The corridor is empty for this depth.</p>
                    <a class="quiet-link" href="/modules/index.php">Return to Dashboard</a>
                </div>
            <?php else: ?>
                <?php foreach ($steps as $index => $step): ?>
                    <article
                        class="passage-step<?= $index === 0 ? ' is-active' : '' ?>"
                        data-step="<?= (int)$step['step_order'] ?>"
                        data-type="<?= gm_passage_h((string)$step['step_type']) ?>"
                    >
                        <div class="passage-card">
                            <div class="step-type"><?= gm_passage_h((string)$step['step_type']) ?></div>
                            <div class="step-body"><?= gm_passage_h((string)$step['body']) ?></div>
                            <button class="step-action" type="button">
                                <?= gm_passage_h((string)($step['cta_label'] ?: 'Continue')) ?>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <footer class="passage-bottom">
            <span><?= $passage ? gm_passage_h((string)$passage['title']) : 'Today&apos;s Passage' ?></span>
            <span class="progress-track" aria-hidden="true"><span class="progress-fill" id="progressFill"></span></span>
        </footer>
    </main>

    <?php if ($passage && $steps): ?>
        <script>
            const passageId = <?= (int)$passage['id'] ?>;
            const eventUrl = <?= json_encode($eventUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            const steps = Array.from(document.querySelectorAll('.passage-step'));
            const progressFill = document.getElementById('progressFill');
            let current = 0;
            let isTransitioning = false;

            function logEvent(eventType, stepOrder) {
                const form = new FormData();
                form.append('passage_id', String(passageId));
                form.append('event_type', eventType);
                if (stepOrder) {
                    form.append('step_order', String(stepOrder));
                }

                fetch(eventUrl, {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin'
                }).catch(() => {});
            }

            function updateProgress() {
                const pct = steps.length <= 1 ? 100 : (current / (steps.length - 1)) * 100;
                progressFill.style.width = `${pct}%`;
            }

            function activate(index) {
                const next = Math.max(0, Math.min(index, steps.length - 1));
                if (next === current || isTransitioning) {
                    return;
                }

                isTransitioning = true;
                const previousStep = steps[current];
                const nextStep = steps[next];

                if (previousStep) {
                    previousStep.classList.remove('is-active');
                    previousStep.classList.add('is-exiting');
                }

                current = next;
                steps.forEach((step, stepIndex) => {
                    if (stepIndex !== current && step !== previousStep) {
                        step.classList.remove('is-active', 'is-exiting');
                    }
                });

                requestAnimationFrame(() => {
                    if (nextStep) {
                        nextStep.classList.add('is-active');
                    }
                });

                window.setTimeout(() => {
                    if (previousStep) {
                        previousStep.classList.remove('is-exiting');
                    }
                    isTransitioning = false;
                }, 1020);

                const activeStep = steps[current];
                logEvent('step', activeStep ? activeStep.dataset.step : null);
                updateProgress();
            }

            document.querySelectorAll('.step-action').forEach((button, index) => {
                button.addEventListener('click', () => {
                    if (index >= steps.length - 1) {
                        logEvent('complete', steps[index] ? steps[index].dataset.step : null);
                        window.location.href = '/modules/index.php';
                        return;
                    }

                    activate(index + 1);
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowRight' || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    const button = steps[current]?.querySelector('.step-action');
                    if (button) {
                        button.click();
                    }
                }
            });

            updateProgress();
        </script>
    <?php endif; ?>
</body>
</html>
