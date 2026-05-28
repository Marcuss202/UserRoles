<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_auth_with_user($pdo);

$range = $_GET['range'] ?? '7';
$range = in_array($range, ['today', '7', '30', 'all'], true) ? $range : '7';

$where = '';
$params = [];

if ($range === 'today') {
    $start = date('Y-m-d 00:00:00');
    $where = 'WHERE a.created_at >= ?';
    $params[] = $start;
} elseif ($range === '7') {
    $start = date('Y-m-d H:i:s', strtotime('-7 days'));
    $where = 'WHERE a.created_at >= ?';
    $params[] = $start;
} elseif ($range === '30') {
    $start = date('Y-m-d H:i:s', strtotime('-30 days'));
    $where = 'WHERE a.created_at >= ?';
    $params[] = $start;
}

$sql =
    'SELECT a.action,
            a.entity_type,
            a.entity_id,
            a.field_name,
            a.old_value,
            a.new_value,
            a.created_at,
            u.email AS user_email
     FROM activity_log a
     LEFT JOIN users u ON u.id = a.user_id
     ' . $where . '
     ORDER BY a.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

function trim_report_text(?string $text, int $max = 40): string {
    $value = $text ?? '-';
    return mb_strimwidth($value, 0, $max, '...');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity report</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="page-wide">
    <div class="container-wide">
        <div class="panel-flat">
            <div class="topbar">
                <h1>Activity report</h1>
                <div class="topbar-actions">
                    <a class="link-btn btn-red" href="products.php">Back</a>
                </div>
            </div>

            <form method="get" action="report.php" class="filter-bar">
                <label for="range">Period</label>
                <select id="range" name="range">
                    <option value="today" <?php echo $range === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="7" <?php echo $range === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                    <option value="30" <?php echo $range === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                    <option value="all" <?php echo $range === 'all' ? 'selected' : ''; ?>>All time</option>
                </select>
                <button type="submit" class="link-btn btn-gray">Apply</button>
            </form>

            <?php if (!$activities): ?>
                <div class="table-empty">No activity found.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Field</th>
                            <th>From</th>
                            <th>To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($activity['user_email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                <td><?php echo htmlspecialchars($activity['entity_type']); ?> #<?php echo htmlspecialchars($activity['entity_id'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($activity['field_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(trim_report_text($activity['old_value'] ?? null)); ?></td>
                                <td><?php echo htmlspecialchars(trim_report_text($activity['new_value'] ?? null)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script src="../assets/session-check.js"></script>
</body>
</html>
