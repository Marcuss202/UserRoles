<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_auth_with_user($pdo);

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

function sanitizeEmail($email): string {
    return strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));
}

function generateTemporaryPassword(): string {
    return 'Temp' . bin2hex(random_bytes(8)) . '!';
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_user') {
            $email = sanitizeEmail($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'item';
            
            $validRoles = ['admin', 'item', 'shelf'];
            
            if ($email === '') {
                $error = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format.';
            } elseif (strlen($email) > 255) {
                $error = 'Email is too long.';
            } elseif (!in_array($role, $validRoles)) {
                $error = 'Invalid role selected.';
            } else {
                try {
                    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                    $checkStmt->execute([$email]);
                    
                    if ($checkStmt->fetch()) {
                        $error = 'A user with this email already exists.';
                    } else {
                        $tempPassword = generateTemporaryPassword();
                        $hash = password_hash($tempPassword, PASSWORD_ARGON2ID, [
                            'memory_cost' => 65536,
                            'time_cost' => 4,
                            'threads' => 2
                        ]);
                        
                        $insert = $pdo->prepare('INSERT INTO users (email, password_hash, role, password_changed) VALUES (?, ?, ?, FALSE)');
                        $insert->execute([$email, $hash, $role]);
                        
                        $success = "User created successfully. Temporary password: <strong>$tempPassword</strong> (User must change on first login)";
                        $_POST = [];
                    }
                } catch (PDOException $e) {
                    error_log('User creation error: ' . $e->getMessage());
                    $error = 'An error occurred while creating the user.';
                }
            }
        } elseif ($action === 'delete_user') {
            $userId = intval($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                $error = 'Invalid user ID.';
            } elseif ($userId == $_SESSION['user_id']) {
                $error = 'You cannot delete your own account.';
            } else {
                try {
                    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
                    $checkStmt->execute([$userId]);
                    if (!$checkStmt->fetch()) {
                        $error = 'User not found.';
                    } else {
                        $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                        $deleteStmt->execute([$userId]);
                        $success = 'User deleted successfully.';
                    }
                } catch (PDOException $e) {
                    error_log('User deletion error: ' . $e->getMessage());
                    $error = 'Unable to delete user. They may have related records.';
                }
            }
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
            <div class="topbar-actions">
                <a class="link-btn btn-gray" href="dashboard.php">Back</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div style="margin-bottom: 40px; padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
            <h2 style="margin-top: 0; color: #333;">Create New User</h2>
            <form method="post" action="admin.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create_user">
                
                <div style="margin-bottom: 15px;">
                    <label for="new_email" style="display: block; margin-bottom: 5px; font-weight: bold;">Email</label>
                    <input 
                        type="email" 
                        id="new_email" 
                        name="email" 
                        required 
                        maxlength="255"
                        placeholder="user@example.com"
                        autocomplete="off"
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box;"
                    >
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="new_role" style="display: block; margin-bottom: 5px; font-weight: bold;">Role</label>
                    <select name="role" id="new_role" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box;">
                        <option value="item">Item Manager</option>
                        <option value="shelf">Shelf Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <button type="submit" style="background-color: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; font-size: 14px;">Create User</button>
            </form>
        </div>

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
                                    <form method="post" action="admin.php" style="display: inline;" onsubmit="return confirm('Delete this user? This action cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <button type="submit" class="action-btn" style="background-color: #dc3545;">Confirm delete</button>
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

    <script src="../assets/session-check.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert, .error, .notice');
            alerts.forEach(alert => {
                const isTempPasswordAlert = alert.textContent.includes('Temporary password');
                const dismissTime = isTempPasswordAlert ? 60000 : 3000; // 60 seconds for temp password, 3 seconds otherwise
                
                setTimeout(() => {
                    alert.classList.add('dismiss');
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, dismissTime);
            });
        });
    </script>
</body>
</html>
