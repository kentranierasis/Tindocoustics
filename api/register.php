<?php
// api/register.php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$full_name = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

// Validation
if (empty($full_name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

try {
    $pdo = getConnection();
    
    // Check if email already exists
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered.']);
        exit;
    }
    
    // Hash password and insert
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare('INSERT INTO customers (full_name, email, password_hash, status) VALUES (?, ?, ?, "active")');
    $stmt->execute([$full_name, $email, $hashedPassword]);
    
    $customerId = $pdo->lastInsertId();
    
    // Auto-login after registration
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_name'] = $full_name;
    $_SESSION['customer_email'] = $email;
    $_SESSION['logged_in'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully!',
        'user' => [
            'id' => $customerId,
            'name' => $full_name,
            'email' => $email
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}