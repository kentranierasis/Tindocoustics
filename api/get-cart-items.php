<?php
// api/get-cart-items.php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

try {
    $pdo = getConnection();
    $customer_id = $_SESSION['customer_id'];
    
    $stmt = $pdo->prepare("
        SELECT ci.id, ci.quantity, p.id AS product_id, p.name, 
               p.variant_label, p.price, p.image_path, p.stock_status
        FROM cart_items ci 
        JOIN products p ON p.id = ci.product_id 
        WHERE ci.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total
    $total = array_reduce($items, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'total' => $total
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}