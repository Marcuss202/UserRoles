<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (is_logged_in()) {
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

function checkLoginRateLimit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rateFile = sys_get_temp_dir() . '/login_limit_' . md5($ip) . '.json';
    
    if (file_exists($rateFile)) {
        $data = json_decode(file_get_contents($rateFile), true);
        $now = time();
        
        if ($now - $data['time'] < 60) {
            if ($data['attempts'] >= 5) {
                return false;
            }
            $data['attempts']++;
        } else {
            $data = ['attempts' => 1, 'time' => $now];
        }
    } else {
        $data = ['attempts' => 1, 'time' => time()];
    }
    
    file_put_contents($rateFile, json_encode($data));
    return true;
}

$error = '';
$csrfToken = generateCSRFToken();

if (($_GET['timeout'] ?? '') === '1') {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkLoginRateLimit()) {
        $error = 'Too many login attempts. Please wait 1 minute before trying again.';
    }
    elseif (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = trim($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'All fields are required.';
        } else {
            $stmt = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid credentials.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                $ip = $_SERVER['REMOTE_ADDR'];
                $rateFile = sys_get_temp_dir() . '/login_limit_' . md5($ip) . '.json';
                if (file_exists($rateFile)) {
                    unlink($rateFile);
                }
                
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Log in</h1>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <button type="submit">Log in</button>
            </form>
            <div class="helper">
                No account? <a href="register.php">Register</a>
            </div>
        </div>
    </div>

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
