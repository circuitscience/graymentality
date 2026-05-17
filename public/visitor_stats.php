<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';

function gm_visitor_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function gm_visitor_country_name(string $countryCode): string
{
    static $countries = [
        'CA' => 'Canada',
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'AU' => 'Australia',
        'NZ' => 'New Zealand',
        'IE' => 'Ireland',
        'FR' => 'France',
        'DE' => 'Germany',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'PT' => 'Portugal',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'DK' => 'Denmark',
        'FI' => 'Finland',
        'BR' => 'Brazil',
        'MX' => 'Mexico',
        'IN' => 'India',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'CN' => 'China',
        'ZA' => 'South Africa',
    ];

    return $countries[$countryCode] ?? ($countryCode === 'LOCAL' ? 'Local Network' : 'Unknown');
}

function gm_visitor_country(): array
{
    foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_X_APPENGINE_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_X_COUNTRY_CODE'] as $header) {
        $value = strtoupper(trim((string)($_SERVER[$header] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $value) === 1 && $value !== 'XX') {
            return [$value, gm_visitor_country_name($value)];
        }
    }

    $ip = gm_visitor_client_ip();
    if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.')) {
        return ['LOCAL', 'Local Network'];
    }

    return ['UNK', 'Unknown'];
}

function gm_visitor_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $header) {
        $value = trim((string)($_SERVER[$header] ?? ''));
        if ($value === '') {
            continue;
        }

        $candidate = trim(explode(',', $value)[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

function gm_visitor_hash(): string
{
    $secret = (string)auth_env('VISITOR_HASH_SECRET', auth_env('APP_KEY', auth_env('DB_PASS', 'gray-mentality')));
    $fingerprint = implode('|', [
        gm_visitor_client_ip(),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    return hash_hmac('sha256', $fingerprint, $secret);
}

function gm_visitor_ensure_table(PDO $db): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $db->exec(
        "CREATE TABLE IF NOT EXISTS visitor_stats (
            id INT PRIMARY KEY AUTO_INCREMENT,
            visitor_hash CHAR(64) NOT NULL UNIQUE,
            country_code VARCHAR(8) NOT NULL DEFAULT 'UNK',
            country_name VARCHAR(100) NOT NULL DEFAULT 'Unknown',
            visits INT UNSIGNED NOT NULL DEFAULT 1,
            first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_visitor_stats_country (country_code),
            INDEX idx_visitor_stats_last_seen (last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function gm_visitor_record_and_load(): ?array
{
    try {
        $db = get_db_connection();
        gm_visitor_ensure_table($db);

        [$countryCode, $countryName] = gm_visitor_country();
        $visitorHash = gm_visitor_hash();

        $stmt = $db->prepare(
            "INSERT INTO visitor_stats (visitor_hash, country_code, country_name, visits, first_seen_at, last_seen_at)
             VALUES (?, ?, ?, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                country_code = VALUES(country_code),
                country_name = VALUES(country_name),
                visits = visits + 1,
                last_seen_at = NOW()"
        );
        $stmt->execute([$visitorHash, $countryCode, $countryName]);

        $stmt = $db->prepare(
            "SELECT id, country_code, country_name, visits
             FROM visitor_stats
             WHERE visitor_hash = ?
             LIMIT 1"
        );
        $stmt->execute([$visitorHash]);
        $currentVisitor = $stmt->fetch();
        if (!is_array($currentVisitor)) {
            return null;
        }

        $totalVisitors = (int)$db->query("SELECT COUNT(*) FROM visitor_stats")->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM visitor_stats
             WHERE country_code = ?"
        );
        $stmt->execute([(string)$currentVisitor['country_code']]);
        $countryVisitors = (int)$stmt->fetchColumn();

        $stmt = $db->query(
            "SELECT v.country_name,
                    v.country_code,
                    v.last_seen_at,
                    c.country_total
             FROM visitor_stats v
             JOIN (
                SELECT country_code, COUNT(*) AS country_total
                FROM visitor_stats
                GROUP BY country_code
             ) c ON c.country_code = v.country_code
             ORDER BY v.last_seen_at DESC
             LIMIT 25"
        );
        $recentVisitors = $stmt ? $stmt->fetchAll() : [];

        return [
            'current' => $currentVisitor,
            'total_visitors' => $totalVisitors,
            'country_visitors' => $countryVisitors,
            'recent_visitors' => is_array($recentVisitors) ? $recentVisitors : [],
        ];
    } catch (Throwable $e) {
        error_log('[visitor_stats] ' . $e->getMessage());
        return null;
    }
}

function gm_visitor_time_label(string $value): string
{
    try {
        $timezone = new DateTimeZone((string)auth_env('APP_TIMEZONE', 'America/Toronto'));
        $date = new DateTimeImmutable($value, $timezone);
        return $date->format('M j, Y g:i A T');
    } catch (Throwable $e) {
        return $value;
    }
}

function gm_visitor_render_widget(?array $stats): string
{
    if ($stats === null) {
        return '';
    }

    $current = is_array($stats['current'] ?? null) ? $stats['current'] : [];
    $recentVisitors = is_array($stats['recent_visitors'] ?? null) ? $stats['recent_visitors'] : [];
    $countryName = (string)($current['country_name'] ?? 'Unknown');
    $visitorNumber = (int)($current['id'] ?? 0);
    $totalVisitors = (int)($stats['total_visitors'] ?? 0);
    $countryVisitors = (int)($stats['country_visitors'] ?? 0);

    ob_start();
    ?>
    <section class="visitor-stats" aria-label="Visitor activity">
      <div class="visitor-stats__summary">
        <span>You are visitor #<?= gm_visitor_h(number_format($visitorNumber)) ?> from <?= gm_visitor_h($countryName) ?>.</span>
        <span><?= gm_visitor_h(number_format($totalVisitors)) ?> unique visitors recorded. <?= gm_visitor_h(number_format($countryVisitors)) ?> from <?= gm_visitor_h($countryName) ?>.</span>
      </div>
      <?php if ($recentVisitors !== []): ?>
        <div class="visitor-stats__recent" tabindex="0" aria-label="Recent unique visitors">
          <?php foreach ($recentVisitors as $visitor): ?>
            <div class="visitor-stats__row">
              <span><?= gm_visitor_h((string)$visitor['country_name']) ?></span>
              <time datetime="<?= gm_visitor_h((string)$visitor['last_seen_at']) ?>"><?= gm_visitor_h(gm_visitor_time_label((string)$visitor['last_seen_at'])) ?></time>
              <span><?= gm_visitor_h(number_format((int)$visitor['country_total'])) ?> total</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php
    return trim((string)ob_get_clean());
}
