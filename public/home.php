<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$config = require __DIR__ . '/../config.php';
$modules = require __DIR__ . '/../data/modules.php';

function gm_landing_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en-CA">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= gm_landing_h($config['headline']) ?></title>
  <meta name="description" content="<?= gm_landing_h($config['intro']) ?>">
  <link rel="stylesheet" href="<?= gm_public_url('/assets/styles.css') ?>">
</head>
<body>
  <div class="shell">
    <header class="topbar">
      <a class="brand" href="<?= gm_landing_h($config['main_url']) ?>" aria-label="<?= gm_landing_h($config['brand']) ?>">
        <span class="brand-kicker">Gray Mentality</span>
        <span class="brand-name"><?= gm_landing_h($config['brand']) ?></span>
      </a>

      <nav class="nav-pills" aria-label="Primary">
        <button class="pill pill-login" onclick="openModal('login-modal')">Login</button>
        <button class="pill pill-register" onclick="openModal('register-modal')">Register</button>
        <a class="pill pill-xfit" href="<?= gm_landing_h($config['xfit_url']) ?>">xFit</a>
      </nav>
    </header>

    <main>
      <section class="hero" aria-labelledby="hero-title">
        <div class="hero-copy">
          <div class="eyebrow"><?= gm_landing_h($config['headline']) ?></div>
          <h1 id="hero-title"><?= gm_landing_h($config['tagline']) ?></h1>
          <p class="lead"><?= gm_landing_h($config['intro']) ?></p>

          <div class="signal-rail" aria-label="Core signals">
            <div class="signal">
              <b>Login</b>
              <span>Account entry stays on the main Gray Mentality domain.</span>
            </div>
            <div class="signal">
              <b>xFit</b>
              <span>The training app lives on the xFit subdomain and is driven by its own source.</span>
            </div>
            <div class="signal">
              <b>Deployment</b>
              <span>This folder is self-contained so it can be hosted independently from the main application.</span>
            </div>
          </div>
        </div>

        <aside class="hero-panel" aria-label="<?= gm_landing_h($config['panel_title']) ?>">
          <div class="panel-title"><?= gm_landing_h($config['panel_title']) ?></div>
          <p class="panel-copy">
            The landing page uses the same product language as the modules: practical, concise, and focused on long-term training decisions.
          </p>
          <div class="panel-list">
            <div class="panel-row">
              <strong>Simple front door</strong>
              <span>Two actions only: sign in or jump to xFit.</span>
            </div>
            <div class="panel-row">
              <strong>Brand-aligned tone</strong>
              <span>Gray Mentality keeps the copy calm and restrained instead of promotional.</span>
            </div>
            <div class="panel-row">
              <strong>Config-driven links</strong>
              <span>The pills can point at env-driven URLs, including the subdomain variable fallback.</span>
            </div>
          </div>
        </aside>
      </section>

      <section class="section" aria-labelledby="modules-title">
        <div class="section-head">
          <h2 id="modules-title">Example module content</h2>
          <p>Derived from the current xFit module language as a landing preview.</p>
        </div>

        <div class="module-grid-wrap">
          <div class="module-grid">
            <?php foreach ($modules as $module): ?>
              <article class="module-card">
                <div class="module-label"><?= gm_landing_h($module['label']) ?></div>
                <h3><?= gm_landing_h($module['title']) ?></h3>
                <p class="module-subtitle"><?= gm_landing_h($module['subtitle']) ?></p>
                <p class="module-copy"><?= gm_landing_h($module['copy']) ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer">
      Main domain: <?= gm_landing_h($config['main_url']) ?> · xFit: <?= gm_landing_h($config['xfit_url']) ?>
    </footer>
  </div>

  <!-- Auth Modals -->
  <div id="login-modal" class="modal">
    <div class="modal-overlay" onclick="closeModal('login-modal')"></div>
    <div class="modal-content">
      <div class="modal-header">
        <h2>Login</h2>
        <button class="modal-close" onclick="closeModal('login-modal')">&times;</button>
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
        <span style="opacity:.55;">|</span>
        Don't have an account? <a href="#" onclick="switchModal('register-modal')">Register</a>
      </p>
    </div>
  </div>

  <div id="register-modal" class="modal">
    <div class="modal-overlay" onclick="closeModal('register-modal')"></div>
    <div class="modal-content">
      <div class="modal-header">
        <h2>Register</h2>
        <button class="modal-close" onclick="closeModal('register-modal')">&times;</button>
      </div>
      <form class="auth-form" action="<?= gm_public_url('/register.php') ?>" method="post">
        <div class="form-group">
          <label for="register-username">Username</label>
          <input type="text" id="register-username" name="username" required>
        </div>
        <div class="form-group">
          <label for="register-email">Email</label>
          <input type="email" id="register-email" name="email" required>
        </div>
        <div class="form-group">
          <label for="register-password">Password</label>
          <input type="password" id="register-password" name="password" required>
        </div>
        <button type="submit" class="auth-submit">Register</button>
      </form>
      <p class="modal-footer-text">Already have an account? <a href="#" onclick="switchModal('login-modal')">Login</a></p>
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
      const modals = document.querySelectorAll('.modal');
      modals.forEach(modal => modal.style.display = 'none');
      openModal(targetModalId);
    }
  </script>
</body>
</html>
