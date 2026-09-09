<?php
// api/cart-count.php
require_once '../config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare('SELECT SUM(quantity) as total FROM cart_items WHERE customer_id = ?');
    $stmt->execute([$_SESSION['customer_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['count' => (int)($result['total'] ?? 0)]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}