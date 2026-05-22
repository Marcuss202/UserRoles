<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_auth();

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser || $currentUser['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $userId = intval($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';
        
        $validRoles = ['admin', 'item', 'shelf'];
        if (!in_array($newRole, $validRoles)) {
            $error = 'Invalid role selected.';
        } elseif ($userId <= 0) {
            $error = 'Invalid user ID.';
        } elseif ($userId == $_SESSION['user_id']) {
            $error = 'You cannot change your own role.';
        } else {
            try {
                $checkStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
                $checkStmt->execute([$userId]);
                if (!$checkStmt->fetch()) {
                    $error = 'User not found.';
                } else {
                    $updateStmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                    $updateStmt->execute([$newRole, $userId]);
                    $success = 'User role updated successfully.';
                }
            } catch (PDOException $e) {
                error_log('Admin error: ' . $e->getMessage());
                $error = 'An error occurred while updating the role.';
            }
        }
    }
}

try {
    $stmt = $pdo->prepare('SELECT id, email, role, created_at FROM users ORDER BY created_at DESC');
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Admin fetch error: ' . $e->getMessage());
    $error = 'Failed to fetch users.';
    $users = [];
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Panel">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Panel - User Management</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="info-text">
            Total users: <strong><?php echo count($users); ?></strong>
        </div>

        <?php if (!empty($users)): ?>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Current Role</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($user['created_at']))); ?></td>
                            <td>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span style="color: #999; font-size: 0.9em;">Your account</span>
                                <?php else: ?>
                                    <form method="post" action="admin.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <select name="role" class="role-select" onchange="this.parentElement.submit()">
                                            <option value="">Change role...</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="item" <?php echo $user['role'] === 'item' ? 'selected' : ''; ?>>Item Manager</option>
                                            <option value="shelf" <?php echo $user['role'] === 'shelf' ? 'selected' : ''; ?>>Shelf Staff</option>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #666; margin-top: 20px;">No users found.</p>
        <?php endif; ?>

        <div class="info-text" style="margin-top: 40px; padding: 20px; background-color: #f5f5f5; border-radius: 4px;">
            <strong>Role Guide:</strong><br>
            • <strong>Admin:</strong> Full access to admin panel and user management<br>
            • <strong>Item Manager:</strong> Can manage products/items<br>
            • <strong>Shelf Staff:</strong> Can perform shelf operations<br>
            <em>Note: You cannot change your own role.</em>
        </div>
    </div>
</body>
</html>
