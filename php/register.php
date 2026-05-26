<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Public registration is disabled. Users must be created by admins only.
// Redirect non-logged-in users to login page
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// If a logged-in user tries to access this, show access denied
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Access Denied</h1>
            <p>User registration is disabled. New users can only be created by administrators through the admin panel.</p>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>

