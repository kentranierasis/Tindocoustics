<?php
// api/add-to-cart.php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to cart.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'] ?? 0;
$quantity = max(1, intval($data['quantity'] ?? 1));

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

try {
    $pdo = getConnection();
    $customer_id = $_SESSION['customer_id'];
    
    // Check if product exists and has stock
    $stmt = $pdo->prepare('SELECT id, name, stock_quantity, stock_status FROM products WHERE id = ?');
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }
    
    // Check if enough stock
    if ($product['stock_status'] === 'out-of-stock' || $product['stock_quantity'] < $quantity) {
        echo json_encode([
            'success' => false, 
            'message' => 'Sorry, only ' . $product['stock_quantity'] . ' left in stock.'
        ]);
        exit;
    }
    
    // Check if item already in cart
    $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE customer_id = ? AND product_id = ?');
    $stmt->execute([$customer_id, $product_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $newQty = $existing['quantity'] + $quantity;
        // Check if new quantity exceeds stock
        if ($newQty > $product['stock_quantity']) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot add more. Only ' . $product['stock_quantity'] . ' left in stock.'
            ]);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
        $stmt->execute([$newQty, $existing['id']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (?, ?, ?)');
        $stmt->execute([$customer_id, $product_id, $quantity]);
    }
    
    echo json_encode(['success' => true, 'message' => $product['name'] . ' added to cart!']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}