<?php
// api/check-wishlist.php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['in_wishlist' => false, 'wishlist_count' => 0]);
    exit;
}

$product_id = (int)($_GET['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['in_wishlist' => false, 'wishlist_count' => 0]);
    exit;
}

try {
    $pdo = getConnection();
    $customer_id = $_SESSION['customer_id'];
    
    // Check if in wishlist
    $stmt = $pdo->prepare('SELECT id FROM wishlist_items WHERE customer_id = ? AND product_id = ?');
    $stmt->execute([$customer_id, $product_id]);
    $in_wishlist = (bool)$stmt->fetch();
    
    // Get wishlist count
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist_items WHERE customer_id = ?');
    $stmt->execute([$customer_id]);
    $count = (int)$stmt->fetchColumn();
    
    echo json_encode([
        'in_wishlist' => $in_wishlist,
        'wishlist_count' => $count
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['in_wishlist' => false, 'wishlist_count' => 0]);
}
?>