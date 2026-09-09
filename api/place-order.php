<?php
// api/place-order.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_method = $data['payment_method'] ?? '';
$shipping_address = $data['shipping_address'] ?? '';
$contact_number = $data['contact_number'] ?? '';

// Validate
if (empty($payment_method) || empty($shipping_address) || empty($contact_number)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

try {
    $pdo = getConnection();
    $customer_id = $_SESSION['customer_id'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get cart items
    $stmt = $pdo->prepare("
        SELECT ci.id, ci.quantity, p.id AS product_id, p.price, p.stock_quantity, p.stock_status, p.name
        FROM cart_items ci 
        JOIN products p ON p.id = ci.product_id 
        WHERE ci.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cartItems)) {
        throw new Exception('Your cart is empty.');
    }
    
    // Calculate total
    $total = array_reduce($cartItems, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);
    
    // Check stock for each item
    foreach ($cartItems as $item) {
        if ($item['stock_status'] === 'out-of-stock' || $item['stock_quantity'] < $item['quantity']) {
            throw new Exception('Insufficient stock for: ' . $item['name']);
        }
    }
    
    // Generate order number
    $orderNumber = 'TIND-' . strtoupper(uniqid());
    
    // Insert order - using order_date (matches your database schema)
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_number, customer_id, total, payment_method, status, order_date) 
        VALUES (?, ?, ?, ?, 'processing', NOW())
    ");
    $stmt->execute([$orderNumber, $customer_id, $total, $payment_method]);
    $orderId = $pdo->lastInsertId();
    
    // Insert order items and update stock
    foreach ($cartItems as $item) {
        // Insert order item
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
        
        // Update stock
        $newQuantity = $item['stock_quantity'] - $item['quantity'];
        $stockStatus = $newQuantity > 5 ? 'in-stock' : ($newQuantity > 0 ? 'low-stock' : 'out-of-stock');
        
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock_quantity = ?, stock_status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$newQuantity, $stockStatus, $item['product_id']]);
    }
    
    // Clear cart
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'order_number' => $orderNumber,
        'order_id' => $orderId
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>