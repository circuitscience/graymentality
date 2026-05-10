<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

function gm_landing_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$stylesheetCandidates = array_filter([
    rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR) . '/assets/styles.css',
    dirname(__DIR__) . '/assets/styles.css',
    __DIR__ . '/assets/styles.css',
]);

$stylesheetVersion = (string)time();
foreach ($stylesheetCandidates as $stylesheetCandidate) {
    if (is_file($stylesheetCandidate)) {
        $stylesheetVersion = (string)filemtime($stylesheetCandidate);
        break;
    }
}
?>
<!doctype html>
<html lang="en-CA">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gray Mentality | Start Here</title>
  <meta name="description" content="A way of operating when life is not ideal. Accept reality. Refuse passive decline.">
  <link rel="stylesheet" href="<?= gm_public_url('/assets/styles.css') ?>?v=<?= gm_landing_h($stylesheetVersion) ?>">
  <style>
    body { margin: 0; min-height: 100vh; background: #050506; color: #f0f0ee; font-family: "Segoe UI", Arial, system-ui, sans-serif; }
    .page-shell { width: min(1180px, calc(100vw - 32px)); margin: 0 auto; padding: 18px 0 52px; }
    .site-header { display: flex; justify-content: space-between; gap: 18px; padding: 16px 0; border-bottom: 1px solid rgba(238,238,238,.13); }
    .brand-mark, .nav-link, .action-button { color: inherit; text-decoration: none; text-transform: uppercase; font-weight: 900; letter-spacing: .12em; }
    .hero-section, .clarity-section, .reality-section, .tenets-section, .real-life-section, .portal-section, .start-section { padding: clamp(72px, 10vw, 128px) 0; border-bottom: 1px solid rgba(238,238,238,.13); }
    .hero-section { min-height: calc(100vh - 74px); display: flex; flex-direction: column; justify-content: center; }
    .section-kicker, .decision-line { color: #ff6c14; }
    h1 { margin: 0; max-width: 12ch; font-size: clamp(3.8rem, 11vw, 9.8rem); line-height: .82; text-transform: uppercase; }
    h2 { margin: 0; font-size: clamp(2rem, 4.4vw, 4.6rem); line-height: 1; text-transform: uppercase; }
    .core-line { font-size: clamp(1.55rem, 3.4vw, 3.6rem); font-weight: 900; text-transform: uppercase; }
    .hero-copy, .section-copy, .tenet-card p { color: #b4b4b0; line-height: 1.7; }
    .action-row, .header-actions { display: flex; flex-wrap: wrap; gap: 12px; }
    .nav-link, .action-button { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; border: 1px solid rgba(238,238,238,.13); background: #0d0d10; cursor: pointer; }
    .action-button-primary { border-color: #ff6c14; background: #ff6c14; color: #090807; }
    .action-button-secondary { border-color: rgba(168,85,255,.72); background: rgba(168,85,255,.12); }
    .tenets-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border-top: 1px solid rgba(238,238,238,.13); border-left: 1px solid rgba(238,238,238,.13); }
    .tenet-card, .decision-item, .portal-card { position: relative; overflow: hidden; padding: 24px; border-right: 1px solid rgba(238,238,238,.13); border-bottom: 1px solid rgba(238,238,238,.13); background: linear-gradient(135deg, rgba(255,108,20,.08), transparent 42%), #101014; }
    .hero-graphic { position: absolute; right: 0; top: 50%; width: clamp(190px, 25vw, 320px); aspect-ratio: 1; transform: translateY(-50%); border: 2px solid rgba(255,108,20,.42); box-shadow: 0 0 32px rgba(255,108,20,.14), inset 0 0 52px rgba(168,85,255,.1); opacity: .58; pointer-events: none; z-index: 0; }
    .hero-graphic span, .section-graphic span, .card-glyph span, .portal-diagram span { position: absolute; display: block; background: linear-gradient(90deg, #ff6c14, #a855ff); box-shadow: 0 0 18px rgba(255,108,20,.42); }
    .hero-graphic span:nth-child(1) { left: 0; top: 22%; width: 70%; height: 4px; }
    .hero-graphic span:nth-child(2) { right: 0; top: 58%; width: 62%; height: 4px; }
    .hero-graphic span:nth-child(3) { left: 34%; top: 0; width: 4px; height: 76%; }
    .hero-graphic span:nth-child(4) { right: 18%; bottom: 0; width: 4px; height: 48%; }
    .section-graphic { position: absolute; right: 0; top: 42px; width: clamp(110px, 15vw, 170px); height: 92px; border: 1px solid rgba(168,85,255,.42); background: repeating-linear-gradient(135deg, rgba(255,108,20,.12) 0 7px, transparent 7px 18px); box-shadow: inset 0 0 28px rgba(168,85,255,.12); opacity: .42; pointer-events: none; z-index: 0; }
    .card-glyph { position: absolute; right: 18px; top: 18px; width: 58px; height: 58px; border: 1px solid rgba(255,108,20,.42); background: rgba(255,108,20,.06); opacity: .68; }
    .card-glyph span:nth-child(1) { left: 10px; top: 18px; width: 52px; height: 4px; }
    .card-glyph span:nth-child(2) { left: 20px; top: 37px; width: 42px; height: 4px; background: #a855ff; }
    .card-glyph span:nth-child(3) { left: 10px; top: 56px; width: 60px; height: 4px; }
    .portal-diagram { position: relative; height: 72px; margin-bottom: 20px; border: 1px solid rgba(168,85,255,.46); background: linear-gradient(135deg, rgba(168,85,255,.1), rgba(255,108,20,.06)); }
    .portal-diagram span:nth-child(1), .portal-diagram span:nth-child(2), .portal-diagram span:nth-child(3) { top: 28px; width: 28px; height: 28px; border: 2px solid #ff6c14; background: transparent; }
    .portal-diagram span:nth-child(1) { left: 18px; } .portal-diagram span:nth-child(2) { left: calc(50% - 14px); border-color: #a855ff; } .portal-diagram span:nth-child(3) { right: 18px; }
    .portal-diagram span:nth-child(4) { left: 42px; right: 42px; top: 42px; height: 3px; }
    .gm-atmosphere { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; background: #050506; }
    .page-shell { position: relative; z-index: 1; }
    .gm-atmosphere__grid { position: absolute; inset: -20%; background-image: linear-gradient(90deg, rgba(255,108,20,.08) 1px, transparent 1px), linear-gradient(180deg, rgba(168,85,255,.07) 1px, transparent 1px); background-size: 72px 72px; animation: gm-grid-drift 26s linear infinite; opacity: .7; }
    .gm-atmosphere__scan { position: absolute; inset: 0; background: linear-gradient(180deg, transparent, rgba(255,108,20,.08), transparent); animation: gm-scan 9s ease-in-out infinite; }
    .gm-atmosphere__signal { position: absolute; inset: 0; background: linear-gradient(115deg, rgba(255,108,20,.18), transparent 32%), linear-gradient(295deg, rgba(168,85,255,.16), transparent 28%); animation: gm-signal 14s ease-in-out infinite alternate; }
    @keyframes gm-grid-drift { from { transform: translate3d(0, 0, 0); } to { transform: translate3d(72px, 72px, 0); } }
    @keyframes gm-scan { 0%, 100% { transform: translateY(-55%); opacity: .1; } 45%, 55% { opacity: .75; } 100% { transform: translateY(55%); } }
    @keyframes gm-signal { from { opacity: .42; transform: scale(1); } to { opacity: .72; transform: scale(1.04); } }
    @media (max-width: 720px) { .site-header { flex-direction: column; } .tenets-grid { grid-template-columns: 1fr; } .nav-link, .action-button { width: 100%; } .hero-graphic { width: 150px; opacity: .18; right: -44px; top: 36%; } .section-graphic { width: 110px; height: 68px; opacity: .18; right: -28px; } .card-glyph { width: 46px; height: 46px; opacity: .38; } }
  </style>
</head>
<body>
  <div class="gm-atmosphere" aria-hidden="true">
    <div class="gm-atmosphere__grid"></div>
    <div class="gm-atmosphere__signal"></div>
    <div class="gm-atmosphere__scan"></div>
  </div>

  <div class="page-shell">
    <header class="site-header">
      <a class="brand-mark" href="<?= gm_public_url('/') ?>" aria-label="Gray Mentality home">
        <img class="site-logo" src="<?= gm_logo_url() ?>" alt="" aria-hidden="true">
        <span>Gray Mentality</span>
      </a>
      <nav class="header-actions" aria-label="Primary">
        <button class="nav-link nav-button" type="button" onclick="openModal('login-modal')">Login</button>
      </nav>
    </header>

    <main>
      <section class="hero-section" aria-labelledby="hero-title">
        <div class="hero-graphic" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
          <span></span>
        </div>
        <div class="hero-rule" aria-hidden="true"></div>
        <p class="section-kicker">Start Here</p>
        <h1 id="hero-title">Gray Mentality</h1>
        <p class="hero-subheadline">A way of operating when life isn&rsquo;t ideal.</p>
        <p class="core-line">Accept reality. Refuse passive decline.</p>
        <p class="hero-copy">
          Life isn&rsquo;t clean. It isn&rsquo;t fair. It doesn&rsquo;t wait.
          You don&rsquo;t need it to. You act anyway.
        </p>
        <div class="hero-signals" aria-label="Operating signals">
          <span>Reality First</span>
          <span>Drift Interrupted</span>
          <span>Action Required</span>
        </div>
        <div class="action-row">
          <a class="action-button action-button-primary" href="<?= gm_public_url('/start') ?>">Enter the Mentality</a>
          <a class="action-button action-button-secondary" href="<?= gm_public_url('/start') ?>">Start Here</a>
        </div>
      </section>
    </main>

    <footer class="site-footer">
      <strong>Gray Mentality</strong>
      <span>Accept reality. Act anyway.</span>
      <small>Built for those who refuse to quietly fade.</small>
      <nav class="footer-links" aria-label="Footer policies">
        <a href="<?= gm_public_url('/privacy') ?>">Privacy Policy</a>
        <a href="<?= gm_public_url('/terms') ?>">Terms of Use</a>
        <a href="<?= gm_public_url('/acceptable-use') ?>">Acceptable Use</a>
        <a href="<?= gm_public_url('/cookie-policy') ?>">Cookie Policy</a>
        <a href="<?= gm_public_url('/accessibility') ?>">Accessibility</a>
        <a href="<?= gm_public_url('/contact') ?>">Contact</a>
      </nav>
      <small class="copyright-notice">
        &copy; <?= gm_landing_h(date('Y')) ?> Gray Mentality. Contact:
        <a href="mailto:info@graymentality.ca">info@graymentality.ca</a>
      </small>
    </footer>
  </div>

  <div id="login-modal" class="modal">
    <div class="modal-overlay" onclick="closeModal('login-modal')"></div>
    <div class="modal-content">
      <div class="modal-header">
        <h2>Login</h2>
        <button class="modal-close" onclick="closeModal('login-modal')" aria-label="Close login modal">&times;</button>
      </div>
      <form class="auth-form" action="<?= gm_public_url('/login.php') ?>" method="post">
        <div class="form-group">
          <label for="login-email">Email</label>
          <input type="email" id="login-email" name="email" required>
        </div>
        <div class="form-group">
          <label for="login-password">Password</label>
          <input type="password" id="login-password" name="password" required>
        </div>
        <button type="submit" class="auth-submit">Login</button>
      </form>
      <p class="modal-footer-text">
        <a href="<?= gm_public_url('/reset_password.php') ?>">Forgot password?</a>
        <span>|</span>
        <a href="<?= gm_public_url('/start') ?>">Start here</a>
      </p>
    </div>
  </div>

  <script>
    function openModal(modalId) {
      document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    function switchModal(targetModalId) {
      document.querySelectorAll('.modal').forEach((modal) => {
        modal.style.display = 'none';
      });
      openModal(targetModalId);
    }

    const revealTargets = document.querySelectorAll(
      '.hero-section'
    );

    revealTargets.forEach((target) => target.classList.add('is-revealable'));

    if ('IntersectionObserver' in window) {
      const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.16 });

      revealTargets.forEach((target) => revealObserver.observe(target));
    } else {
      revealTargets.forEach((target) => target.classList.add('is-visible'));
    }
  </script>
</body>
</html>
