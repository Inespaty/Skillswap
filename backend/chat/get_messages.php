<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$userId = requireAuth();

// Get inputs
$otherUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if (!$otherUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // 1. Verify access (must have exchanged messages OR have a valid connection)
    // We strictly check if they are part of a conversation.
    // However, if they are fetching messages, they might be starting a chat. 
    // The previous check in send_message enforced 'requests'. Here we can be slightly more lenient to allow viewing old history,
    // OR enforce the same rule. Let's enforce that they must be the sender or receiver of the messages being fetched.
    // Actually, SQL WHERE clause handles the security perfectly: WHERE (sender = me AND receiver = them) OR (sender = them AND receiver = me).
    
    // 2. Mark messages as read (if I am the receiver)
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET read_status = 1 
        WHERE sender_id = ? AND receiver_id = ? AND read_status = 0
    ");
    $stmt->execute([$otherUserId, $userId]);

    // 3. Fetch messages
    $stmt = $pdo->prepare("
        SELECT 
            message_id,
            sender_id,
            receiver_id,
            content,
            read_status,
            created_at
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
        LIMIT ? OFFSET ?
    ");
    // Note: Usually chat is fetched DESC (newest first) for pagination, but frontend often expects ASC for display.
    // Let's fetch DESC then sorting roughly in valid time order for the limit. 
    // Actually, simple ASC with limit is okay if we assume fetching 'last 50'. 
    // Standardization: Fetch latest 50.
    
    // Proper way for chat pagination: ORDER BY created_at DESC LIMIT 50 -> then reverse in code or frontend.
    
    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT 
                message_id,
                sender_id,
                receiver_id,
                content,
                read_status,
                created_at
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ) sub
        ORDER BY created_at ASC
    ");
    
    $stmt->execute([$userId, $otherUserId, $otherUserId, $userId, $limit, $offset]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>