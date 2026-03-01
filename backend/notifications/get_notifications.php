<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$userId = requireAuth();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

try {
    $whereClause = "user_id = ?";
    $params = [$userId];

    if ($unreadOnly) {
        $whereClause .= " AND read_status = 0";
    }

    // Get count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Get unread count specifically
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetchColumn();

    // Get notifications
    $stmt = $pdo->prepare("
        SELECT * 
        FROM notifications 
        WHERE $whereClause
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    
    // Bind limit/offset as integers for stricter PDO drivers or just append them if emulation is on.
    // PDO emulation usually handles strings in limit fine, but let's be safe if possible or just rely on standard execute params if driver allows.
    // Actually, PDO bindParam is needed for limits in some drivers (MySQL usually fine with string literals in execute if emulation on, but LIMIT ? can be tricky).
    // Let's use direct query string for limit/offset to be universally safe with simple PDO wrappers, or bind properly.
    // Since we validated int, safe to embed.
    
    $sql = "
        SELECT notification_id, type, message, read_status, action_url, created_at, related_id, related_type
        FROM notifications 
        WHERE $whereClause
        ORDER BY created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int)$unreadCount,
        'total' => (int)$total
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>