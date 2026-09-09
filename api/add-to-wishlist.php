<?php
// api/add-to-wishlist.php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to wishlist.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'] ?? 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

try {
    $pdo = getConnection();
    $customer_id = $_SESSION['customer_id'];
    
    // Check if product exists
    $stmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ?');
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }
    
    // Check if already in wishlist
    $stmt = $pdo->prepare('SELECT id FROM wishlist_items WHERE customer_id = ? AND product_id = ?');
    $stmt->execute([$customer_id, $product_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Remove from wishlist (toggle)
        $stmt = $pdo->prepare('DELETE FROM wishlist_items WHERE customer_id = ? AND product_id = ?');
        $stmt->execute([$customer_id, $product_id]);
        echo json_encode([
            'success' => true, 
            'action' => 'removed',
            'message' => 'Removed from wishlist.',
            'wishlist_count' => getWishlistCount($pdo, $customer_id)
        ]);
    } else {
        // Add to wishlist
        $stmt = $pdo->prepare('INSERT INTO wishlist_items (customer_id, product_id) VALUES (?, ?)');
        $stmt->execute([$customer_id, $product_id]);
        echo json_encode([
            'success' => true, 
            'action' => 'added',
            'message' => 'Added to wishlist!',
            'wishlist_count' => getWishlistCount($pdo, $customer_id)
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function getWishlistCount($pdo, $customer_id) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist_items WHERE customer_id = ?');
    $stmt->execute([$customer_id]);
    return (int)$stmt->fetchColumn();
}