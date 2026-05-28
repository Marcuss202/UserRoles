<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_auth();

$stmt = $pdo->prepare('SELECT password_changed FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user && !$user['password_changed']) {
    header('Location: change_password.php');
    exit;
}

$email = $_SESSION['email'] ?? 'user';

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$userRole = $user['role'] ?? 'shelf';

$stats = [
    'product_count' => 0,
    'shelf_count' => 0,
    'order_total' => 0,
    'order_created' => 0,
    'order_accepted' => 0,
    'order_fulfilled' => 0,
    'open_orders' => 0,
    'user_count' => 0,
    'activity_24h' => 0,
    'latest_product_name' => null,
    'latest_product_time' => null,
];

try {
    $stats['product_count'] = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn();
    $stats['shelf_count'] = (int) $pdo->query('SELECT COUNT(DISTINCT shelf) FROM products WHERE deleted_at IS NULL')->fetchColumn();
    $stats['order_total'] = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $stats['order_created'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'created'")->fetchColumn();
    $stats['order_accepted'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'accepted'")->fetchColumn();
    $stats['order_fulfilled'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'fulfilled'")->fetchColumn();
    $stats['open_orders'] = $stats['order_created'] + $stats['order_accepted'];
    $stats['user_count'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['activity_24h'] = (int) $pdo->query('SELECT COUNT(*) FROM activity_log WHERE created_at >= (NOW() - INTERVAL 24 HOUR)')->fetchColumn();

    $latestProduct = $pdo->query('SELECT name, created_at FROM products WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 1')->fetch();
    if ($latestProduct) {
        $stats['latest_product_name'] = $latestProduct['name'];
        $stats['latest_product_time'] = date('M j, H:i', strtotime($latestProduct['created_at']));
    }
} catch (PDOException $e) {
    // Keep default stats if tables are missing or queries fail.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="topbar">
                <h1>Warehouse</h1>
                <a class="link-btn" href="logout.php">Log out</a>
            </div>
            <p>Welcome, <?php echo htmlspecialchars($email); ?>.</p>

            <div class="dashboard-summary">
                <h2 class="summary-title">Parskats</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">Products</div>
                        <div class="summary-value"><?php echo $stats['product_count']; ?></div>
                        <div class="summary-meta">Shelves used: <?php echo $stats['shelf_count']; ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Orders</div>
                        <div class="summary-value"><?php echo $stats['order_total']; ?></div>
                        <div class="summary-meta">Open: <?php echo $stats['open_orders']; ?> | Fulfilled: <?php echo $stats['order_fulfilled']; ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Users</div>
                        <div class="summary-value"><?php echo $stats['user_count']; ?></div>
                        <div class="summary-meta">Activity last 24h: <?php echo $stats['activity_24h']; ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Order status</div>
                        <div class="summary-value"><?php echo $stats['open_orders']; ?></div>
                        <div class="summary-meta">Created: <?php echo $stats['order_created']; ?> | Accepted: <?php echo $stats['order_accepted']; ?></div>
                    </div>
                </div>
                <div class="summary-note">
                    <?php if ($stats['latest_product_name']): ?>
                        Latest product: <strong><?php echo htmlspecialchars($stats['latest_product_name']); ?></strong>
                        <span class="summary-muted">(<?php echo htmlspecialchars($stats['latest_product_time']); ?>)</span>
                    <?php else: ?>
                        <span class="summary-muted">No products yet.</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-panels">
                <?php if ($userRole === 'admin'): ?>
                    <a href="products.php" class="dashboard-btn btn-shelf">View Products</a>
                    <a href="report.php" class="dashboard-btn btn-item">View Report</a>
                    <a href="orders.php" class="dashboard-btn btn-manage">Order Management</a>
                    <a href="admin.php" class="dashboard-btn btn-admin">Admin Panel</a>
                <?php endif; ?>
                
                <?php if ($userRole === 'item'): ?>
                    <a href="products.php" class="dashboard-btn btn-item">Add Products</a>
                    <a href="orders.php" class="dashboard-btn btn-item btn-manage">Order Management</a>
                <?php endif; ?>
                
                <?php if ($userRole === 'shelf'): ?>
                    <a href="products.php" class="dashboard-btn btn-shelf">View Products</a>
                    <a href="orders.php" class="dashboard-btn btn-shelf">View Orders</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
