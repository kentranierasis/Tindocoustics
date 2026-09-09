<?php
// api/check-session.php
require_once '../config.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("Check session - Session data: " . print_r($_SESSION, true));

// Check if user is logged in (either customer or admin)
$logged_in = false;
$user_type = null;

if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])) {
    $logged_in = true;
    $user_type = 'customer';
} elseif (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    $logged_in = true;
    $user_type = 'admin';
}

echo json_encode([
    'logged_in' => $logged_in,
    'user_type' => $user_type,
    'user' => $logged_in ? [
        'id' => (int)($_SESSION['customer_id'] ?? $_SESSION['admin_id'] ?? 0),
        'name' => $_SESSION['customer_name'] ?? $_SESSION['admin_name'] ?? null,
        'email' => $_SESSION['customer_email'] ?? $_SESSION['admin_email'] ?? null
    ] : null
]);
?>