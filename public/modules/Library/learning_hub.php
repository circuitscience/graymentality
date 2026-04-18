<?php
// public/learning_hub.php
// exFIT Learning & Tracker Hub – Tabs or Accordion view on demand.

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>exFIT – Learning & Tracker Hub</title>

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    .tab-content {
      transition: max-height 0.25s ease, opacity 0.25s ease;
      overflow: hidden;
    }

    /* Base style for nav tabs (so global button styles can't override) */
    .tab-btn {
      background-color: transparent !important;
      color: #e5e7eb;           /* text-gray-200 */
      border-color: #374151;    /* border-gray-700 */
    }
    .tab-btn.active-tab-btn {
      background-color: #f97316 !important; /* bg-orange-500 */
      color: #000000;                       /* black text */
      border-color: #fb923c;               /* lighter orange */
    }

    /* TABS VIEW */
    body.view-tabs #tabBar {
      display: flex;
    }
    body.view-tabs .module-header {
      display: none;
    }
    body.view-tabs .tab-content {
      display: none;
      max-height: none;
      opacity: 1;
      overflow: visible;
    }
    body.view-tabs .tab-content.active-tab {
      display: block;
    }

    /* ACCORDION VIEW */
    body.view-accordion #tabBar {
      display: none;
    }
    body.view-accordion .module-header {
      display: flex;
    }
    body.view-accordion .tab-content {
      display: block;
    }
    body.view-accordion .tab-content:not(.open) {
      max-height: 0;
      opacity: 0;
      padding-top: 0 !important;
      padding-bottom: 0 !important;
    }
    body.view-accordion .tab-content.open {
      max-height: 2000px;
      opacity: 1;
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 min-h-screen view-tabs">
  <!-- HEADER -->
  <header class="p-6 text-center border-b border-gray-800 bg-gray-950/80">
    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">
      <span class="text-orange-400">exFIT</span>
      <span class="text-purple-400"> Learning &amp; Tracker Hub</span>
    </h1>
    <p class="mt-2 text-sm text-gray-300">
      All your FYIs &amp; trackers in one place. Switch between Tabs or Accordion view.
    </p>
  </header>

  <!-- VIEW SWITCHER -->
  <section class="flex items-center justify-center gap-3 p-4 border-b border-gray-800 bg-gray-950/60">
    <span class="text-xs md:text-sm text-gray-300">Interface style:</span>
    <div class="inline-flex rounded-full bg-gray-800 p-1">
      <button
        id="btnViewTabs"
        class="px-3 py-1 text-xs md:text-sm rounded-full bg-orange-500 text-black border border-gray-700 font-semibold"
        type="button"
        data-view="tabs">
        Tabs
      </button>
      <button
        id="btnViewAccordion"
        class="px-3 py-1 text-xs md:text-sm rounded-full bg-transparent text-gray-200 border border-gray-700 font-semibold"
        type="button"
        data-view="accordion">
        Accordion
      </button>
    </div>
  </section>

  <!-- TAB BAR (Tabs view only) -->
  <nav
    id="tabBar"
    class="flex flex-wrap justify-center gap-2 p-4 border-b border-gray-800 bg-gray-950/70 sticky top-0 z-10">
    <button class="tab-btn px-3 py-1 rounded-full text-xs md:text-sm border" data-tab="protein">
      Protein Intake
    </button>
    <button class="tab-btn px-3 py-1 rounded-full text-xs md:text-sm border" data-tab="creatine">
      Creatine
    </button>
    <button class="tab-btn px-3 py-1 rounded-full text-xs md:text-sm border" data-tab="grip">
      Grip Strength
    </button>
    <button class="tab-btn px-3 py-1 rounded-full text-xs md:text-sm border" data-tab="hydration">
      Hydration
    </button>
    <button class="tab-btn px-3 py-1 rounded-full text-xs md:text-sm border" data-tab="muscle">
      Muscle Growth
    </button>
    <button class="tab-btn px-3 py-1 rounded-full text-xs md:text-sm border" data-tab="weight">
      Weight Loss
    </button>
  </nav>

  <!-- MAIN CONTENT -->
  <main class="p-4 md:p-6 max-w-5xl mx-auto space-y-4">

    <!-- Protein Intake -->
    <section class="module-block bg-gray-900/70 border border-gray-800 rounded-xl">
      <button
        class="module-header w-full flex items-center justify-between px-4 py-2 text-left font-semibold text-sm md:text-base">
        <span>Protein Intake</span>
        <span class="text-gray-400 text-lg chevron">▾</span>
      </button>
      <div id="protein" class="tab-content active-tab open px-4 pb-4 pt-2">
        <?php include __DIR__ . '/../protein_intake/index.php'; ?>
      </div>
    </section>

    <!-- Creatine -->
    <section class="module-block bg-gray-900/70 border border-gray-800 rounded-xl">
      <button
        class="module-header w-full flex items-center justify-between px-4 py-2 text-left font-semibold text-sm md:text-base">
        <span>Creatine</span>
        <span class="text-gray-400 text-lg chevron">▾</span>
      </button>
      <div id="creatine" class="tab-content px-4 pb-4 pt-2">
        <?php include __DIR__ . '/../creatine/index.php'; ?>
      </div>
    </section>

    <!-- Grip Strength -->
    <section class="module-block bg-gray-900/70 border border-gray-800 rounded-xl">
      <button
        class="module-header w-full flex items-center justify-between px-4 py-2 text-left font-semibold text-sm md:text-base">
        <span>Grip Strength</span>
        <span class="text-gray-400 text-lg chevron">▾</span>
      </button>
      <div id="grip" class="tab-content px-4 pb-4 pt-2">
        <?php include __DIR__ . '/../grip_strength/embed.php'; ?>
      </div>
    </section>

    <!-- Hydration -->
    <section class="module-block bg-gray-900/70 border border-gray-800 rounded-xl">
      <button
        class="module-header w-full flex items-center justify-between px-4 py-2 text-left font-semibold text-sm md:text-base">
        <span>Hydration</span>
        <span class="text-gray-400 text-lg chevron">▾</span>
      </button>
      <div id="hydration" class="tab-content px-4 pb-4 pt-2">
        <?php include __DIR__ . '/../hydration/index.php'; ?>
      </div>
    </section>

    <!-- Muscle Growth -->
    <section class="module-block bg-gray-900/70 border border-gray-800 rounded-xl">
      <button
        class="module-header w-full flex items-center justify-between px-4 py-2 text-left font-semibold text-sm md:text-base">
        <span>Muscle Growth</span>
        <span class="text-gray-400 text-lg chevron">▾</span>
      </button>
      <div id="muscle" class="tab-content px-4 pb-4 pt-2">
        <?php include __DIR__ . '/../muscle_growth/index.php'; ?>
      </div>
    </section>

    <!-- Weight Loss -->
    <section class="module-block bg-gray-900/70 border border-gray-800 rounded-xl">
      <button
        class="module-header w-full flex items-center justify-between px-4 py-2 text-left font-semibold text-sm md:text-base">
        <span>Weight Loss</span>
        <span class="text-gray-400 text-lg chevron">▾</span>
      </button>
      <div id="weight" class="tab-content px-4 pb-4 pt-2">
        <?php include __DIR__ . '/../weight_loss/index.php'; ?>
      </div>
    </section>

  </main>

  <!-- JS -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const body       = document.body;
      const tabButtons = document.querySelectorAll('.tab-btn');
      const panels     = document.querySelectorAll('.tab-content');
      const headers    = document.querySelectorAll('.module-header');

      const btnViewTabs      = document.getElementById('btnViewTabs');
      const btnViewAccordion = document.getElementById('btnViewAccordion');

      function activateTab(tabId) {
        panels.forEach(panel => {
          const isActive = (panel.id === tabId);
          panel.classList.toggle('active-tab', isActive);
        });
        tabButtons.forEach(btn => {
          const isActive = (btn.dataset.tab === tabId);
          btn.classList.toggle('active-tab-btn', isActive);
        });
      }

      function setView(view) {
        const isTabs = (view === 'tabs');

        body.classList.toggle('view-tabs', isTabs);
        body.classList.toggle('view-accordion', !isTabs);

        // Style view toggle buttons
        btnViewTabs.classList.toggle('bg-orange-500', isTabs);
        btnViewTabs.classList.toggle('text-black', isTabs);
        btnViewTabs.classList.toggle('bg-transparent', !isTabs);
        btnViewTabs.classList.toggle('text-gray-200', !isTabs);

        btnViewAccordion.classList.toggle('bg-orange-500', !isTabs);
        btnViewAccordion.classList.toggle('text-black', !isTabs);
        btnViewAccordion.classList.toggle('bg-transparent', isTabs);
        btnViewAccordion.classList.toggle('text-gray-200', isTabs);

        // Which tab is "current"?
        let currentTab = 'protein';
        if (window.localStorage) {
          currentTab = localStorage.getItem('exfit_last_tab') || 'protein';
        }

        if (isTabs) {
          activateTab(currentTab);
        } else {
          // Accordion: open only the current tab's panel
          panels.forEach(panel => {
            panel.classList.toggle('open', panel.id === currentTab);
          });
        }

        if (window.localStorage) {
          localStorage.setItem('exfit_view_mode', view);
        }
      }

      // Tab clicks
      tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          const tabId = btn.dataset.tab;
          activateTab(tabId);
          if (window.localStorage) {
            localStorage.setItem('exfit_last_tab', tabId);
          }
        });
      });

      // Accordion header clicks
      headers.forEach(header => {
        header.addEventListener('click', () => {
          if (!body.classList.contains('view-accordion')) return;
          const panel = header.parentElement.querySelector('.tab-content');
          if (!panel) return;
          panel.classList.toggle('open');
        });
      });

      // View toggle clicks
      btnViewTabs.addEventListener('click', () => setView('tabs'));
      btnViewAccordion.addEventListener('click', () => setView('accordion'));

      // Initialize from localStorage
      let view    = 'tabs';
      let lastTab = 'protein';

      if (window.localStorage) {
        view    = localStorage.getItem('exfit_view_mode') || 'tabs';
        lastTab = localStorage.getItem('exfit_last_tab') || 'protein';
      }

      setView(view);
      activateTab(lastTab);
    });
  </script>
</body>
</html>
