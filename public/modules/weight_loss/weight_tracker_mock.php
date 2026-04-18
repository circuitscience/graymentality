<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8" />
  <title>exFIT • Weight Management Tracker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full bg-slate-950 text-slate-100">

  <!-- Page wrapper -->
  <div class="min-h-screen flex flex-col">

    <!-- Header -->
    <header class="border-b border-slate-800 bg-slate-950/80 backdrop-blur sticky top-0 z-20">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <!-- exFIT Logo stub -->
          <div class="h-9 w-9 rounded-full border border-purple-400/60 flex items-center justify-center">
            <span class="text-xs font-semibold tracking-widest text-orange-400">ex</span>
          </div>
          <div>
            <h1 class="text-lg font-semibold tracking-tight">
              <span class="text-orange-400">exFIT</span> Weight Management
            </h1>
            <p class="text-xs text-slate-400">Track fat loss, protect lean mass, stay honest.</p>
          </div>
        </div>

        <!-- Small “user” stub -->
        <div class="hidden sm:flex items-center gap-3 text-xs text-slate-400">
          <div class="text-right">
            <div class="font-medium text-slate-200">Welcome back</div>
            <div>Last log: <span class="text-orange-300">Nov 12</span></div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main class="flex-1">
      <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">

        <!-- Top summary strip -->
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
          <div class="bg-slate-900/70 border border-slate-800 rounded-xl px-3 py-2.5">
            <div class="text-slate-400">Last Logged</div>
            <div class="mt-1 text-sm font-semibold text-slate-100">Nov 19</div>
          </div>
          <div class="bg-slate-900/70 border border-slate-800 rounded-xl px-3 py-2.5">
            <div class="text-slate-400">BMI</div>
            <div class="mt-1 text-sm font-semibold text-slate-100">27.4</div>
          </div>
          <div class="bg-slate-900/70 border border-slate-800 rounded-xl px-3 py-2.5">
            <div class="text-slate-400">Body Fat</div>
            <div class="mt-1 text-sm font-semibold text-slate-100">23.1%</div>
          </div>
          <div class="bg-slate-900/70 border border-slate-800 rounded-xl px-3 py-2.5">
            <div class="text-slate-400">Waist / Hip</div>
            <div class="mt-1 text-sm font-semibold">
              <span>0.92</span>
              <span class="ml-1 inline-flex items-center rounded-full bg-red-500/20 text-red-300 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">
                High risk
              </span>
            </div>
          </div>
        </section>

        <!-- Tabs / main card -->
        <section class="bg-slate-900/80 border border-slate-800 rounded-2xl shadow-lg shadow-black/40">
          <!-- Tab buttons -->
          <div class="border-b border-slate-800 flex">
            <button
              id="tab-checkin"
              class="flex-1 text-center text-xs sm:text-sm font-medium py-3 border-b-2 border-orange-400 text-orange-300">
              Today’s Check-In
            </button>
            <button
              id="tab-results"
              class="flex-1 text-center text-xs sm:text-sm font-medium py-3 border-b-2 border-transparent text-slate-400 hover:text-slate-200">
              Results & Trends
            </button>
            <button
              id="tab-education"
              class="hidden sm:flex flex-1 justify-center text-xs sm:text-sm font-medium py-3 border-b-2 border-transparent text-slate-400 hover:text-slate-200">
              Education Hub
            </button>
          </div>

          <!-- Panel: Check-in form -->
          <div id="panel-checkin" class="p-4 sm:p-6 space-y-5">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-base sm:text-lg font-semibold">📆 Today’s Check-In</h2>
              <span class="text-[11px] uppercase tracking-wide text-slate-400">
                Est. 30–60 sec
              </span>
            </div>

            <!-- Units / date row -->
            <div class="flex flex-col sm:flex-row gap-3 text-xs">
              <div class="flex-1">
                <label class="block text-slate-400 mb-1">Date</label>
                <input
                  type="date"
                  class="w-full rounded-lg bg-slate-950/80 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400" />
              </div>
              <div class="flex-1">
                <label class="block text-slate-400 mb-1">Units</label>
                <div class="inline-flex rounded-full border border-slate-700 bg-slate-950/80 overflow-hidden text-[11px]">
                  <button class="flex-1 px-3 py-1.5 bg-orange-500/20 text-orange-300 font-medium">
                    Metric
                  </button>
                  <button class="flex-1 px-3 py-1.5 text-slate-300">
                    Imperial
                  </button>
                </div>
                <p class="mt-1 text-[11px] text-slate-500">
                  Stored defaults from your profile; adjust only if needed.
                </p>
              </div>
            </div>

            <!-- Body measurements block -->
            <div class="border border-slate-800 rounded-2xl bg-slate-950/60 p-4 space-y-4">
              <div class="flex items-center justify-between gap-2">
                <div>
                  <h3 class="text-sm font-semibold">Body measurements</h3>
                  <p class="text-xs text-slate-400">
                    We’ll use these to estimate fat vs lean mass.
                  </p>
                </div>
                <span class="text-[10px] text-slate-500">
                  Pulled from profile • editable
                </span>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <!-- Weight -->
                <div>
                  <label class="block text-slate-400 mb-1">Weight</label>
                  <div class="flex rounded-lg border border-slate-700 bg-slate-950/80 overflow-hidden">
                    <input
                      type="number"
                      step="0.1"
                      placeholder="81.6"
                      class="w-full bg-transparent px-3 py-2 text-sm focus:outline-none" />
                    <span class="px-2 py-2 text-[11px] text-slate-400 border-l border-slate-700">
                      kg
                    </span>
                  </div>
                </div>

                <!-- Waist -->
                <div>
                  <label class="block text-slate-400 mb-1 flex items-center gap-1">
                    Waist
                    <span class="text-[10px] text-slate-500">at navel</span>
                  </label>
                  <div class="flex rounded-lg border border-slate-700 bg-slate-950/80 overflow-hidden">
                    <input
                      type="number"
                      step="0.1"
                      placeholder="95"
                      class="w-full bg-transparent px-3 py-2 text-sm focus:outline-none" />
                    <span class="px-2 py-2 text-[11px] text-slate-400 border-l border-slate-700">
                      cm
                    </span>
                  </div>
                </div>

                <!-- Hips -->
                <div>
                  <label class="block text-slate-400 mb-1 flex items-center gap-1">
                    Hips
                    <span class="text-[10px] text-slate-500">widest point</span>
                  </label>
                  <div class="flex rounded-lg border border-slate-700 bg-slate-950/80 overflow-hidden">
                    <input
                      type="number"
                      step="0.1"
                      placeholder="104"
                      class="w-full bg-transparent px-3 py-2 text-sm focus:outline-none" />
                    <span class="px-2 py-2 text-[11px] text-slate-400 border-l border-slate-700">
                      cm
                    </span>
                  </div>
                </div>

                <!-- Neck -->
                <div>
                  <label class="block text-slate-400 mb-1 flex items-center gap-1">
                    Neck
                    <span class="text-[10px] text-slate-500">below Adam’s apple</span>
                  </label>
                  <div class="flex rounded-lg border border-slate-700 bg-slate-950/80 overflow-hidden">
                    <input
                      type="number"
                      step="0.1"
                      placeholder="39"
                      class="w-full bg-transparent px-3 py-2 text-sm focus:outline-none" />
                    <span class="px-2 py-2 text-[11px] text-slate-400 border-l border-slate-700">
                      cm
                    </span>
                  </div>
                </div>
              </div>

              <!-- Extra measures row -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div>
                  <label class="block text-slate-400 mb-1">Chest</label>
                  <input
                    type="number"
                    step="0.1"
                    placeholder="110"
                    class="w-full rounded-lg bg-slate-950/80 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400" />
                </div>
                <div>
                  <label class="block text-slate-400 mb-1">Shoulders</label>
                  <input
                    type="number"
                    step="0.1"
                    placeholder="128"
                    class="w-full rounded-lg bg-slate-950/80 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400" />
                </div>
                <div>
                  <label class="block text-slate-400 mb-1">Thigh</label>
                  <input
                    type="number"
                    step="0.1"
                    placeholder="60"
                    class="w-full rounded-lg bg-slate-950/80 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400" />
                </div>
                <div>
                  <label class="block text-slate-400 mb-1">Calf</label>
                  <input
                    type="number"
                    step="0.1"
                    placeholder="39"
                    class="w-full rounded-lg bg-slate-950/80 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400" />
                </div>
              </div>
            </div>

            <!-- Subjective sliders / quick feelings -->
            <div class="border border-slate-800 rounded-2xl bg-slate-950/60 p-4 space-y-4">
              <div>
                <h3 class="text-sm font-semibold">How are you feeling?</h3>
                <p class="text-xs text-slate-400">
                  Quick snapshot of recovery, hunger, and mental state.
                </p>
              </div>

              <div class="space-y-3 text-xs">
                <!-- Sleep -->
                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-slate-400">Sleep quality</label>
                    <span class="text-[11px] text-slate-500">1 = awful • 10 = perfect</span>
                  </div>
                  <input type="range" min="1" max="10" value="7" class="w-full" />
                </div>

                <!-- Hunger -->
                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-slate-400">Hunger level</label>
                    <span class="text-[11px] text-slate-500">1 = no hunger • 10 = ravenous</span>
                  </div>
                  <input type="range" min="1" max="10" value="5" class="w-full" />
                </div>

                <!-- Energy -->
                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-slate-400">Energy level</label>
                    <span class="text-[11px] text-slate-500">1 = dead battery • 10 = wired</span>
                  </div>
                  <input type="range" min="1" max="10" value="6" class="w-full" />
                </div>

                <!-- Mood -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <div class="col-span-2 sm:col-span-1">
                    <label class="block text-slate-400 mb-1">Mood</label>
                    <select
                      class="w-full rounded-lg bg-slate-950/80 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400">
                      <option>Great</option>
                      <option>Okay</option>
                      <option>Low</option>
                      <option>Stressed</option>
                    </select>
                  </div>
                  <div class="col-span-2 sm:col-span-3">
                    <label class="block text-slate-400 mb-1">Notes (optional)</label>
                    <textarea
                      rows="2"
                      placeholder="Heavy week at work, slept late, cravings were bad last night..."
                      class="w-full rounded-lg bg-slate-950/80 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-orange-400 focus:border-orange-400"></textarea>
                  </div>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-3 justify-between pt-2">
              <div class="flex gap-2 text-[11px] text-slate-500">
                <span class="inline-flex items-center px-2 py-1 rounded-full border border-slate-700 bg-slate-950/80">
                  Auto-calculate BMI, body fat, lean mass
                </span>
              </div>
              <div class="flex gap-2 justify-end">
                <button
                  class="px-4 py-2 rounded-xl border border-slate-700 text-xs font-medium text-slate-200 hover:bg-slate-800">
                  Cancel
                </button>
                <button
                  class="px-4 py-2 rounded-xl bg-orange-500 text-xs font-semibold text-slate-950 hover:bg-orange-400">
                  Save Check-In
                </button>
              </div>
            </div>
          </div>

          <!-- Panel: Results (placeholder layout) -->
          <div id="panel-results" class="hidden p-4 sm:p-6 space-y-4">
            <h2 class="text-base sm:text-lg font-semibold mb-1">📈 Results & Trends</h2>
            <p class="text-xs text-slate-400 mb-3">
              After you save a few check-ins, we’ll show graphs of weight, body fat, and waist-to-hip ratio here.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
              <!-- Current metrics card -->
              <div class="border border-slate-800 rounded-2xl bg-slate-950/60 p-4 space-y-3 text-xs">
                <div class="flex items-center justify-between">
                  <h3 class="text-sm font-semibold">Today’s snapshot</h3>
                  <span class="text-[11px] text-slate-500">Nov 19 • 2025</span>
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                  <div>
                    <dt class="text-slate-400">Weight</dt>
                    <dd class="font-semibold">81.6 kg</dd>
                  </div>
                  <div>
                    <dt class="text-slate-400">BMI</dt>
                    <dd class="font-semibold">27.4</dd>
                  </div>
                  <div>
                    <dt class="text-slate-400">Lean BMI</dt>
                    <dd class="font-semibold">23.1</dd>
                  </div>
                  <div>
                    <dt class="text-slate-400">Body fat</dt>
                    <dd class="font-semibold">23.1%</dd>
                  </div>
                  <div>
                    <dt class="text-slate-400">Lean mass</dt>
                    <dd class="font-semibold">63.1 kg</dd>
                  </div>
                  <div>
                    <dt class="text-slate-400">Fat mass</dt>
                    <dd class="font-semibold">18.5 kg</dd>
                  </div>
                  <div>
                    <dt class="text-slate-400">Waist / hip</dt>
                    <dd class="font-semibold">0.92</dd>
                  </div>
                  <div>
                    <dt class="text-slate-400">Risk flag</dt>
                    <dd class="font-semibold text-red-300">High</dd>
                  </div>
                </dl>
              </div>

              <!-- Graph placeholder -->
              <div class="border border-slate-800 rounded-2xl bg-slate-950/60 p-4 text-xs flex flex-col justify-between">
                <div>
                  <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold">Weight trend (mock)</h3>
                    <span class="text-[11px] text-slate-500">Last 6 logs</span>
                  </div>
                  <div class="h-32 rounded-lg border border-dashed border-slate-700 bg-slate-900/60 flex items-center justify-center text-[11px] text-slate-500">
                    Graph placeholder (Chart.js / SVG)
                  </div>
                </div>
                <div class="mt-3 text-[11px] text-slate-400">
                  <span class="font-medium text-orange-300">Goal:</span> lose ~0.5 kg per week while keeping lean mass stable.
                </div>
              </div>
            </div>
          </div>

          <!-- Panel: Education (placeholder layout) -->
          <div id="panel-education" class="hidden p-4 sm:p-6 space-y-4">
            <h2 class="text-base sm:text-lg font-semibold mb-1">📖 Weight-loss Education Hub</h2>
            <p class="text-xs text-slate-400 mb-3">
              Short, practical lessons built for over-50 lifters. Each card can link to a full article.
            </p>

            <div class="grid gap-3 sm:grid-cols-2 text-xs">
              <article class="border border-slate-800 rounded-2xl bg-slate-950/60 p-3.5">
                <h3 class="text-sm font-semibold mb-1 text-orange-300">
                  Why fat loss after 50 is different
                </h3>
                <p class="text-slate-300 mb-2">
                  Hormones change, muscle drops, and recovery slows. The fix isn’t more punishment cardio…
                </p>
                <button class="text-[11px] text-purple-300 hover:text-purple-200">
                  Read more →
                </button>
              </article>

              <article class="border border-slate-800 rounded-2xl bg-slate-950/60 p-3.5">
                <h3 class="text-sm font-semibold mb-1 text-orange-300">
                  Hunger & cravings: re-training the signal
                </h3>
                <p class="text-slate-300 mb-2">
                  Protein, fiber, sleep, and simple routines beat “willpower” every time.
                </p>
                <button class="text-[11px] text-purple-300 hover:text-purple-200">
                  Read more →
                </button>
              </article>

              <article class="border border-slate-800 rounded-2xl bg-slate-950/60 p-3.5">
                <h3 class="text-sm font-semibold mb-1 text-orange-300">
                  Visual portion guide (no counting)
                </h3>
                <p class="text-slate-300 mb-2">
                  Palm for protein, fist for carbs, two fists of veg, thumb of fats. Done.
                </p>
                <button class="text-[11px] text-purple-300 hover:text-purple-200">
                  View guide →
                </button>
              </article>

              <article class="border border-slate-800 rounded-2xl bg-slate-950/60 p-3.5">
                <h3 class="text-sm font-semibold mb-1 text-orange-300">
                  Protecting muscle while dropping fat
                </h3>
                <p class="text-slate-300 mb-2">
                  Why heavy lifting + protein + modest deficit beats “eat less, move more” slogans.
                </p>
                <button class="text-[11px] text-purple-300 hover:text-purple-200">
                  Read more →
                </button>
              </article>
            </div>
          </div>
        </section>

        <!-- Quick summary strip -->
        <section class="mt-2">
          <div class="border border-slate-900 rounded-2xl bg-slate-950/80 px-3 py-3 sm:px-4 sm:py-3.5">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-xs sm:text-sm font-semibold text-slate-200 flex items-center gap-2">
                Daily Intake Snapshot
                <span class="hidden sm:inline-flex text-[10px] font-normal text-slate-500">
                  From your protein, creatine & hydration trackers
                </span>
              </h3>
              <span class="text-[10px] text-slate-500">
                Nutrition panel • exFIT
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 text-[11px]">
              <!-- Protein -->
              <div class="flex items-center gap-2 rounded-xl bg-slate-900/80 border border-slate-800 px-3 py-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-orange-500/10 border border-orange-500/40">
                  <span class="text-base">🥩</span>
                </div>
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <span class="font-semibold text-slate-100">Protein</span>
                    <span class="text-[10px] text-slate-500">Goal: 160 g</span>
                  </div>
                  <div class="flex items-center justify-between mt-0.5">
                    <span class="text-slate-300">Today: <span class="font-semibold text-orange-300">120 g</span></span>
                    <span class="inline-flex items-center rounded-full bg-orange-500/20 text-orange-300 px-2 py-[2px] text-[10px]">
                      75% target
                    </span>
                  </div>
                </div>
              </div>

              <!-- Hydration -->
              <div class="flex items-center gap-2 rounded-xl bg-slate-900/80 border border-slate-800 px-3 py-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-sky-500/10 border border-sky-500/40">
                  <span class="text-base">💧</span>
                </div>
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <span class="font-semibold text-slate-100">Hydration</span>
                    <span class="text-[10px] text-slate-500">Goal: 3.0 L</span>
                  </div>
                  <div class="flex items-center justify-between mt-0.5">
                    <span class="text-slate-300">Today: <span class="font-semibold text-sky-300">2.1 L</span></span>
                    <span class="inline-flex items-center rounded-full bg-sky-500/15 text-sky-300 px-2 py-[2px] text-[10px]">
                      On track
                    </span>
                  </div>
                </div>
              </div>

              <!-- Creatine -->
              <div class="flex items-center gap-2 rounded-xl bg-slate-900/80 border border-slate-800 px-3 py-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-purple-500/10 border border-purple-500/40">
                  <span class="text-base">⚡</span>
                </div>
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <span class="font-semibold text-slate-100">Creatine</span>
                    <span class="text-[10px] text-slate-500">Goal: 5 g</span>
                  </div>
                  <div class="flex items-center justify-between mt-0.5">
                    <span class="text-slate-300">Taken: <span class="font-semibold text-purple-300">Yes</span></span>
                    <button class="text-[10px] text-purple-300 hover:text-purple-200 underline-offset-2 hover:underline">
                      View log
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Link row -->
            <div class="mt-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1">
              <span class="text-[10px] text-slate-500">
                Protein, hydration and creatine modules sync here to support weight-loss decisions.
              </span>
              <div class="flex gap-2 text-[10px]">
                <button class="px-2.5 py-1 rounded-full border border-slate-700 text-slate-300 hover:bg-slate-800">
                  Open protein tracker
                </button>
                <button class="px-2.5 py-1 rounded-full border border-slate-700 text-slate-300 hover:bg-slate-800">
                  Open hydration tracker
                </button>
                <button class="px-2.5 py-1 rounded-full border border-slate-700 text-slate-300 hover:bg-slate-800">
                  Open creatine tracker
                </button>
              </div>
            </div>
          </div>
        </section>

      </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-900 bg-slate-950/90">
      <div class="max-w-5xl mx-auto px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-slate-500">
        <div>© exFIT / Gray Mentality — “Die Living.”</div>
        <div class="flex gap-3">
          <span>Weight • Protein • Creatine • Sleep • Hydration</span>
        </div>
      </div>
    </footer>
  </div>

  <!-- Tiny tab-switcher script (no framework) -->
  <script>
    const tabs = {
      'tab-checkin': 'panel-checkin',
      'tab-results': 'panel-results',
      'tab-education': 'panel-education'
    };

    function setActiveTab(activeId) {
      for (const [tabId, panelId] of Object.entries(tabs)) {
        const tab = document.getElementById(tabId);
        const panel = document.getElementById(panelId);
        if (!tab || !panel) continue;

        const isActive = (tabId === activeId);
        panel.classList.toggle('hidden', !isActive);

        tab.classList.toggle('border-orange-400', isActive);
        tab.classList.toggle('text-orange-300', isActive);
        tab.classList.toggle('border-transparent', !isActive);
        tab.classList.toggle('text-slate-400', !isActive);
      }
    }

    document.getElementById('tab-checkin')?.addEventListener('click', () => setActiveTab('tab-checkin'));
    document.getElementById('tab-results')?.addEventListener('click', () => setActiveTab('tab-results'));
    document.getElementById('tab-education')?.addEventListener('click', () => setActiveTab('tab-education'));

    // default
    setActiveTab('tab-checkin');
  </script>
</body>
</html>
