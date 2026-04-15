<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function gm_landing_env_value(array $keys, ?string $default = null): ?string
{
    foreach ($keys as $key) {
        $value = env($key);
        if ($value !== null && trim($value) !== '') {
            return trim($value);
        }
    }

    return $default;
}

function gm_landing_normalize_url(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        return $value;
    }

    return 'https://' . ltrim($value, '/');
}

function gm_landing_resolve_url(array $keys, string $default): string
{
    $value = gm_landing_env_value($keys);

    if ($value === null) {
        return gm_landing_normalize_url($default);
    }

    return gm_landing_normalize_url($value);
}

return [
    'brand' => gm_landing_env_value(['BRAND_NAME'], 'A Gray Mentality') ?? 'A Gray Mentality',
    'headline' => gm_landing_env_value(['LANDING_HEADLINE'], 'A Gray Mentality') ?? 'A Gray Mentality',
    'tagline' => gm_landing_env_value(['LANDING_TAGLINE'], 'Train with restraint. Build with intent.') ?? 'Train with restraint. Build with intent.',
    'intro' => gm_landing_env_value(
        ['LANDING_INTRO'],
        'A clean, intentionally quiet front door for the Gray Mentality brand. The only actions here are the account entry point and the xFit experience.'
    ) ?? 'A clean, intentionally quiet front door for the Gray Mentality brand. The only actions here are the account entry point and the xFit experience.',
    'main_url' => rtrim(gm_landing_resolve_url(['MAIN_DOMAIN_URL'], 'https://www.graymentality.ca'), '/'),
    'login_url' => gm_landing_resolve_url(
        ['LOGIN_URL'],
        rtrim(gm_landing_resolve_url(['MAIN_DOMAIN_URL'], 'https://www.graymentality.ca'), '/') . '/login'
    ),
    'xfit_url' => rtrim(gm_landing_resolve_url(
        ['XFIT_URL', 'SUBDOMAIN_URL', 'SUBDOMAIN_URL'],
        'https://xfit.graymentality.ca'
    ), '/'),
    'panel_title' => gm_landing_env_value(['PANEL_TITLE'], 'What lives inside xFit') ?? 'What lives inside xFit',
];
