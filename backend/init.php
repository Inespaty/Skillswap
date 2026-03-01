<?php
// Set headers for CORS and JSON content type
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // ini_set('session.cookie_secure', 1); // Enable in production with HTTPS
    session_start();
}

// Include database connection
require_once __DIR__ . '/db.php';

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone
date_default_timezone_set('UTC');

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Security Helpers
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/rate_limit.php';

// Rate Limiting
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// Standard limit: 100 req/min
if (!checkRateLimit($pdo, $ip_address, 'global', 100, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again later.']);
    exit();
}

// Stricter limit for auth endpoints
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, 'login.php') !== false || strpos($uri, 'register.php') !== false) {
    if (!checkRateLimit($pdo, $ip_address, 'auth', 10, 60)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many login attempts. Please wait.']);
        exit();
    }
}

// CSRF Protection for state-changing requests
// Exempt registration and login endpoints since new users don't have CSRF tokens yet
$exemptPaths = ['/auth/register.php', '/auth/login.php'];
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isExempt = false;

foreach ($exemptPaths as $exemptPath) {
    if (strpos($currentPath, $exemptPath) !== false) {
        $isExempt = true;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS' && !$isExempt) {
    if (!verifyCsrfToken()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or missing CSRF token']);
        exit();
    }
}

// Helper function to get authorization header
function getAuthorizationHeader() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(
            array_map('ucwords', array_keys($requestHeaders)),
            array_values($requestHeaders)
        );
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

// Get access token from header
function getBearerToken() {
    $headers = getAuthorizationHeader();
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    return null;
}

// Verify user is authenticated and return user data
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit();
    }

    $userId = (int)$_SESSION['user_id'];

    // Verify user still exists and is not banned
    try {
        $stmt = $GLOBALS['pdo']->prepare('SELECT id, name, email, is_admin, is_banned FROM users WHERE id = ? AND is_banned = 0');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            // User doesn't exist or is banned, destroy session
            session_destroy();
            http_response_code(401);
            echo json_encode(['error' => 'Account not found or suspended']);
            exit();
        }

        // Update session with latest data
        $_SESSION['is_admin'] = !empty($user['is_admin']);

        return $userId;
    } catch (PDOException $e) {
        error_log("Auth verification failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Authentication verification failed']);
        exit();
    }
}

// Verify admin access
function requireAdmin() {
    $userId = requireAuth();

    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit();
    }

    return $userId;
}

// Input validation and sanitization functions
// Note: sanitizeInput is now defined in db.php for consistency

/**
 * Validate required fields
 * @param array $data Input data
 * @param array $required Required field names
 * @return array Validation errors
 */
function validateRequired($data, $required) {
    $errors = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[] = ucfirst($field) . ' is required';
        }
    }
    return $errors;
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate string length
 * @param string $str
 * @param int $min
 * @param int $max
 * @return bool
 */
function validateLength($str, $min = 1, $max = 255) {
    $len = strlen(trim($str));
    return $len >= $min && $len <= $max;
}

/**
 * Validate integer
 * @param mixed $value
 * @param int $min
 * @param int $max
 * @return bool
 */
function validateInt($value, $min = 0, $max = PHP_INT_MAX) {
    if (!is_numeric($value)) return false;
    $int = (int)$value;
    return $int >= $min && $int <= $max;
}

/**
 * Validate file upload
 * @param array $file $_FILES array element
 * @param array $allowedTypes Allowed MIME types
 * @param int $maxSize Max file size in bytes
 * @return array Errors or empty array if valid
 */
function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5242880) {
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }

    if ($file['size'] > $maxSize) {
        $errors[] = 'File too large (max ' . ($maxSize / 1024 / 1024) . 'MB)';
    }

    // Detect MIME type with fallback methods
    $mimeType = null;
    
    // Try finfo_open() first (requires fileinfo extension)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
    // Fallback to mime_content_type() if available
    elseif (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    }
    // Last resort: check file extension (less secure but better than nothing)
    else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extensionMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mimeType = $extensionMap[$ext] ?? null;
    }

    if ($mimeType && !in_array($mimeType, $allowedTypes)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
    } elseif (!$mimeType) {
        $errors[] = 'Could not determine file type.';
    }

    return $errors;
}

/**
 * Generate secure filename
 * @param string $filename
 * @return string
 */
function generateSecureFilename($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $name);
    return $safeName . '_' . time() . '.' . $ext;
}

// Get JSON input from request body
function getJsonInput() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit();
    }
    
    return $data;
}
?>