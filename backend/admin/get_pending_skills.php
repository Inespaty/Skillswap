<?php
require_once __DIR__ . '/../init.php';

// Auth check
$user_id = requireAuth();
requireAdmin($user_id);

try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Fetch pending skills
    $sql = "SELECT s.*, COALESCE(u.name, 'Deleted User') as user_name, c.category_name
            FROM skills s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN skill_categories c ON s.category_id = c.category_id
            WHERE s.approval_status = 'pending'
            ORDER BY s.created_at ASC
            LIMIT $limit OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM skills WHERE approval_status = 'pending'");
    $stmt->execute();
    $total = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'skills' => $skills,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Get pending skills error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
