<?php
// api/login.php
require_once '../config.php';

header('Content-Type: application/json');

// Enable error logging for debugging
error_log("Login attempt started");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

error_log("Login attempt for email: " . $email);

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

try {
    $pdo = getConnection();
    
    // ============================================================
    // STEP 1: Check if this is an ADMIN
    // ============================================================
    $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        // ✅ ADMIN LOGIN SUCCESSFUL
        error_log("Admin login successful for: " . $email);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['user_type'] = 'admin';
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin login successful.',
            'user_type' => 'admin',
            'redirect' => 'admin/adminDashboard.php',
            'user' => [
                'id' => (int)$admin['id'],
                'name' => $admin['full_name'],
                'email' => $admin['email']
            ]
        ]);
        exit;
    }
    
    // ============================================================
    // STEP 2: Check if this is a CUSTOMER
    // ============================================================
    $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, status FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customer && password_verify($password, $customer['password_hash'])) {
        // Check if customer is active
        if ($customer['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact support.']);
            exit;
        }
        
        // ✅ CUSTOMER LOGIN SUCCESSFUL
        error_log("Customer login successful for: " . $email);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['customer_id'] = (int)$customer['id'];
        $_SESSION['customer_name'] = $customer['full_name'];
        $_SESSION['customer_email'] = $customer['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['user_type'] = 'customer';
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'user_type' => 'customer',
            'redirect' => 'index.php',
            'user' => [
                'id' => (int)$customer['id'],
                'name' => $customer['full_name'],
                'email' => $customer['email']
            ]
        ]);
        exit;
    }
    
    // ============================================================
    // STEP 3: No user found
    // ============================================================
    error_log("Login failed - no matching user for: " . $email);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>