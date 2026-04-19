<?php
declare(strict_types=1);

/**
 * bmr_logs.php
 *
 * Read-only viewer for BMR / maintenance / goal intake logs.
 *
 * Depends on:
 * - config.php that defines $conn = new mysqli(...)
 * - Session with:
 *     $_SESSION['user_id']
 *     $_SESSION['user_data']['role_id'] (10 = admin)
 *
 * Table:
 *   bmr_logs (see bmr_calculator.php for schema)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../auth_functions.php';

$authUser = require_auth();

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection not available.');
}

$userId  = (int)($authUser['id'] ?? 0);
$roleId  = $_SESSION['user_data']['role_id'] ?? ($authUser['role_id'] ?? null);
$isAdmin = ($roleId !== null && (int)$roleId === 10);

// --- Filters via GET ---
$filterUserId  = $isAdmin ? (int)($_GET['user_id'] ?? 0) : $userId;
$dateFromInput = trim((string)($_GET['date_from'] ?? ''));
$dateToInput   = trim((string)($_GET['date_to'] ?? ''));
$limit         = 200; // max rows to show

$whereParts = [];
$types      = '';
$params     = [];

// Restrict non-admins to their own data
if ($isAdmin) {
    if ($filterUserId > 0) {
        $whereParts[] = 'user_id = ?';
        $types       .= 'i';
        $params[]     = $filterUserId;
    }
} else {
    $whereParts[] = 'user_id = ?';
    $types       .= 'i';
    $params[]     = $userId;
}

// Date range (created_at between)
if ($dateFromInput !== '') {
    $whereParts[] = 'created_at >= ?';
    $types       .= 's';
    $params[]     = $dateFromInput . ' 00:00:00';
}

if ($dateToInput !== '') {
    $whereParts[] = 'created_at <= ?';
    $types       .= 's';
    $params[]     = $dateToInput . ' 23:59:59';
}

$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$sql = "
    SELECT
      id,
      user_id,
      sex,
      age,
      height_cm,
      weight_kg,
      pal,
      bmr_kcal,
      maintenance_kcal,
      goal_weight_kg,
      goal_days,
      goal_daily_delta_kcal,
      goal_intake_kcal,
      created_at
    FROM bmr_logs
    $whereSql
    ORDER BY created_at DESC, id DESC
    LIMIT ?
";

$types      .= 'i';
$params[]    = $limit;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit('Query prepare failed: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BMR Logs Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --bg: #020617;
            --bg-card: #020617;
            --accent: #ff6b00;
            --accent-purple: #a855f7;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --border-subtle: #1f2937;
            --border-strong: #4b5563;
            --row-alt: #020617;
            --row-hover: #111827;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top, #111827 0, #020617 50%, #000 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 1.5rem;
        }

        .wrapper {
            width: 100%;
            max-width: 1100px;
        }

        .card {
            background:
              radial-gradient(circle at top left, rgba(248, 113, 113, 0.06), transparent 55%),
              radial-gradient(circle at bottom right, rgba(59, 130, 246, 0.06), transparent 55%),
              linear-gradient(145deg, #020617, #020617 50%, #020617);
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.35);
            padding: 1.5rem;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(14px);
        }

        header {
            margin-bottom: 1.25rem;
        }

        h1 {
            margin: 0;
            font-size: 1.6rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        h1 span.logo-dot {
            display: inline-flex;
            width: 1.7rem;
            height: 1.7rem;
            border-radius: 999px;
            background: conic-gradient(from 0deg, var(--accent), var(--accent-purple), var(--accent));
            box-shadow: 0 0 18px rgba(248, 113, 113, 0.8);
        }

        h1 small {
            font-size: 0.7rem;
            font-weight: 500;
            color: #f97316;
            text-transform: none;
        }

        .subheading {
            margin-top: 0.3rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
            margin-bottom: 1rem;
            padding: 0.75rem 0.9rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-subtle);
            background: rgba(15, 23, 42, 0.7);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            min-width: 150px;
        }

        .filter-label {
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #d1d5db;
        }

        input[type="date"],
        input[type="number"],
        select {
            border-radius: 0.5rem;
            border: 1px solid var(--border-subtle);
            padding: 0.4rem 0.55rem;
            background: rgba(15, 23, 42, 0.95);
            color: var(--text-main);
            font-size: 0.85rem;
        }

        input[type="date"]:focus,
        input[type="number"]:focus,
        select:focus {
            outline: 1px solid var(--accent);
            border-color: var(--accent);
        }

        .btn {
            border-radius: 0.55rem;
            border: none;
            padding: 0.45rem 0.9rem;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary {
            background-image: linear-gradient(135deg, var(--accent), var(--accent-purple));
            color: #020617;
            box-shadow: 0 8px 20px rgba(248, 113, 113, 0.55);
        }

        .btn-outline {
            border: 1px solid var(--border-subtle);
            background: transparent;
            color: var(--text-muted);
        }

        .logs-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.45rem;
        }

        .logs-meta strong {
            color: #e5e7eb;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.1rem 0.45rem;
            border-radius: 999px;
            border: 1px solid rgba(55, 65, 81, 0.9);
            font-size: 0.7rem;
        }

        .badge-admin {
            border-color: #f97316;
            color: #fed7aa;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 0.75rem;
            border: 1px solid var(--border-subtle);
            background: rgba(15, 23, 42, 0.8);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        thead {
            background: linear-gradient(135deg, rgba(248, 113, 113, 0.2), rgba(59, 130, 246, 0.2));
        }

        th, td {
            padding: 0.4rem 0.55rem;
            border-bottom: 1px solid rgba(31, 41, 55, 0.9);
            white-space: nowrap;
        }

        th {
            text-align: left;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #e5e7eb;
        }

        tbody tr:nth-child(even) {
            background: var(--row-alt);
        }

        tbody tr:hover {
            background: var(--row-hover);
        }

        .col-small {
            width: 1%;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.1rem;
            padding: 0.05rem 0.35rem;
            border-radius: 999px;
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border: 1px solid rgba(75, 85, 99, 0.9);
        }

        .pill-male {
            border-color: #60a5fa;
            color: #bfdbfe;
        }

        .pill-female {
            border-color: #f472b6;
            color: #f9a8d4;
        }

        .empty-state {
            padding: 1rem;
            text-align: center;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .card { padding: 1.1rem; }
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            .logs-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <header>
            <h1>
                <span class="logo-dot"></span>
                BMR Logs
                <small>exFIT metabolic snapshots</small>
            </h1>
            <p class="subheading">
                Read-only history of BMR / maintenance and goal-intake calculations. Use this to
                see how your assumptions (activity level, goals) shift over time, or to debug
                how the planner is being used.
            </p>
        </header>

        <form class="filters" method="get" action="">
            <?php if ($isAdmin): ?>
                <div class="filter-group">
                    <span class="filter-label">User ID</span>
                    <input type="number" name="user_id" min="0"
                           value="<?= htmlspecialchars((string)($filterUserId ?: ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Any">
                </div>
            <?php else: ?>
                <div class="filter-group">
                    <span class="filter-label">User</span>
                    <div class="badge">
                        ID: <?= (int) $userId ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="filter-group">
                <span class="filter-label">From</span>
                <input type="date" name="date_from"
                       value="<?= htmlspecialchars($dateFromInput, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="filter-group">
                <span class="filter-label">To</span>
                <input type="date" name="date_to"
                       value="<?= htmlspecialchars($dateToInput, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="filter-group" style="flex: 1; min-width: 160px;">
                <span class="filter-label">&nbsp;</span>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">
                        Apply filters
                    </button>
                    <a href="bmr_logs.php" class="btn btn-outline" style="text-decoration: none;">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <div class="logs-meta">
            <div>
                Showing <strong><?= count($rows) ?></strong> record(s)
                <?= $isAdmin && $filterUserId ? "for user {$filterUserId}" : ($isAdmin ? '(all users)' : '') ?>
                <?= ($dateFromInput || $dateToInput)
                    ? ' in selected date range'
                    : ' (latest first)'
                ?>.
            </div>
            <div>
                <?php if ($isAdmin): ?>
                    <span class="badge badge-admin">Admin view</span>
                <?php else: ?>
                    <span class="badge">Your logs only</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrapper">
            <?php if (!$rows): ?>
                <div class="empty-state">
                    No BMR logs found for the selected filters.
                </div>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th class="col-small">ID</th>
                        <th class="col-small">User</th>
                        <th>When</th>
                        <th>Sex</th>
                        <th>Age</th>
                        <th>Ht (cm)</th>
                        <th>Wt (kg)</th>
                        <th>PAL</th>
                        <th>BMR</th>
                        <th>Maint</th>
                        <th>Goal Wt (kg)</th>
                        <th>Days</th>
                        <th>Δ kcal/day</th>
                        <th>Goal kcal</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><?= (int) $r['user_id'] ?></td>
                            <td><?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($r['sex'] === 'male'): ?>
                                    <span class="pill pill-male">M</span>
                                <?php else: ?>
                                    <span class="pill pill-female">F</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $r['age'] ?></td>
                            <td><?= number_format((float)$r['height_cm'], 1) ?></td>
                            <td><?= number_format((float)$r['weight_kg'], 1) ?></td>
                            <td><?= number_format((float)$r['pal'], 2) ?></td>
                            <td><?= (int) $r['bmr_kcal'] ?></td>
                            <td><?= (int) $r['maintenance_kcal'] ?></td>
                            <td>
                                <?= $r['goal_weight_kg'] !== null
                                    ? number_format((float)$r['goal_weight_kg'], 1)
                                    : '—' ?>
                            </td>
                            <td><?= $r['goal_days'] !== null ? (int)$r['goal_days'] : '—' ?></td>
                            <td><?= $r['goal_daily_delta_kcal'] !== null ? (int)$r['goal_daily_delta_kcal'] : '—' ?></td>
                            <td><?= $r['goal_intake_kcal'] !== null ? (int)$r['goal_intake_kcal'] : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <p style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--text-muted);">
            Tip: you can later plug this into charts (per-user BMR drift, PAL drift, goal aggressiveness),
            or join against <code>users</code> to show names instead of bare IDs.
        </p>
    </div>
</div>
</body>
</html>
