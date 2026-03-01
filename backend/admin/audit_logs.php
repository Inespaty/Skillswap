<?php
require_once __DIR__ . '/../init.php';

// Check if user is admin
requireAdmin();

// Get pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// Get filter parameters
$action = isset($_GET['action']) ? $_GET['action'] : null;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : null; // e.g. 'user', 'skill'

$where = ["1=1"];
$params = [];

if ($action) {
    $where[] = "a.action = ?";
    $params[] = $action;
}

if ($userId) {
    $where[] = "a.user_id = ?";
    $params[] = $userId;
}

if ($entityType) {
    $where[] = "a.entity_type = ?";
    $params[] = $entityType;
}

$whereClause = implode(" AND ", $where);

try {
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a WHERE $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    // Get logs with details
    $sql = "
        SELECT 
            a.log_id,
            a.user_id,
            a.action,
            a.entity_type,
            a.entity_id,
            a.details,
            a.ip_address,
            a.created_at,
            u.name as user_name,
            u.email as user_email
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $whereClause
        ORDER BY a.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => (int)$total,
            'has_next' => $page < $totalPages
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load logs: ' . $e->getMessage()]);
}
?>