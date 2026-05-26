<?php
// Security: Harden session configuration
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Security headers for session
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Only over HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 1800); // 30 minutes
    
    session_start();
    
    // Prevent session fixation attacks
    if (empty($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_auth(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function current_role(): string {
    return $_SESSION['role'] ?? 'shelf';
}

function can_add_product(): bool {
    return in_array(current_role(), ['admin', 'item'], true);
}

function can_edit_item_fields(): bool {
    return in_array(current_role(), ['admin', 'item'], true);
}

function can_edit_shelf(): bool {
    return in_array(current_role(), ['admin', 'shelf'], true);
}

