<?php
require __DIR__ . '/../includes/auth.php';

$_SESSION = [];
if (session_id() !== '') {
    session_destroy();
}

header('Location: login.php');
exit;
