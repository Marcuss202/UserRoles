<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 1800);
    
    session_start();
    
    if (empty($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

define('SESSION_ACTIVITY_TIMEOUT', 1800);

function check_session_timeout(): void {
    if (is_logged_in()) {
        $now = time();
        $lastActivity = $_SESSION['last_activity'] ?? $now;
        
        if ($now - $lastActivity > SESSION_ACTIVITY_TIMEOUT) {
            session_destroy();
            header('Location: login.php?timeout=1');
            exit;
        }
        
        $_SESSION['last_activity'] = $now;
    }
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_auth(): void {
    check_session_timeout();
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

function can_manage_orders(): bool {
    return in_array(current_role(), ['admin', 'item'], true);
}

function can_view_orders(): bool {
    return in_array(current_role(), ['admin', 'item', 'shelf'], true);
}

