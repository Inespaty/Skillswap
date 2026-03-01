<?php
/**
 * Create Admin Account Script
 * Run this file once in your browser: http://localhost:8080/create_admin.php
 * Then DELETE this file for security
 */
require_once '../backend/db.php';

// Admin details
$name = 'System Administrator';
$email = 'admin@skillswap.com';
$password = 'Admin@123'; // Change this to your desired password
$credits = 100;

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo "<h2>Admin account already exists!</h2>";
        echo "<p>Email: $email</p>";
        echo "<p>If you forgot the password, you can change it in the database.</p>";
        exit;
    }
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert admin user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name, 
            email, 
            password, 
            credits, 
            is_admin, 
            email_verified, 
            status,
            created_at
        ) VALUES (?, ?, ?, ?, 1, 1, 'active', NOW())
    ");
    
    $stmt->execute([
        $name,
        $email,
        $hashedPassword,
        $credits
    ]);
    
    echo "<h2 style='color: green;'>✓ Admin Account Created Successfully!</h2>";
    echo "<div style='background: #f0f0f0; padding: 20px; border-radius: 8px; max-width: 500px;'>";
    echo "<h3>Login Credentials:</h3>";
    echo "<p><strong>Email:</strong> $email</p>";
    echo "<p><strong>Password:</strong> $password</p>";
    echo "<hr>";
    echo "<p style='color: red;'><strong>IMPORTANT:</strong></p>";
    echo "<ol>";
    echo "<li>Change your password after first login</li>";
    echo "<li><strong>DELETE this file (create_admin.php) immediately for security!</strong></li>";
    echo "</ol>";
    echo "</div>";
    echo "<br><a href='login.html' style='padding: 10px 20px; background: #C11C84; color: white; text-decoration: none; border-radius: 5px;'>Go to Login</a>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Error Creating Admin Account</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
