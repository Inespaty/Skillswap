<?php
require_once __DIR__ . '/../../init.php';

// Auth check
$user_id = requireAuth();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Search users by name or email, excluding current user
    // Limit to 10 results
    $stmt = $pdo->prepare("
        SELECT id, name, profile_pic as avatar, '' as skills 
        FROM users 
        WHERE (name LIKE ? OR email LIKE ?) 
        AND id != ? 
        AND status = 'active'
        LIMIT 10
    ");
    
    $searchTerm = "%{$query}%";
    $stmt->execute([$searchTerm, $searchTerm, $user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Encode avatar URLs properly
    foreach ($users as &$user) {
        if (empty($user['avatar'])) {
            $user['avatar'] = 'assets/img/default-avatar.png';
        }
        // Fetch top skill for context (optional, but good for UI)
        $skillStmt = $pdo->prepare("SELECT title FROM skills WHERE user_id = ? AND active_status = 1 LIMIT 1");
        $skillStmt->execute([$user['id']]);
        $skill = $skillStmt->fetchColumn();
        $user['skills'] = $skill ? $skill : 'No skills listed';
    }

    echo json_encode($users);

} catch (PDOException $e) {
    error_log("Search users error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
