<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/public/gray/functions.php';
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$input = ['action'=>'Walk 15 minutes','body_choice'=>'recover','drift_status'=>'maintaining'];
$values = gray_validate_daily($input);
check($values['outcome'] === null, 'No outcome must remain unrecorded.');
check($values['body_choice'] === 'recover', 'Recovery must be valid.');
foreach ([['action'=>''], ['body_choice'=>'invalid'], ['drift_status'=>['forward']], ['outcome'=>'failed'], ['reflection'=>str_repeat('x',8001)]] as $invalid) {
    try { gray_validate_daily(array_merge($input,$invalid)); throw new RuntimeException('Invalid input accepted'); }
    catch (InvalidArgumentException $expected) {}
}
check(gray_csrf_valid(['csrf'=>'token'],'token'), 'Valid CSRF rejected');
check(!gray_csrf_valid(['csrf'=>['token']],'token'), 'Array CSRF accepted');
check(!gray_csrf_valid(['csrf'=>'wrong'],'token'), 'Wrong CSRF accepted');
check(gray_timezone('bad/timezone')->getName() === 'America/Toronto', 'Timezone fallback');
$local = (new DateTimeImmutable('2026-10-04T02:00:00Z'))->setTimezone(gray_timezone('America/Toronto'));
check($local->format('Y-m-d') === '2026-10-03', 'Local date must differ from UTC date');
check($local->modify('monday this week')->format('Y-m-d') === '2026-09-28', 'Week start');
check(gray_content('2026-10-03') === gray_content('2026-10-03'), 'Content unstable');
check(gray_escape('<script>') === '&lt;script&gt;', 'HTML escaping');
// Capture statements to verify ownership and parameterized writes without a live database.
class GrayStatement extends PDOStatement {
    public array $parameters = [];
    public function execute(?array $params = null): bool { $this->parameters = $params ?? []; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return false; }
}
class GrayDatabase extends PDO {
    public string $sql = ''; public GrayStatement $statement;
    public function __construct() { $this->statement = new GrayStatement(); }
    public function prepare(string $query, array $options = []): PDOStatement|false { $this->sql = $query; return $this->statement; }
}
$db = new GrayDatabase();
gray_daily($db, 42, '2026-10-03');
check(str_contains($db->sql,'WHERE user_id=? AND entry_date=?'), 'Read missing ownership filter');
check($db->statement->parameters === [42,'2026-10-03'], 'Read parameters');
gray_save_daily($db,42,'2026-10-03','America/Toronto',gray_content('2026-10-03'),$values);
check(count($db->statement->parameters) === 13, 'Write parameter count');
check($db->statement->parameters[0] === 42, 'Write ownership');
$update = explode('ON DUPLICATE KEY UPDATE',$db->sql)[1];
check(!str_contains($update,'concept_text'), 'Saved content must not change on update');
echo "Gray Today checks passed.\n";
