<?php
require_once __DIR__ . '/../../backend/init.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $data = getJsonInput();

    if (empty($data['username']) && empty($data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required']);
        exit();
    }

    if (empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Password is required']);
        exit();
    }

    // Accept either `username` (email used as username) or `email`
    $login = sanitizeInput($data['username'] ?? $data['email']);
    $password = $data['password'];

    // Find user by email (case-insensitive)
    $stmt = $pdo->prepare('SELECT id, name, email, password, is_admin, is_banned, last_login FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        exit();
    }

    // Verify password (stored in `password` column as hash)
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        exit();
    }

    // Check banned flag
    if (!empty($user['is_banned'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Account is banned']);
        exit();
    }

    // Check user status (new field)
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $statusRow = $stmt->fetch();
    
    if ($statusRow && $statusRow['status'] !== 'active') {
        $statusMsg = $statusRow['status'] === 'suspended' ? 'Account is suspended' : 'Account is deactivated';
        http_response_code(403);
        echo json_encode(['error' => $statusMsg]);
        exit();
    }

    // Regenerate session and set session vars
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['is_admin'] = !empty($user['is_admin']);

    // Update last_login
    $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $stmt->execute([$user['id']]);
    
    // Log login action to audit_logs
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs 
            (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (?, 'user_login', 'user', ?, ?, ?)
        ");
        $details = json_encode([
            'email' => $user['email'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $stmt->execute([
            $user['id'],
            $user['id'],
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Don't fail login if logging fails
        error_log('Failed to log login: ' . $e->getMessage());
    }

    // Get user's full profile data including profile picture
    $stmt = $pdo->prepare('SELECT credits, reputation_score, profile_pic, bio, phone, location, title, website FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $extra = $stmt->fetch();

    $userData = [
        'id' => $user['id'],
        'user_id' => $user['id'], // Include both for compatibility
        'name' => $user['name'],
        'email' => $user['email'],
        'is_admin' => !empty($user['is_admin']),
        'credits' => isset($extra['credits']) ? (int)$extra['credits'] : 0,
        'reputation_score' => isset($extra['reputation_score']) ? (float)$extra['reputation_score'] : 0.0,
        'profile_pic' => $extra['profile_pic'] ?? 'assets/img/default-avatar.png',
        'bio' => $extra['bio'] ?? null,
        'phone' => $extra['phone'] ?? null,
        'location' => $extra['location'] ?? null,
        'title' => $extra['title'] ?? null,
        'website' => $extra['website'] ?? null
    ];

    echo json_encode([
        'ok' => true,
        'message' => 'Login successful',
        'user' => $userData,
        'session' => ['id' => session_id()]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Login PDO error: ' . $e->getMessage());
    echo json_encode(['error' => 'An error occurred during login', 'debug' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Login error: ' . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred', 'debug' => $e->getMessage()]);
}
?>