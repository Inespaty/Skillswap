<?php
/**
 * Database Configuration
 * Loads environment variables and establishes database connection
 */

require_once __DIR__ . '/env.php';

// Database configuration from environment variables
$db_host = env('DB_HOST', 'localhost');
$db_name = env('DB_NAME', 'skillswap');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');

// Validate required environment variables
if (empty($db_host) || empty($db_name) || empty($db_user)) {
    error_log("Missing required database environment variables");
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration error. Please try again later.']);
    exit();
}

// Create connection with error handling
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Log the error (in production, log to a file instead of displaying)
    error_log("Database connection failed: " . $e->getMessage());

    // Return a generic error message to the client
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Please try again later.']);
    exit();
}

/**
 * Helper function to execute prepared statements safely
 * @param PDO $pdo Database connection
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return PDOStatement
 * @throws PDOException
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage() . "\nSQL: " . $sql . "\nParams: " . json_encode($params));
        throw $e;
    }
}

/**
 * Sanitize and validate input data
 * @param mixed $data Input data
 * @param string $type Validation type (email, string, int, etc.)
 * @return mixed Sanitized data or false on validation failure
 */
function sanitizeInput($data, $type = 'string') {
    if ($data === null || $data === '') {
        return $data;
    }

    switch ($type) {
        case 'email':
            $data = filter_var($data, FILTER_SANITIZE_EMAIL);
            return filter_var($data, FILTER_VALIDATE_EMAIL) ? $data : false;

        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT);

        case 'string':
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');

        case 'text':
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);

        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
?>