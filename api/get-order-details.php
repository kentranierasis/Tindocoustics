<?php
// api/get-order-details.php
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

$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
    exit;
}

try {
    $pdo = getConnection();
    $customer_id = $_SESSION['customer_id'];
    
    // Get order details
    $stmt = $pdo->prepare("
        SELECT id, order_number, order_date, total, payment_method, status 
        FROM orders 
        WHERE id = ? AND customer_id = ?
    ");
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.quantity, oi.unit_price, p.name, p.variant_label, p.image_path 
        FROM order_items oi 
        JOIN products p ON p.id = oi.product_id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $order['items'] = $items;
    $order['status'] = ucfirst($order['status']);
    $order['order_date'] = date('M j, Y g:i A', strtotime($order['order_date']));
    
    echo json_encode([
        'success' => true,
        'order' => $order
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}