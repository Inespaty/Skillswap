<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$userId = requireAuth();

// Get inputs
$type = isset($_GET['type']) && $_GET['type'] === 'sent' ? 'sent' : 'received';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

// Filter by user role in request
if ($type === 'sent') {
    $where[] = "r.from_user_id = ?";
    $params[] = $userId;
} else {
    $where[] = "r.to_user_id = ?";
    $params[] = $userId;
}

// Filter by status
if ($status !== 'all') {
    $where[] = "r.status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

try {
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests r WHERE $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    // Get requests
    // Note: The requests table doesn't have a 'note' column - notes are only in notifications
    $sql = "
        SELECT 
            r.request_id, 
            r.status, 
            r.created_at, 
            r.hours_required,
            r.from_user_id,
            r.to_user_id,
            r.completed_by_requester,
            r.completed_by_helper,
            r.requester_confirmed_at,
            s.skill_id, 
            s.title as skill_title, 
            s.image as skill_image,
            u.id as other_user_id, 
            u.name as other_user_name, 
            u.profile_pic as other_user_pic,
            u.profile_pic as other_user_avatar,
            u.email as other_user_email
        FROM requests r
        JOIN skills s ON r.skill_id = s.skill_id
    ";

    if ($type === 'sent') {
        // If sent, join recipient info
        $sql .= " JOIN users u ON r.to_user_id = u.id";
    } else {
        // If received, join sender info
        $sql .= " JOIN users u ON r.from_user_id = u.id";
    }

    $sql .= " WHERE $whereClause ORDER BY r.created_at DESC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure requests is always an array
    if (!is_array($requests)) {
        $requests = [];
    }

    // Cast boolean/integer fields to ensure correct types in JSON
    foreach ($requests as &$req) {
        $req['completed_by_helper'] = (int)$req['completed_by_helper'];
        $req['completed_by_requester'] = (int)$req['completed_by_requester'];
        $req['request_id'] = (int)$req['request_id'];
        $req['from_user_id'] = (int)$req['from_user_id'];
        $req['to_user_id'] = (int)$req['to_user_id'];
        $req['skill_id'] = (int)$req['skill_id'];
        $req['hours_required'] = (int)$req['hours_required'];
    }
    unset($req); // Break reference

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'limit' => $limit
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>
