<?php
require __DIR__ . '/../includes/auth.php';
require_auth();

$email = $_SESSION['email'] ?? 'user';
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
        </div>
    </div>
</body>
</html>
