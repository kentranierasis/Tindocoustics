<?php
// test_login.php
require_once 'config.php';

$email = 'keken@gmail.com';  // CHANGE THIS
$password = 'qwerty';         // CHANGE THIS

echo "Testing login for: " . $email . "<br><br>";

try {
    $pdo = getConnection();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, status FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ User not found!<br>";
    } else {
        echo "✅ User found!<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Name: " . $user['full_name'] . "<br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "Status: " . $user['status'] . "<br>";
        echo "Password Hash: " . $user['password_hash'] . "<br><br>";
        
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            echo "✅ Password is CORRECT!<br>";
            echo "You should be able to login.";
        } else {
            echo "❌ Password is INCORRECT!<br>";
            echo "The password you entered doesn't match the hash in the database.<br><br>";
            
            // Check if hash is valid
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                echo "⚠️ Password hash needs rehashing.<br>";
            }
            
            // Generate a new hash for comparison
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "New hash for your password: " . $new_hash . "<br>";
            echo "If you want to reset your password, use this hash in the database.";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage();
}
?>