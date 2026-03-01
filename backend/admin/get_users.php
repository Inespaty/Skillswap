<?php
require_once __DIR__ . '/../init.php';

// Auth check
$user_id = requireAuth();
requireAdmin($user_id);

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;

    // Build query
    $sql = "SELECT id, name, email, profile_pic, credits, reputation_score, is_admin, is_banned, status, created_at, last_login 
            FROM users 
            WHERE 1=1";
    $params = [];

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    if ($search) {
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Count total
    $countSql = str_replace("SELECT id, name, email, profile_pic, credits, reputation_score, is_admin, is_banned, status, created_at, last_login", "SELECT COUNT(*)", $sql);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Fetch users
    $sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data
    foreach ($users as &$user) {
        if (empty($user['profile_pic'])) {
            $user['profile_pic'] = 'assets/img/default-avatar.svg';
        }
        $user['is_admin'] = (bool)$user['is_admin'];
        $user['is_banned'] = (bool)$user['is_banned'];
    }

    echo json_encode([
        'success' => true,
        'users' => $users,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Get users error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
