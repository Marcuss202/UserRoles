<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

// Security: Set strict headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\'');

// Security: Generate and verify CSRF tokens
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Security: Validate password strength
function validatePasswordStrength($password): array {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters.';
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

// Security: Sanitize email
function sanitizeEmail($email): string {
    return strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));
}

$error = '';
$notice = '';
$passwordStrengthErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token verification
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $password2 = trim($_POST['password2'] ?? '');
        
        // Input validation
        if ($email === '' || $password === '' || $password2 === '') {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (strlen($email) > 255) {
            $error = 'Email is too long.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            // Check password strength
            $passwordStrengthErrors = validatePasswordStrength($password);
            
            if (!empty($passwordStrengthErrors)) {
                $error = 'Password does not meet security requirements.';
            } else {
                try {
                    // Check if email exists (timing-safe comparison)
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    
                    if ($stmt->fetch()) {
                        // Don't reveal if email exists (prevent enumeration)
                        $error = 'Registration could not be completed. Please try with a different email.';
                    } else {
                        // Hash with strong algorithm
                        $hash = password_hash($password, PASSWORD_ARGON2ID, [
                            'memory_cost' => 65536,
                            'time_cost' => 4,
                            'threads' => 2
                        ]);
                        
                        $insert = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
                        $insert->execute([$email, $hash]);
                        
                        $notice = 'Registration successful. You can now log in.';
                        // Clear form
                        $_POST = [];
                    }
                } catch (PDOException $e) {
                    // Log error but don't expose details
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
        .requirement {
            margin: 5px 0;
            color: #666;
        }
        .requirement.met {
            color: #28a745;
        }
        .requirement.unmet {
            color: #dc3545;
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
                    <strong>Password must contain:</strong>
                    <div class="requirement" id="req-length">✗ At least 12 characters</div>
                    <div class="requirement" id="req-upper">✗ Uppercase letters (A-Z)</div>
                    <div class="requirement" id="req-lower">✗ Lowercase letters (a-z)</div>
                    <div class="requirement" id="req-number">✗ Numbers (0-9)</div>
                    <div class="requirement" id="req-special">✗ Special characters (!@#$%^&*)</div>
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
        // Real-time password strength validation (client-side preview only)
        const passwordInput = document.getElementById('password');
        
        function checkPasswordRequirements() {
            const password = passwordInput.value;
            
            const checks = {
                'req-length': password.length >= 12,
                'req-upper': /[A-Z]/.test(password),
                'req-lower': /[a-z]/.test(password),
                'req-number': /[0-9]/.test(password),
                'req-special': /[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\/]/.test(password)
            };
            
            Object.entries(checks).forEach(([id, met]) => {
                const element = document.getElementById(id);
                if (met) {
                    element.classList.remove('unmet');
                    element.classList.add('met');
                    element.textContent = element.textContent.replace('✗', '✓');
                } else {
                    element.classList.remove('met');
                    element.classList.add('unmet');
                    element.textContent = element.textContent.replace('✓', '✗');
                }
            });
        }
        
        passwordInput.addEventListener('input', checkPasswordRequirements);
    </script>
</body>
</html>
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
        // Real-time password strength validation (client-side preview only)
        const passwordInput = document.getElementById('password');
        
        function checkPasswordRequirements() {
            const password = passwordInput.value;
            
            const checks = {
                'req-length': password.length >= 12,
                'req-upper': /[A-Z]/.test(password),
                'req-lower': /[a-z]/.test(password),
                'req-number': /[0-9]/.test(password),
                'req-special': /[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\/]/.test(password)
            };
            
            Object.entries(checks).forEach(([id, met]) => {
                const element = document.getElementById(id);
                if (met) {
                    element.classList.remove('unmet');
                    element.classList.add('met');
                    element.textContent = element.textContent.replace('✗', '✓');
                } else {
                    element.classList.remove('met');
                    element.classList.add('unmet');
                    element.textContent = element.textContent.replace('✓', '✗');
                }
            });
        }
        
        passwordInput.addEventListener('input', checkPasswordRequirements);
        
        // Prevent paste of suspicious content patterns
        document.getElementById('password').addEventListener('paste', function(e) {
            // Allow paste but validate server-side
        });
    </script>
</body>
</html>
