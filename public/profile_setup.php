<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';

$authUser = require_auth(false);
$profile = auth_user_profile((int)$authUser['id']);
$message = '';
$messageType = '';

$dateOfBirth = (string)($profile['date_of_birth'] ?? '');
$gender = (string)($profile['gender'] ?? '');
$timezone = (string)($profile['timezone'] ?? 'America/Toronto');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dateOfBirth = trim((string)($_POST['date_of_birth'] ?? ''));
    $gender = trim((string)($_POST['gender'] ?? ''));
    $timezone = trim((string)($_POST['timezone'] ?? 'America/Toronto'));

    $result = auth_save_user_profile((int)$authUser['id'], $dateOfBirth, $gender, $timezone);
    $message = (string)$result['message'];
    $messageType = $result['success'] ? 'success' : 'error';

    if ($result['success']) {
        header('Location: /modules/index.php');
        exit;
    }
}

function gm_profile_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$timezoneOptions = [
    'America/Toronto' => 'Eastern Time',
    'America/Winnipeg' => 'Central Time',
    'America/Edmonton' => 'Mountain Time',
    'America/Vancouver' => 'Pacific Time',
    'America/Halifax' => 'Atlantic Time',
    'America/St_Johns' => 'Newfoundland Time',
];

if ($timezone !== '' && !isset($timezoneOptions[$timezone])) {
    $timezoneOptions = [$timezone => $timezone] + $timezoneOptions;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile Setup | Gray Mentality</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 32px 16px;
            color: #f4f1ea;
            background:
                radial-gradient(circle at 18% 12%, rgba(155, 92, 255, 0.22), transparent 34%),
                radial-gradient(circle at 82% 18%, rgba(255, 106, 0, 0.18), transparent 30%),
                linear-gradient(180deg, #050507, #101116 52%, #050507);
        }

        .profile-shell {
            width: min(620px, 100%);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 0;
            background: rgba(10, 12, 17, 0.92);
            box-shadow: 0 26px 80px rgba(0, 0, 0, 0.42);
            padding: clamp(24px, 5vw, 44px);
        }

        .kicker {
            margin: 0 0 12px;
            color: #ff6a00;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(2rem, 8vw, 4.4rem);
            line-height: 0.9;
            text-transform: uppercase;
        }

        .lead {
            margin: 18px 0 28px;
            color: #b7b1aa;
            font-size: 1rem;
            line-height: 1.65;
        }

        .profile-form {
            display: grid;
            gap: 18px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        label {
            color: #f4f1ea;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        input,
        select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 0;
            background: rgba(255, 255, 255, 0.055);
            color: #f4f1ea;
            padding: 13px 14px;
            font: inherit;
        }

        select option {
            color: #111318;
        }

        .field-note {
            color: #8f8a84;
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .message {
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin: 0 0 18px;
            padding: 12px 14px;
        }

        .message.error {
            border-color: rgba(255, 106, 0, 0.55);
            color: #ffb27a;
        }

        .message.success {
            border-color: rgba(155, 92, 255, 0.55);
            color: #d7c5ff;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .submit-button,
        .logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            border-radius: 0;
            padding: 0 18px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            text-decoration: none;
        }

        .submit-button {
            border: 1px solid #ff6a00;
            background: #ff6a00;
            color: #08090d;
            cursor: pointer;
        }

        .logout-link {
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #f4f1ea;
        }
    </style>
</head>
<body>
    <main class="profile-shell">
        <p class="kicker">Profile setup</p>
        <h1>Before the portal</h1>
        <p class="lead">
            Set the basic profile details the platform needs before opening the dashboard.
        </p>

        <?php if ($message !== ''): ?>
            <div class="message <?= gm_profile_h($messageType) ?>"><?= gm_profile_h($message) ?></div>
        <?php endif; ?>

        <form class="profile-form" method="post">
            <div class="field">
                <label for="date_of_birth">Date of birth</label>
                <input
                    type="date"
                    id="date_of_birth"
                    name="date_of_birth"
                    value="<?= gm_profile_h($dateOfBirth) ?>"
                    required
                >
                <div class="field-note">Used to calculate age without storing a value that goes stale.</div>
            </div>

            <div class="field">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="">Select one</option>
                    <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="non_binary" <?= $gender === 'non_binary' ? 'selected' : '' ?>>Non-binary</option>
                    <option value="prefer_not_to_say" <?= $gender === 'prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
                </select>
            </div>

            <div class="field">
                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone">
                    <?php foreach ($timezoneOptions as $value => $label): ?>
                        <option value="<?= gm_profile_h($value) ?>" <?= $timezone === $value ? 'selected' : '' ?>>
                            <?= gm_profile_h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="actions">
                <button class="submit-button" type="submit">Save Profile</button>
                <a class="logout-link" href="/logout.php">Logout</a>
            </div>
        </form>
    </main>
</body>
</html>
