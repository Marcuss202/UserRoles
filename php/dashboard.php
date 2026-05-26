<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require_auth();

// Check if user needs to change their temporary password
$stmt = $pdo->prepare('SELECT password_changed FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user && !$user['password_changed']) {
    header('Location: change_password.php');
    exit;
}

$email = $_SESSION['email'] ?? 'user';

// Get user's role
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$userRole = $user['role'] ?? 'shelf';
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
            <p class="helper"><a href="products.php">View products</a></p>
            
            <div class="dashboard-panels">
                <?php if ($userRole === 'admin'): ?>
                    <a href="products.php" class="dashboard-btn btn-shelf">View Products</a>
                    <a href="report.php" class="dashboard-btn btn-item">View Report</a>
                    <a href="orders.php" class="dashboard-btn btn-item">Order Management</a>
                    <a href="admin.php" class="dashboard-btn btn-admin">Admin Panel</a>
                <?php endif; ?>
                
                <?php if ($userRole === 'item'): ?>
                    <a href="products.php" class="dashboard-btn btn-item">Add Products</a>
                    <a href="orders.php" class="dashboard-btn btn-item">Order Management</a>
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
