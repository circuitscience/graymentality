<?php
declare(strict_types=1);
session_start();
/**
 * exFIT Modules Hub
 * -------------------------------------------------------------------------
 * FILE: /public/modules/index.php
 *
 * PURPOSE
 *  - Authenticated landing page for exFIT support modules, labs, and calculators.
 *  - Acts as a visual “explore hub” rather than a dashboard or program controller.
 *  - Designed to complement the core training program, not replace it.
 *
 * CORE UX CONCEPT
 *  - Full-screen immersive background that changes per selected module.
 *  - A translucent preview card positioned mid-left, floating over the image:
 *        • Module title
 *        • Short explanatory blurb
 *        • Contextual tags
 *        • Call-to-action button to open the module
 *  - A bottom carousel of compact module cards:
 *        • Auto-scrolls on a timed interval
 *        • Clickable to focus a specific module
 *        • Visually highlights the active module
 *
 * BEHAVIOR
 *  - Background image updates when the active module changes.
 *  - Preview card content is injected dynamically via JavaScript.
 *  - Auto-scroll cycles through modules unless interrupted by user input.
 *  - Left/right arrows allow manual navigation through the carousel.
 *
 * ACCESS CONTROL
 *  - Requires an authenticated user session.
 *  - Redirects unauthenticated users to the login page.
 *
 * DATA STRUCTURE
 *  - Module definitions are maintained in a single JS array:
 *        • id
 *        • title / subtitle
 *        • module URL
 *        • background image
 *        • descriptive preview content
 *        • category tags
 *  - No database reads are required for module metadata.
 *
 * DESIGN INTENT
 *  - Minimal UI chrome.
 *  - Emphasis on clarity, calm, and long-term orientation.
 *  - Avoids “app dashboard” noise and feature overload.
 *  - Supports ageing athletes, compliance-driven training, and education.
 *
 * FILE NOTES
 *  - This page does NOT modify training state or workout progression.
 *  - Modules are auxiliary tools; the training program remains the scoreboard.
 *  - Styling is intentionally image-forward with restrained motion.
 *
 * ASSETS
 *  - Background images are resolved relative to /public/modules/assets/
 *  - CSS variable --modules-bg-image controls the active hero background.
 *
 * MAINTENANCE
 *  - To add a new module:
 *        • Append an entry to `modulesMenuItems[]`
 *        • Provide a background image and preview text
 *  - To adjust pacing:
 *        • Modify the auto-scroll interval in startModulesAutoScroll()
 */

// From /public/modules → up twice → /config/config.php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userName = $_SESSION['username'] ?? 'exFIT member';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>exFIT Modules, Labs & Calculators</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --modules-bg-image: url('assets/dark_couple.png');
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background: #000;
            color: #f5f5f5;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .modules-shell {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        /* Full-screen background image */
        .modules-bg {
            position: absolute;
            inset: 0;
            background-image: var(--modules-bg-image);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(3px) brightness(0.7);
            transform: none;
            z-index: 0;
        }

        /* Foreground content */
        .modules-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* ===== Header ===== */
        .modules-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(to bottom, rgba(0,0,0,0.8), rgba(0,0,0,0.35));
            backdrop-filter: blur(12px);
        }

        .modules-title-group {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }

        .modules-title {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .modules-subtitle {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .back-btn {
            border: none;
            background: rgba(0,0,0,0.4);
            color: #f5f5f5;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .back-btn:hover {
            background: rgba(0,0,0,0.75);
            transform: translateY(-1px);
        }

        /* ===== Main content (smaller translucent card) ===== */
        .modules-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-start;     /* LEFT */
            padding: 1.2rem 1.2rem 0.4rem;   /* a bit more side padding */
        }


        .modules-preview-card {
            width: 100%;
            max-width: 560px;           /* smaller so background shows */
            min-height: 220px;
            border-radius: 1.2rem;

            /* transparent + no borders */
            border: none;
            background: rgba(0,0,0,0.18);
            backdrop-filter: blur(24px);

            padding: 1.05rem 1.05rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;

            /* softer shadow (don’t crush the photo) */
            box-shadow:
                0 18px 40px rgba(0,0,0,0.55);
        }

        .modules-preview-card,
        .modules-preview-card * {
                text-shadow: 0 2px 14px rgba(0,0,0,0.75);
                margin-left: clamp(0rem, 2vw, 1.2rem);
            }

            .modules-preview-header p,
            .modules-preview-body {
                color: rgba(245,245,245,0.88);
            }

        .modules-preview-header {
            margin-bottom: 0.6rem;
        }

        .modules-preview-header h2 {
            font-size: 1.25rem;
            margin: 0 0 0.25rem;
        }

        .modules-preview-header p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.86;
        }

        .modules-preview-body {
            font-size: 0.83rem;
            opacity: 0.95;
            margin-top: 0.5rem;
        }

        .modules-preview-meta {
            margin-top: 0.9rem;
            font-size: 0.8rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            opacity: 0.9;
        }

        .modules-tag {
            padding: 0.18rem 0.65rem;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.35);
            font-size: 0.7rem;
            background: rgba(0,0,0,0.5);
        }

        .modules-preview-cta {
            margin-top: 0.9rem;
            display: flex;
            justify-content: flex-end;
        }

        .modules-open-btn {
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
            transition:
                transform 0.15s ease,
                box-shadow 0.15s ease,
                filter 0.15s ease;
        }

        .modules-open-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 30px rgba(255,122,26,0.7);
            filter: brightness(1.05);
        }

        /* ===== Bottom carousel ===== */
        .bottom-carousel {
            position: relative;
            padding: 0.5rem 0.75rem 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(to top, rgba(0,0,0,0.95), rgba(0,0,0,0.5));
            border-top: 1px solid rgba(255,255,255,0.12);
        }

        .nav-arrow {
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 999px;
            background: rgba(0,0,0,0.6);
            color: #f5f5f5;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: transform 0.2s ease, background 0.2s ease, opacity 0.2s ease;
        }

        .nav-arrow:hover {
            transform: scale(1.08);
            background: rgba(0,0,0,0.9);
        }

        .nav-arrow.disabled {
            opacity: 0.3;
            cursor: default;
            pointer-events: none;
        }

        .card-track-mask {
            overflow: hidden;
            flex: 1;
        }

        .card-track {
            display: flex;
            gap: 0.6rem;
            transition: transform 0.35s ease-out;
        }

        .mini-card {
            min-width: 130px;
            max-width: 130px;
            padding: 0.6rem 0.7rem;
            border-radius: 0.75rem;
            background: rgba(15,15,15,0.8);
            border: 1px solid rgba(255,255,255,0.18);
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            opacity: 0.75;
            transform: scale(0.96);
            transition:
                transform 0.2s ease,
                opacity 0.2s ease,
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .mini-card.active {
            opacity: 1;
            transform: scale(1.05);
            border-color: #ff7a1a;
            box-shadow: 0 0 18px rgba(255,122,26,0.7);
            background: radial-gradient(circle at top left, rgba(255,122,26,0.4), rgba(15,15,15,0.85));
        }

        .mini-card-title {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .mini-card-subtitle {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        @media (min-width: 768px) {
            .modules-preview-card {
                padding: 1.6rem 1.5rem;
                min-height: 260px;
            }
        }
    </style>
</head>
<body>

<div class="modules-shell">
    <div class="modules-bg"></div>

    <div class="modules-content">
        <!-- Header -->
        <header class="modules-header">
            <div class="modules-title-group">
                <div class="modules-title">Modules, Labs & Calculators</div>
                <div class="modules-subtitle">
                    Hi <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?> — explore your numbers & potential.
                </div>
            </div>

            <button class="back-btn" type="button" onclick="goBackToDashboard()">
                ‹ Dashboard
            </button>
        </header>

        <!-- Main content / preview card -->
        <main class="modules-main">
            <section class="modules-preview-card" id="modules-preview">
                <!-- JS injects preview for active module here -->
            </section>
        </main>

        <!-- Bottom floating carousel -->
        <div class="bottom-carousel">
            <button class="nav-arrow left" id="carousel-prev" aria-label="Previous">
                ‹
            </button>

            <div class="card-track-mask">
                <div class="card-track" id="card-track">
                    <!-- JS injects mini-cards here -->
                </div>
            </div>

            <button class="nav-arrow right" id="carousel-next" aria-label="Next">
                ›
            </button>
        </div>
    </div>
</div>

<script>
/**
 * Each module now has:
 * - url: relative to /public/modules/
 * - bg: background image relative to /public/modules/
 */
const modulesMenuItems = [
    {
        id: 'frame_potential',
        title: 'Frame Potential',
        subtitle: 'Genetic muscle ceiling',
        url: 'frame_potential/index.php',
        bg: 'assets/frame_potential4.png',
        tags: ['muscle potential', 'anthropometrics'],
        preview: {
            title: 'Frame Potential Lab',
            intro: 'Estimate your lifetime muscular potential based on height, wrist, and ankle size.',
            body: `
                This lab uses frame-size based models to estimate a realistic upper bound for
                lean body mass and strength. It helps keep expectations grounded and comparison
                to others in a healthier perspective.
            `
        }
    },
    {
        id: 'bmr',
        title: 'BMR Calculator',
        subtitle: 'Daily energy baseline',
        url: 'bmr/index.php',
        bg: 'assets/bmr4.png',
        tags: ['metabolism', 'calories'],
        preview: {
            title: 'BMR & Daily Energy Lab',
            intro: 'Calculate your Basal Metabolic Rate and estimated daily calories based on activity.',
            body: `
                Use this module to find your baseline maintenance intake, then build weight loss
                or gain strategies on top without guessing. Includes logging support in the BMR logs.
            `
        }
    },
    {
        id: 'weight_loss',
        title: 'Weight & Trend',
        subtitle: 'Loss tracking & trajectory',
        url: 'weight_loss/index.php',
        bg: 'assets/weight_loss5.png',
        tags: ['weight', 'trend', 'compliance'],
        preview: {
            title: 'Weight Loss & Trajectory Lab',
            intro: 'Track your weight and see how consistent trends reflect your long-term habits.',
            body: `
                This module lets you record weight, view trends, and connect changes to behavior
                rather than day-to-day noise. Perfect partner to the BMR and nutrition labs.
            `
        }
    },
    {
        id: 'protein_intake',
        title: 'Protein Needs',
        subtitle: 'Intake & guidance',
        url: 'protein_intake/index.php',
        bg: 'assets/protein5.png',
        tags: ['protein', 'nutrition'],
        preview: {
            title: 'Protein Intake Lab',
            intro: 'Figure out how much protein you need for muscle retention and growth.',
            body: `
                We translate guidelines into grams per day and help you think in terms of actual foods.
                Great for older trainees prioritizing muscle and metabolic health.
            `
        }
    },
    {
        id: 'creatine',
        title: 'Creatine',
        subtitle: 'Dosing & logging',
        url: 'creatine/index.php',
        bg: 'assets/creatine5.png',
        tags: ['supplements', 'creatine'],
        preview: {
            title: 'Creatine & Supplement Tracking',
            intro: 'Track your creatine usage and understand how it supports strength and performance.',
            body: `
                Simple logging plus education around dosing, timing, and safety — with an emphasis on
                long-term habits rather than quick fixes.
            `
        }
    },
    {
        id: 'hydration',
        title: 'Hydration',
        subtitle: 'Fluid intake lab',
        url: 'hydration/index.php',
        bg: 'assets/hydration1.png',
        tags: ['hydration', 'health'],
        preview: {
            title: 'Hydration Lab',
            intro: 'Estimate your daily fluid needs and log hydration over time.',
            body: `
                Hydration matters more as we age. This module helps you connect intake, exercise,
                and recovery, especially for heavy training days and hot environments.
            `
        }
    },
    {
        id: 'sleep_recovery',
        title: 'Sleep & Recovery',
        subtitle: 'Rest tracking',
        url: 'sleep_recovery/index.php',
        bg: 'assets/recovery6.png',
        tags: ['sleep', 'recovery'],
        preview: {
            title: 'Sleep & Recovery Lab',
            intro: 'Track your sleep and recovery habits alongside your training.',
            body: `
                Consistent resistance training without enough recovery is a dead end. This module helps
                you monitor sleep, stress, and overall readiness in a simple, non-obsessive way.
            `
        }
    },
    {
        id: 'muscle_growth',
        title: 'Muscle Growth',
        subtitle: 'Logging & notes',
        url: 'muscle_growth/index.php',
        bg: 'assets/muscle_growth6.png',
        tags: ['hypertrophy', 'logging'],
        preview: {
            title: 'Muscle Growth Log',
            intro: 'Connect your training, nutrition, and recovery logs to how your body is changing.',
            body: `
                Use this area to keep periodic notes on progress, photos, and measurements so you can see
                the compound effect of your consistency rather than chasing daily extremes.
            `
        }
    },
    {
        id: 'grip_strength',
        title: 'Grip Strength',
        subtitle: 'Ageing & strength',
        url: 'grip_strength/index.php',
        bg: 'assets/grip2.png',
        tags: ['strength', 'ageing'],
        preview: {
            title: 'Grip Strength Lab',
            intro: 'Measure and categorize your grip strength versus age and sex norms.',
            body: `
                Grip strength is strongly associated with healthy ageing and independence. Track it
                as a simple proxy for overall strength and function as you move through the program.
            `
        }
    },
    {
        id: 'learning_hub',
        title: 'Learning Hub',
        subtitle: 'RIR, sleep, protein…',
        url: 'Library/learning_hub.php',
        bg: 'assets/dark_couple.png',
        tags: ['education', 'glossary'],
        preview: {
            title: 'Learning Hub & Glossary',
            intro: 'Deep dives and simple explanations of the concepts used across exFIT.',
            body: `
                Use this hub to look up RIR, tempo, rest intervals, progression rules, and research-backed
                notes on nutrition, sleep, and ageing. It’s your “why” library.
            `
        }
    }
];

let activeModuleIndex = 0;
let autoScrollTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    initModulesCarousel();
});

/** Render cards, bind events, set initial active module */
function initModulesCarousel() {
    const track   = document.getElementById('card-track');
    const prevBtn = document.getElementById('carousel-prev');
    const nextBtn = document.getElementById('carousel-next');

    if (!track || !prevBtn || !nextBtn) return;

    // Render mini cards
    track.innerHTML = '';
    modulesMenuItems.forEach((mod, index) => {
        const card = document.createElement('div');
        card.className = 'mini-card';
        card.dataset.index = index.toString();

        card.innerHTML = `
            <div class="mini-card-title">${mod.title}</div>
            <div class="mini-card-subtitle">${mod.subtitle}</div>
        `;

        card.addEventListener('click', () => {
            setActiveModule(index, true);
        });

        track.appendChild(card);
    });

    prevBtn.addEventListener('click', () => {
        setActiveModule(activeModuleIndex - 1, true);
    });

    nextBtn.addEventListener('click', () => {
        setActiveModule(activeModuleIndex + 1, true);
    });

    // Initial state
    setActiveModule(0, false);
    startModulesAutoScroll();
}

/** Update active module: card UI + preview content + centering + arrows + background */
function setActiveModule(index, fromUser) {
    const track   = document.getElementById('card-track');
    const preview = document.getElementById('modules-preview');
    const prevBtn = document.getElementById('carousel-prev');
    const nextBtn = document.getElementById('carousel-next');
    const cards   = track ? track.querySelectorAll('.mini-card') : [];

    if (!track || !preview || cards.length === 0) return;

    // Clamp index
    if (index < 0) index = 0;
    if (index >= modulesMenuItems.length) index = modulesMenuItems.length - 1;

    activeModuleIndex = index;

    // Active card class
    cards.forEach((card, i) => {
        card.classList.toggle('active', i === activeModuleIndex);
    });

    // Center active card visually
    const mask = document.querySelector('.card-track-mask');
    const activeCard = cards[activeModuleIndex];

    if (mask && activeCard) {
        const maskRect  = mask.getBoundingClientRect();
        const cardRect  = activeCard.getBoundingClientRect();
        const maskCenter = maskRect.left + maskRect.width / 2;
        const cardCenter = cardRect.left + cardRect.width / 2;
        const currentTransform = getCurrentTranslateX(track);
        const offset = maskCenter - cardCenter;
        const nextTransform = currentTransform + offset;

        track.style.transform = `translateX(${nextTransform}px)`;
    }

    // Preview content
    const mod = modulesMenuItems[activeModuleIndex];
    preview.innerHTML = renderModulePreview(mod);

    // Update background image
    const bgUrl = mod.bg || 'assets/dark_couple.png';
    document.documentElement.style.setProperty('--modules-bg-image', `url('${bgUrl}')`);

    // Arrow states
    if (prevBtn) {
    prevBtn.disabled = (activeModuleIndex === 0);
    }
    if (nextBtn) {
        prevBtn.disabled = (activeModuleIndex === 0);
    }
    if (nextBtn) {
        nextBtn.classList.toggle('disabled', activeModuleIndex === modulesMenuItems.length - 1);
        nextBtn.disabled = (activeModuleIndex === modulesMenuItems.length - 1);
    }

    // Reset auto-scroll timer if user moved
    if (fromUser) {
        restartModulesAutoScroll();
    }
}

/** Build the main preview HTML for a module */
function renderModulePreview(mod) {
    const tagsHTML = (mod.tags || [])
        .map(tag => `<span class="modules-tag">${tag}</span>`)
        .join('');

    return `
        <div class="modules-preview-header">
            <h2>${mod.preview.title}</h2>
            <p>${mod.preview.intro}</p>
        </div>
        <div class="modules-preview-body">
            ${mod.preview.body}
        </div>
        <div class="modules-preview-meta">
            ${tagsHTML}
        </div>
        <div class="modules-preview-cta">
            <button class="modules-open-btn" type="button" onclick="openModule('${mod.id}')">
                Open module <span>→</span>
            </button>
        </div>
    `;
}

/** Read current translateX from computed transform */
function getCurrentTranslateX(el) {
    const style = window.getComputedStyle(el);
    const transform = style.transform;

    if (!transform || transform === 'none') return 0;

    const matrix = new DOMMatrixReadOnly(transform);
    return matrix.m41; // translateX
}

/** Auto-scroll: rotate active module every few seconds */
function startModulesAutoScroll() {
    stopModulesAutoScroll();
    autoScrollTimer = setInterval(() => {
        const nextIndex = (activeModuleIndex + 1) % modulesMenuItems.length;
        setActiveModule(nextIndex, false);
    }, 6000); // 6s per module
}

function stopModulesAutoScroll() {
    if (autoScrollTimer) {
        clearInterval(autoScrollTimer);
        autoScrollTimer = null;
    }
}

function restartModulesAutoScroll() {
    stopModulesAutoScroll();
    startModulesAutoScroll();
}

/** Open selected module */
function openModule(moduleId) {
    const mod = modulesMenuItems.find(item => item.id === moduleId);
    if (!mod) return;

    // e.g. 'bmr/index.php' → /public/modules/bmr/index.php
    window.location.href = mod.url;
}

/** Back to dashboard (relative path from /public/modules) */
function goBackToDashboard() {
    window.location.href = '/user_dashboard/index.php';
}
</script>

</body>
</html>