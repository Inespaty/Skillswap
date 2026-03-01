<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// This endpoint is meant to be called internally by other scripts
// It doesn't require session check as it's not directly accessible

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? (int)$input['user_id'] : null;
$type = isset($input['type']) ? $input['type'] : '';
$message = isset($input['message']) ? trim($input['message']) : '';
$related_id = isset($input['related_id']) ? (int)$input['related_id'] : null;
$related_type = isset($input['related_type']) ? $input['related_type'] : null;

if (!$user_id || !$type || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO Notifications 
        (user_id, type, message, related_id, related_type, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $type,
        $message,
        $related_id ?: null,
        $related_type
    ]);
    
    $notification_id = $pdo->lastInsertId();
    
    // Get the full notification
    $stmt = $pdo->prepare("
        SELECT n.*, 
               u.full_name as sender_name,
               u.profile_pic as sender_avatar
        FROM Notifications n
        LEFT JOIN Users u ON n.related_type = 'user' AND n.related_id = u.user_id
        WHERE n.notification_id = ?
    ");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the notification for the response
    $response = [
        'id' => $notification['notification_id'],
        'type' => $notification['type'],
        'message' => $notification['message'],
        'is_read' => (bool)$notification['read_status'],
        'created_at' => $notification['created_at'],
        'related_id' => $notification['related_id'],
        'related_type' => $notification['related_type'],
        'sender' => $notification['related_type'] === 'user' ? [
            'id' => $notification['related_id'],
            'name' => $notification['sender_name'],
            'avatar' => $notification['sender_avatar']
        ] : null
    ];
    
    echo json_encode([
        'success' => true,
        'notification' => $response
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to create notification: ' . $e->getMessage()
    ]);
}