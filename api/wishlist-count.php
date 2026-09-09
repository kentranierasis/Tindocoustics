<?php
// api/wishlist-count.php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist_items WHERE customer_id = ?');
    $stmt->execute([$_SESSION['customer_id']]);
    $count = (int)$stmt->fetchColumn();
    
    echo json_encode(['count' => $count]);
    
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}