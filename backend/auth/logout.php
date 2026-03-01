<?php
require_once __DIR__ . '/../init.php';

// Log logout action before destroying session
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../helpers/audit.php';
        logAudit($pdo, $_SESSION['user_id'], 'user_logout', 'user', $_SESSION['user_id'], null);
    } catch (Exception $e) {
        error_log('Failed to log logout: ' . $e->getMessage());
    }
}

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Return success response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Successfully logged out',
    'redirect' => '/login.html' // Adjust this path as needed
]);
