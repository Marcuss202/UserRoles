<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_auth_with_user($pdo);

function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function validatePasswordStrength($password): array {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters.';
    }
    if (strpos($password, ' ') !== false) {
        $errors[] = 'Password cannot contain spaces.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain uppercase letters.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain lowercase letters.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain numbers.';
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\/]/', $password)) {
        $errors[] = 'Password must contain special characters.';
    }
    
    return $errors;
}

$error = '';
$success = '';
$passwordStrengthErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'All fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $passwordStrengthErrors = validatePasswordStrength($newPassword);
            
            if (!empty($passwordStrengthErrors)) {
                $error = 'Password does not meet security requirements.';
            } else {
                try {
                    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        $error = 'User not found.';
                    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
                        $error = 'Current password is incorrect.';
                    } else {
                        $hash = password_hash($newPassword, PASSWORD_ARGON2ID, [
                            'memory_cost' => 65536,
                            'time_cost' => 4,
                            'threads' => 2
                        ]);
                        
                        $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ?, password_changed = TRUE WHERE id = ?');
                        $updateStmt->execute([$hash, $_SESSION['user_id']]);
                        
                        $success = 'Password changed successfully! Redirecting to dashboard...';
                        header('Refresh: 2; url=dashboard.php');
                    }
                } catch (PDOException $e) {
                    error_log('Password change error: ' . $e->getMessage());
                    $error = 'An error occurred while changing your password.';
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$email = $_SESSION['email'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Change Password">
    <title>Change Password</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Change Your Password</h1>
            <p style="color: #666; margin-bottom: 20px;">Welcome, <?php echo htmlspecialchars($email); ?>! You must change your temporary password before continuing.</p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="notice"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($passwordStrengthErrors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($passwordStrengthErrors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="change_password.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <label for="current_password">Current (Temporary) Password</label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    required 
                    autocomplete="current-password"
                >
                
                <label for="new_password">New Password</label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password" 
                    required 
                    autocomplete="new-password"
                    aria-describedby="password-requirements"
                >
                
                <div class="password-requirements" id="password-requirements">
                    <strong>Password requirements:</strong>
                    <ul>
                        <li class="requirement" id="req-length">At least 12 characters</li>
                        <li class="requirement" id="req-upper">Uppercase letters (A-Z)</li>
                        <li class="requirement" id="req-nospace">No spaces allowed</li>
                        <li class="requirement" id="req-lower">Lowercase letters (a-z)</li>
                        <li class="requirement" id="req-number">Numbers (0-9)</li>
                        <li class="requirement" id="req-special">Special characters (!@#$%^&*)</li>
                    </ul>
                </div>
                
                <label for="confirm_password">Confirm New Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required 
                    autocomplete="new-password"
                >
                
                <button type="submit">Change Password</button>
            </form>
        </div>
    </div>
    
    <script src="../assets/session-check.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('new_password');
            
            function updateRequirements() {
                const password = passwordInput.value;
                
                const requirements = {
                    'req-length': password.length >= 12,
                    'req-upper': /[A-Z]/.test(password),
                    'req-lower': /[a-z]/.test(password),
                    'req-number': /[0-9]/.test(password),
                    'req-special': /[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\/]/.test(password),
                    'req-nospace': !/\s/.test(password)
                };
                
                for (const [id, isMet] of Object.entries(requirements)) {
                    const element = document.getElementById(id);
                    if (element) {
                        if (isMet) {
                            element.classList.add('met');
                        } else {
                            element.classList.remove('met');
                        }
                    }
                }
            }
            
            if (passwordInput) {
                passwordInput.addEventListener('keypress', function(e) {
                    if (e.key === ' ') {
                        e.preventDefault();
                        return false;
                    }
                });
                
                passwordInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                    if (!/\s/.test(pastedText)) {
                        document.execCommand('insertText', false, pastedText);
                    }
                });
                
                passwordInput.addEventListener('input', updateRequirements);
                updateRequirements();
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert, .error, .notice');
            alerts.forEach(alert => {
                const isTempPasswordAlert = alert.textContent.includes('Temporary password');
                const dismissTime = isTempPasswordAlert ? 60000 : 3000;
                
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
