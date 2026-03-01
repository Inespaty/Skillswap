<?php
require_once __DIR__ . '/../init.php';

// Enable DEV debug output in JSON responses (set to false in production)
$DEBUG = true;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

/**
 * Validate student email
 * Must end with .edu or be from approved university domains
 */
function isValidStudentEmail($email) {
    // List of approved university email domains
    $approvedDomains = [
        '.edu',           // General educational institutions
        '.ac.za',         // South African universities
        '.edu.eg',        // Egyptian universities
        '.edu.sa',        // Saudi universities
        'student.com',    // Example student domain
        'gmail.com',      // Allowed for students without university emails (Rwanda context)
    ];
    
    $email = strtolower($email);
    foreach ($approvedDomains as $domain) {
        if (strpos($email, $domain) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Log audit event
 */
function logAudit($pdo, $userId, $action, $entityType = null, $entityId = null, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $entityType, $entityId, $details, $ip]);
    } catch (PDOException $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

try {
    $data = getJsonInput();

    // Required fields
    $required = ['name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "$field is required"]);
            exit();
        }
    }

    $name = sanitizeInput($data['name']);
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit();
    }

    // Validate student email
    if (!isValidStudentEmail($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Please use a valid student email address (e.g., ending with .edu)']);
        exit();
    }

    // Password strength check
    if (strlen($password) < 8 || 
        !preg_match('/[A-Za-z]/', $password) || 
        !preg_match('/[0-9]/', $password)) 
    {
        http_response_code(400);
        echo json_encode([
            'error' => 'Password must be at least 8 characters long and contain both letters and numbers'
        ]);
        exit();
    }

    // Check if email already exists (case-insensitive check)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already exists']);
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user with all new fields
    $stmt = $pdo->prepare("INSERT INTO users 
        (name, email, password, credits, reputation_score, profile_pic, status, email_verified, is_admin, is_banned, created_at, updated_at)
        VALUES (?, ?, ?, 10, 0.00, 'default-avatar.png', 'active', 0, 0, 0, NOW(), NOW())");
    $stmt->execute([$name, $email, $hashedPassword]);

    $userId = $pdo->lastInsertId();

    // Log registration event
    logAudit($pdo, $userId, 'user_registered', 'user', $userId, json_encode(['email' => $email, 'name' => $name]));

    // Do NOT set session - user must login after registration
    // This ensures users cannot access dashboard without explicitly logging in

    // Success response
    http_response_code(201);
    echo json_encode([
        'message' => 'Registration successful',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'credits' => 10,
            'reputation_score' => 0.00,
            'is_admin' => false
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Registration PDO error: ' . $e->getMessage());
    $resp = ['error' => 'An error occurred during registration'];
    if (!empty($DEBUG)) $resp['debug'] = $e->getMessage();
    echo json_encode($resp);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Registration error: ' . $e->getMessage());
    $resp = ['error' => 'An unexpected error occurred'];
    if (!empty($DEBUG)) $resp['debug'] = $e->getMessage();
    echo json_encode($resp);
}
?>
