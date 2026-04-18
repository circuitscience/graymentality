<?php
// public/modules/grip_strength/embed.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo '<p class="text-sm text-gray-400">Please log in to use the grip strength tracker.</p>';
    return;
}

$userId = (int) $_SESSION['user_id'];

// --- Fetch user age & gender ---
$userAge = null;
$userGender = null;
if ($stmt = $conn->prepare("SELECT age, gender FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($userAge, $userGender);
    $stmt->fetch();
    $stmt->close();
}

function categorize_grip(?string $gender, ?int $age, float $avgGripLbs): string {
    // ... keep your existing categorize_grip logic here ...
}

// --- Handle POST (save log) ---
$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testDate  = $_POST['test_date']  ?? date('Y-m-d');
    $testType  = $_POST['test_type']  ?? 'dynamometer';
    $bodyLbs   = $_POST['bodyweight_lbs'] !== '' ? (float)$_POST['bodyweight_lbs'] : null;
    $bodyKg    = $bodyLbs ? $bodyLbs / 2.20462 : null;

    $gripLeft  = $_POST['grip_left_lbs']     !== '' ? (float)$_POST['grip_left_lbs'] : null;
    $gripRight = $_POST['grip_right_lbs']    !== '' ? (float)$_POST['grip_right_lbs'] : null;
    $deadHang  = $_POST['dead_hang_seconds'] !== '' ? (int)$_POST['dead_hang_seconds'] : null;
    $farmerW   = $_POST['farmer_weight_lbs'] !== '' ? (float)$_POST['farmer_weight_lbs'] : null;
    $notes     = trim($_POST['notes'] ?? '');

    $avgGrip = null;

    if ($testType === 'dynamometer' && $gripLeft && $gripRight) {
        $avgGrip = ($gripLeft + $gripRight) / 2.0;
    } elseif ($testType === 'dead_hang' && $bodyLbs && $deadHang) {
        $avgGrip = $bodyLbs * ($deadHang / 60.0);
    } elseif ($testType === 'farmer_carry' && $farmerW) {
        $avgGrip = $farmerW;
    }

    if ($avgGrip !== null && $avgGrip > 0) {
        $category = categorize_grip($userGender, $userAge, $avgGrip);

        if ($stmt = $conn->prepare("
            INSERT INTO grip_logs (
                user_id, test_date, test_type,
                bodyweight_kg, bodyweight_lbs,
                grip_left_lbs, grip_right_lbs, avg_grip_lbs,
                dead_hang_seconds, farmer_weight_lbs,
                category, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")) {
            $stmt->bind_param(
                'issddddddidss',
                $userId,
                $testDate,
                $testType,
                $bodyKg,
                $bodyLbs,
                $gripLeft,
                $gripRight,
                $avgGrip,
                $deadHang,
                $farmerW,
                $category,
                $notes
            );
            if ($stmt->execute()) {
                $feedback = 'Grip strength log saved successfully.';
            } else {
                $feedback = 'Error saving log: ' . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $feedback = 'Database error preparing statement.';
        }
    } else {
        $feedback = 'Could not calculate average grip – check your inputs.';
    }
}

// --- Fetch recent logs ---
$logs = [];
if ($stmt = $conn->prepare("
    SELECT test_date, test_type, bodyweight_lbs,
           grip_left_lbs, grip_right_lbs, avg_grip_lbs,
           dead_hang_seconds, farmer_weight_lbs, category, notes
    FROM grip_logs
    WHERE user_id = ?
    ORDER BY test_date DESC, id DESC
    LIMIT 30
")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
}

// you can also build the mini plan here (same logic as before)
?>

<div class="space-y-4">
  <!-- Wrap everything in a div so Tailwind from the hub handles layout -->

  <?php if ($feedback): ?>
    <div class="text-sm text-orange-400"><?php echo htmlspecialchars($feedback); ?></div>
  <?php endif; ?>

  <div class="grid md:grid-cols-2 gap-4">
    <!-- Left: FYI -->
    <div class="bg-gray-900/70 border border-gray-800 rounded-xl p-4">
      <h2 class="text-lg font-semibold mb-2">Grip Strength FYI</h2>
      <p class="text-sm text-gray-300">
        Grip strength is a simple, research-backed proxy for overall strength and long-term health.
      </p>
      <ul class="mt-2 text-sm text-gray-300 list-disc list-inside space-y-1">
        <li>Linked to muscle quality and coordination.</li>
        <li>Correlates with healthy aging and independence.</li>
        <li>Easy to re-test over time.</li>
      </ul>
    </div>

    <!-- Right: Form -->
    <div class="bg-gray-900/70 border border-gray-800 rounded-xl p-4">
      <h2 class="text-lg font-semibold mb-2">Grip Test & Log</h2>
      <form method="POST" class="space-y-3 text-sm">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Test Date</label>
          <input type="date" name="test_date"
                 class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-sm"
                 value="<?php echo htmlspecialchars($_POST['test_date'] ?? date('Y-m-d')); ?>">
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1">Test Type</label>
          <select name="test_type"
                  class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-sm">
            <option value="dynamometer">Dynamometer</option>
            <option value="dead_hang">Dead Hang</option>
            <option value="farmer_carry">Farmer Carry</option>
          </select>
        </div>

        <!-- Add your fields (left/right, dead hang, farmer) similarly, using Tailwind -->

        <div>
          <label class="block text-xs text-gray-400 mb-1">Notes</label>
          <textarea name="notes"
                    class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-sm"
                    rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>

        <button type="submit"
                class="inline-flex items-center px-3 py-1.5 rounded-full bg-orange-500 text-black text-xs font-semibold">
          Save Grip Log
        </button>
      </form>
    </div>
  </div>

  <!-- History table -->
  <div class="bg-gray-900/70 border border-gray-800 rounded-xl p-4">
    <h2 class="text-lg font-semibold mb-2">Recent Grip Logs</h2>
    <?php if (empty($logs)): ?>
      <p class="text-sm text-gray-400">No logs yet. Save your first grip test above.</p>
    <?php else: ?>
      <div class="overflow-x-auto text-xs">
        <table class="min-w-full border-collapse">
          <thead class="text-gray-400">
            <tr>
              <th class="border-b border-gray-800 py-1 pr-2 text-left">Date</th>
              <th class="border-b border-gray-800 py-1 pr-2 text-left">Type</th>
              <th class="border-b border-gray-800 py-1 pr-2 text-left">Avg (lbs)</th>
              <th class="border-b border-gray-800 py-1 pr-2 text-left">Category</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($logs as $row): ?>
            <tr class="border-b border-gray-900">
              <td class="py-1 pr-2"><?php echo htmlspecialchars($row['test_date']); ?></td>
              <td class="py-1 pr-2"><?php echo htmlspecialchars($row['test_type']); ?></td>
              <td class="py-1 pr-2"><?php echo $row['avg_grip_lbs'] !== null ? (float)$row['avg_grip_lbs'] : ''; ?></td>
              <td class="py-1 pr-2"><?php echo htmlspecialchars((string)$row['category']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
