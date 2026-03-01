<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token and store it in session
 * @return string The generated token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from request header
 * @return bool True if valid, False otherwise
 */
function verifyCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $token = isset($headers['x-csrf-token']) ? $headers['x-csrf-token'] : null;

    if (!$token && isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }

    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

// Ensure getallheaders exists (polyfill for nginx/fpm if needed)
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
?>
