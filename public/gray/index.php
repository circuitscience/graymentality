<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth_functions.php';
require_once __DIR__ . '/functions.php';
$authUser = require_auth();
header('Cache-Control: private, no-store');
$userId = (int)$authUser['id'];
$profile = auth_user_profile($userId);
$timezone = gray_timezone($profile['timezone'] ?? null);
$now = new DateTimeImmutable('now', $timezone);
$today = $now->format('Y-m-d');
$weekStart = $now->modify('monday this week')->format('Y-m-d');
$nextWeek = $now->modify('monday next week')->format('Y-m-d');
$view = $_GET['view'] ?? 'today';
if (!is_string($view) || !in_array($view, ['today', 'week', 'history'], true)) { $view = 'today'; }
$_SESSION['gray_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['gray_csrf'];
$error = ''; $ready = false; $entry = []; $review = []; $history = []; $reviews = []; $summary = [];
$content = gray_content($today);
try {
    $db = get_db_connection();
    $db->exec("SET time_zone = '+00:00'");
    $entry = gray_daily($db, $userId, $today);
    if ($entry) { $content = [$entry['concept_title'], $entry['concept_text'], $entry['question_text']]; }
    $stmt = $db->prepare('SELECT * FROM gray_weekly_reviews WHERE user_id=? AND week_start=?');
    $stmt->execute([$userId, $weekStart]); $review = $stmt->fetch() ?: [];
    $ready = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!gray_csrf_valid($_POST, $csrf)) { http_response_code(403); throw new InvalidArgumentException('Your form expired. Reload the page and try again.'); }
        // Do not silently move a form opened before midnight into the following day.
        if (!isset($_POST['entry_date']) || $_POST['entry_date'] !== $today) { http_response_code(409); throw new InvalidArgumentException('The date changed. Reload before saving a new check-in.'); }
        if ($view === 'today') {
            $values = gray_validate_daily($_POST);
            gray_save_daily($db, $userId, $today, $timezone->getName(), $content, $values);
        } elseif ($view === 'week') {
            $values = [];
            foreach (['reality','body','standards','drift','decision'] as $field) { $values[$field] = gray_text($_POST, $field, 8000); }
            if (implode('', $values) === '') { throw new InvalidArgumentException('Add at least one reflection to save your week.'); }
            $stmt = $db->prepare('INSERT INTO gray_weekly_reviews (user_id, week_start, reality, body, standards, drift, decision) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE reality=VALUES(reality), body=VALUES(body), standards=VALUES(standards), drift=VALUES(drift), decision=VALUES(decision)');
            $stmt->execute(array_merge([$userId, $weekStart], array_values($values)));
        } else { http_response_code(405); throw new InvalidArgumentException('History is read-only.'); }
        header('Location: /gray?view=' . $view . '&saved=1', true, 303); exit;
    }
    if ($view === 'history') {
        $stmt = $db->prepare('SELECT * FROM gray_daily_entries WHERE user_id=? ORDER BY entry_date DESC LIMIT 90');
        $stmt->execute([$userId]); $history = $stmt->fetchAll();
        $stmt = $db->prepare('SELECT * FROM gray_weekly_reviews WHERE user_id=? ORDER BY week_start DESC LIMIT 52');
        $stmt->execute([$userId]); $reviews = $stmt->fetchAll();
    }
    $stmt = $db->prepare('SELECT outcome, COUNT(*) AS total FROM gray_daily_entries WHERE user_id=? AND entry_date>=? AND entry_date<? GROUP BY outcome');
    $stmt->execute([$userId, $weekStart, $nextWeek]);
    foreach ($stmt->fetchAll() as $row) { $summary[$row['outcome'] ?? 'unrecorded'] = (int)$row['total']; }
} catch (InvalidArgumentException $e) {
    $error = $e->getMessage();
    if (http_response_code() === 200) { http_response_code(422); }
    if ($view === 'today') { $entry = array_merge($entry, array_filter($_POST, 'is_string')); }
    if ($view === 'week') { $review = array_merge($review, array_filter($_POST, 'is_string')); }
} catch (Throwable $e) {
    error_log('[gray.today] ' . $e->getMessage());
    http_response_code(503); $ready = false;
    $error = 'Your record is temporarily unavailable. Please try again later.';
}
$labels = ['done'=>'Done','partly'=>'Partly','not_done'=>'Not done','changed'=>'Changed deliberately'];
function gray_textarea(string $name, string $label, array $record): void { ?>
<label for="<?= gray_escape($name) ?>"><?= gray_escape($label) ?></label>
<textarea id="<?= gray_escape($name) ?>" name="<?= gray_escape($name) ?>" maxlength="8000" rows="3"><?= gray_escape($record[$name] ?? '') ?></textarea>
<?php }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gray <?= ucfirst($view) ?> — Gray Mentality</title><link rel="stylesheet" href="/gray/styles.css"></head><body>
<main><header><a class="brand" href="/">GRAY MENTALITY</a><h1><?= $view === 'today' ? 'Today' : ($view === 'week' ? 'Your week' : 'Your history') ?></h1><p><?= gray_escape($now->format('l, F j, Y')) ?> · <?= gray_escape($timezone->getName()) ?></p></header>
<nav aria-label="Your Gray record"><?php foreach (['today'=>'Today','week'=>'Week','history'=>'History'] as $key=>$label): ?><a href="/gray?view=<?= $key ?>" <?= $view === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a><?php endforeach; ?><a href="/modules">Tools</a><a href="/logout">Log out</a></nav>
<?php if ($error): ?><p role="alert" class="notice"><?= gray_escape($error) ?></p><?php endif; ?>
<?php if ($ready && isset($_GET['saved']) && !$error): ?><p role="status" class="notice">Your <?= $view === 'week' ? 'weekly review' : 'check-in' ?> is saved.</p><?php endif; ?>
<?php if ($ready && $view === 'today'): ?>
<section><p class="eyebrow">Today’s concept · <?= gray_escape($content[0]) ?></p><p><?= gray_escape($content[1]) ?></p></section>
<form method="post" action="/gray?view=today"><input type="hidden" name="csrf" value="<?= gray_escape($csrf) ?>"><input type="hidden" name="entry_date" value="<?= $today ?>">
<div class="grid"><section><h2>Reality first</h2><?php gray_textarea('reality','What is actually true today? Optional.', $entry); ?><details><summary>Today’s question</summary><p><?= gray_escape($content[2]) ?></p><?php gray_textarea('question_answer','Your private answer — optional', $entry); ?></details></section>
<section><h2>One correct action</h2><label for="action">What will you do next?</label><input id="action" name="action" required maxlength="500" value="<?= gray_escape($entry['action'] ?? '') ?>" placeholder="For example: walk for 15 minutes"><p>Make it specific enough to know when it is done.</p></section>
<section><h2>Body</h2><label for="body_choice">What does your body need today?</label><select id="body_choice" name="body_choice" required><option value="">Choose</option><?php foreach (['train'=>'Train','move'=>'Walk / move','recover'=>'Recover'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($entry['body_choice'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></section>
<section><h2>Drift check</h2><fieldset><legend>How are things moving today?</legend><?php foreach (['forward'=>'Forward','maintaining'=>'Maintaining','drifting'=>'Drifting'] as $key=>$label): ?><label class="choice"><input type="radio" name="drift_status" value="<?= $key ?>" required <?= ($entry['drift_status'] ?? '') === $key ? 'checked' : '' ?>> <?= $label ?></label><?php endforeach; ?></fieldset></section>
<section class="full"><h2>Finish the day</h2><label for="outcome">Did you do your one correct action?</label><select id="outcome" name="outcome"><option value="">Not recorded yet</option><?php foreach ($labels as $key=>$label): ?><option value="<?= $key ?>" <?= ($entry['outcome'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><?php gray_textarea('reflection','What changed? Optional.', $entry); ?></section></div><button type="submit">Save check-in</button><p>Your entries are private. Return later to record the outcome.</p></form>
<?php elseif ($ready && $view === 'week'): ?>
<section><h2>Week beginning <?= gray_escape($weekStart) ?></h2><p><?= array_sum($summary) ?> check-ins · <?= $summary['done'] ?? 0 ?> actions done · <?= $summary['partly'] ?? 0 ?> partly · <?= $summary['not_done'] ?? 0 ?> not done · <?= $summary['changed'] ?? 0 ?> changed deliberately · <?= $summary['unrecorded'] ?? 0 ?> outcomes not recorded</p><p>Days without a check-in are not counted as failures.</p></section>
<form method="post" action="/gray?view=week"><input type="hidden" name="csrf" value="<?= gray_escape($csrf) ?>"><input type="hidden" name="entry_date" value="<?= $today ?>"><section><h2>Your review</h2><?php foreach (['reality'=>'Reality — what changed?','body'=>'Body — what did it need?','standards'=>'Standards — what held and what slipped?','drift'=>'Drift — what needs attention?','decision'=>'Decision — what changes next week?'] as $key=>$label) { gray_textarea($key,$label,$review); } ?></section><button type="submit">Save weekly review</button></form>
<?php elseif ($ready): ?>
<h2>Recent days</h2><p>Showing up to 90 check-ins.</p><?php if (!$history): ?><section><p>Your record starts with one honest day.</p><a href="/gray">Start today</a></section><?php endif; ?>
<?php foreach ($history as $day): ?><section><h2><?= gray_escape($day['entry_date']) ?></h2><p><?= gray_escape($day['action']) ?> · <?= gray_escape($labels[$day['outcome']] ?? 'Outcome not recorded') ?></p><details><summary>Open this day</summary><p>Body: <?= gray_escape($day['body_choice']) ?> · Direction: <?= gray_escape($day['drift_status']) ?></p><?php foreach (['reality'=>'Reality','reflection'=>'Reflection','question_text'=>'Question','question_answer'=>'Your answer','concept_title'=>'Concept','concept_text'=>'Thought'] as $key=>$label): ?><?php if ($day[$key] !== ''): ?><h3><?= $label ?></h3><p class="written"><?= gray_escape($day[$key]) ?></p><?php endif; ?><?php endforeach; ?></details></section><?php endforeach; ?>
<h2>Weekly reviews</h2><p>Showing up to 52 reviews.</p><?php if (!$reviews): ?><p>No weekly reviews yet.</p><?php endif; ?><?php foreach ($reviews as $past): ?><section><details><summary>Week beginning <?= gray_escape($past['week_start']) ?></summary><?php foreach (['reality','body','standards','drift','decision'] as $key): ?><h3><?= ucfirst($key) ?></h3><p class="written"><?= gray_escape($past[$key]) ?></p><?php endforeach; ?></details></section><?php endforeach; ?>
<?php endif; ?><footer><p>Die Living.</p></footer></main></body></html>
