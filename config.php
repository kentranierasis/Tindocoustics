<?php
// Start session if not already started 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getConnection(): PDO
{
    $host = 'localhost';
    $db   = 'tindoc_db';
    $user = 'root';
    $pass = '';
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}


// Check if user is logged in 
function isLoggedIn(): bool
{
    return isset($_SESSION['customer_id']) || isset($_SESSION['admin_id']);
}

// Get current user type 
function getUserType(): ?string
{
    if (isset($_SESSION['customer_id'])) return 'customer';
    if (isset($_SESSION['admin_id'])) return 'admin';
    return null;
} 

// Get current user ID
function getCurrentUserId(): ?int
{
    return $_SESSION['customer_id'] ?? $_SESSION['admin_id'] ?? null;
}

// Get current user name
function getCurrentUserName(): ?string
{
    return $_SESSION['customer_name'] ?? $_SESSION['admin_name'] ?? null;
}

// Get current user email
function getCurrentUserEmail(): ?string
{
    return $_SESSION['customer_email'] ?? $_SESSION['admin_email'] ?? null;
}

    // FORMATTING HELPER FUNCTIONS
    

// Format price with ₱ symbol (for frontend)
function formatPrice($amount): string
{
    return '₱' . number_format((float)$amount, 0);
}

// Format price with ₱ symbol (for admin)
function peso($amount) {
    return '&#8369;' . number_format((float)$amount, 0);
}

// Get stock status label
function getStockLabel($status): string
{
    return match ($status) {
        'in-stock' => 'In Stock',
        'low-stock' => 'Low Stock',
        'out-of-stock' => 'Out of Stock',
        default => ucfirst($status),
    };
}

// Get stock status CSS class
function getStockClass($status): string
{
    return match ($status) {
        'in-stock' => 'in-stock',
        'low-stock' => 'low-stock',
        'out-of-stock' => 'out-of-stock',
        default => '',
    };
}

// Get initials from name (for avatars)
function initialsFromName($name) {
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

// Format time ago
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

// CART HELPER FUNCTIONS


// Get cart count for current user
function getCartCount(): int
{
    if (!isset($_SESSION['customer_id'])) {
        return 0;
    }
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart_items WHERE customer_id = ?");
        $stmt->execute([$_SESSION['customer_id']]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// Get wishlist count for current user
function getWishlistCount(): int
{
    if (!isset($_SESSION['customer_id'])) {
        return 0;
    }
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist_items WHERE customer_id = ?");
        $stmt->execute([$_SESSION['customer_id']]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
?>