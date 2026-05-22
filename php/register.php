<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

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

function sanitizeEmail($email): string {
    return strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));
}

function checkRateLimit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rateFile = sys_get_temp_dir() . '/register_limit_' . md5($ip) . '.json';
    
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
$notice = '';
$passwordStrengthErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRateLimit()) {
        $error = 'Too many registration attempts. Please wait 1 minute before trying again.';
    }
    elseif (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $password2 = trim($_POST['password2'] ?? '');
        
        if ($email === '' || $password === '' || $password2 === '') {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (strlen($email) > 255) {
            $error = 'Email is too long.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            $passwordStrengthErrors = validatePasswordStrength($password);
            
            if (!empty($passwordStrengthErrors)) {
                $error = 'Password does not meet security requirements.';
            } else {
                try {
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Registration could not be completed. Please try with a different email.';
                    } else {
                        $hash = password_hash($password, PASSWORD_ARGON2ID, [
                            'memory_cost' => 65536,
                            'time_cost' => 4,
                            'threads' => 2
                        ]);
                        
                        $insert = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
                        $insert->execute([$email, $hash]);
                        
                        $notice = 'Registration successful. You can now log in.';
                        $_POST = [];
                    }
                } catch (PDOException $e) {
                    error_log('Registration error: ' . $e->getMessage());
                    $error = 'An error occurred during registration. Please try again.';
                }
            }
        }
    }
}


$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure registration form">
    <title>Register</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .password-requirements {
            margin-top: 10px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .password-requirements ul {
            list-style-type: disc;
            margin: 8px 0 0 20px;
            padding: 0;
        }
        .requirement {
            margin: 5px 0;
            color: #666;
        }
        .requirement.met {
            color: #28a745;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Create account</h1>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($notice): ?>
                <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
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
            
            <form method="post" action="register.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    maxlength="255"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    autocomplete="email"
                >
                
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
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
                
                <label for="password2">Repeat password</label>
                <input 
                    type="password" 
                    id="password2" 
                    name="password2" 
                    required 
                    autocomplete="new-password"
                >
                
                <button type="submit">Register</button>
            </form>
            
            <div class="helper">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            
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
</body>
</html>
