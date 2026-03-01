<?php
/**
 * Update Admin Password Script
 * Run this file once in your browser: http://localhost:8080/update_admin_password.php
 * Then DELETE this file for security
 */

require_once '../backend/db.php';

// New password
$email = 'admin@skillswap.com';
$newPassword = 'Admin@123'; // Change this if you want a different password

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "<h2 style='color: red;'>Admin account not found!</h2>";
        echo "<p>Email: $email</p>";
        exit;
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashedPassword, $email]);
    
    echo "<h2 style='color: green;'>✓ Admin Password Updated Successfully!</h2>";
    echo "<div style='background: #f0f0f0; padding: 20px; border-radius: 8px; max-width: 500px;'>";
    echo "<h3>Updated Login Credentials:</h3>";
    echo "<p><strong>Name:</strong> " . htmlspecialchars($admin['name']) . "</p>";
    echo "<p><strong>Email:</strong> $email</p>";
    echo "<p><strong>Password:</strong> $newPassword</p>";
    echo "<hr>";
    echo "<p style='color: red;'><strong>IMPORTANT:</strong></p>";
    echo "<ol>";
    echo "<li>Change your password after first login</li>";
    echo "<li><strong>DELETE this file (update_admin_password.php) immediately for security!</strong></li>";
    echo "</ol>";
    echo "</div>";
    echo "<br><a href='login.html' style='padding: 10px 20px; background: #C11C84; color: white; text-decoration: none; border-radius: 5px;'>Go to Login</a>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Error Updating Password</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
