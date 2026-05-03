<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

function gm_legal_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$requestPath = trim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'), '/');
$pageKey = $requestPath === '' ? 'privacy' : str_replace('.php', '', $requestPath);

$pages = [
    'privacy' => [
        'title' => 'Privacy Policy',
        'intro' => 'How Gray Mentality handles personal information connected to accounts, onboarding, contact, and portal use.',
        'sections' => [
            ['Information We Collect', ['Account details you submit, contact information, login/session data, and information you choose to enter into site tools or modules.']],
            ['How We Use It', ['To provide site access, maintain account security, respond to contact requests, improve the portal, and operate related Gray Mentality services.']],
            ['Contact', ['For privacy questions, email info@graymentality.ca.']],
        ],
    ],
    'terms' => [
        'title' => 'Terms of Use',
        'intro' => 'The basic terms for using Gray Mentality, exFit, and related portal content.',
        'sections' => [
            ['Use Of The Site', ['Use the site lawfully and responsibly. Do not interfere with site operations or attempt unauthorized access.']],
            ['Content', ['Content is provided for general educational and operational purposes and is not medical, legal, or financial advice.']],
            ['Accounts', ['You are responsible for your account credentials and activity under your account.']],
        ],
    ],
    'acceptable-use' => [
        'title' => 'Acceptable Use',
        'intro' => 'Standards for using Gray Mentality systems without harming the site, other users, or the integrity of the portal.',
        'sections' => [
            ['Do Not Misuse The Platform', ['Do not upload malicious content, scrape aggressively, abuse forms, bypass authentication, or attempt to disrupt services.']],
            ['Respect The Community', ['Do not use the platform to harass, threaten, impersonate, or exploit others.']],
        ],
    ],
    'cookie-policy' => [
        'title' => 'Cookie Policy',
        'intro' => 'How cookies and similar technologies may be used on Gray Mentality.',
        'sections' => [
            ['Essential Cookies', ['The site may use essential cookies for login, session security, preferences, and core functionality.']],
            ['Analytics Or Improvements', ['If analytics are added, they should be used to understand site performance and improve the experience.']],
        ],
    ],
    'accessibility' => [
        'title' => 'Accessibility',
        'intro' => 'Gray Mentality aims to keep the portal readable, navigable, and usable across devices and assistive technologies.',
        'sections' => [
            ['Feedback', ['If you encounter an accessibility issue, email info@graymentality.ca with the page, device, browser, and issue details.']],
            ['Ongoing Work', ['Accessibility is treated as an active maintenance concern as the portal evolves.']],
        ],
    ],
    'contact' => [
        'title' => 'Contact',
        'intro' => 'Reach Gray Mentality for account, policy, privacy, accessibility, or general portal questions.',
        'sections' => [
            ['Email', ['info@graymentality.ca']],
        ],
    ],
];

$page = $pages[$pageKey] ?? $pages['privacy'];
$stylesheetVersion = (string)time();
$stylesheetPath = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR) . '/assets/styles.css';
if (is_file($stylesheetPath)) {
    $stylesheetVersion = (string)filemtime($stylesheetPath);
}
?>
<!doctype html>
<html lang="en-CA">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= gm_legal_h($page['title']) ?> | Gray Mentality</title>
  <meta name="description" content="<?= gm_legal_h($page['intro']) ?>">
  <link rel="stylesheet" href="<?= gm_public_url('/assets/styles.css') ?>?v=<?= gm_legal_h($stylesheetVersion) ?>">
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
        <span>Gray Mentality</span>
      </a>
      <nav class="header-actions" aria-label="Primary">
        <a class="nav-link" href="<?= gm_public_url('/') ?>">Home</a>
        <a class="nav-link" href="mailto:info@graymentality.ca">Contact</a>
      </nav>
    </header>

    <main class="legal-page">
      <article>
        <p class="section-kicker">Policy</p>
        <h1><?= gm_legal_h($page['title']) ?></h1>
        <p class="section-copy"><?= gm_legal_h($page['intro']) ?></p>

        <?php foreach ($page['sections'] as [$heading, $items]): ?>
          <h2><?= gm_legal_h($heading) ?></h2>
          <ul>
            <?php foreach ($items as $item): ?>
              <li>
                <?php if ($pageKey === 'contact' || str_contains($item, '@')): ?>
                  <a href="mailto:info@graymentality.ca">info@graymentality.ca</a>
                <?php else: ?>
                  <?= gm_legal_h($item) ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </article>
    </main>

    <footer class="site-footer">
      <strong>Gray Mentality</strong>
      <span>Accept reality. Act anyway.</span>
      <small class="copyright-notice">
        &copy; <?= gm_legal_h(date('Y')) ?> Gray Mentality. Contact:
        <a href="mailto:info@graymentality.ca">info@graymentality.ca</a>
      </small>
    </footer>
  </div>
</body>
</html>
