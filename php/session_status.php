<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'not_logged_in']);
    exit;
}

if (!user_exists($pdo)) {
    session_destroy();
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'account_deleted']);
    exit;
}

echo json_encode(['ok' => true]);
