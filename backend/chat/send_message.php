<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$senderId = requireAuth();

// Get input data
$input = getJsonInput();
$receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : null;
$content = isset($input['content']) ? trim($input['content']) : '';

if (!$receiverId || empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Receiver Not Found or message empty']);
    exit;
}

if ($senderId === $receiverId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot send message to self']);
    exit;
}

try {
    // 1. Verify connection exists (accepted request)
    // Users can only chat if they have an active (accepted) or completed request
    $stmt = $pdo->prepare("
        SELECT request_id FROM requests 
        WHERE status IN ('accepted', 'completed')
        AND (
            (from_user_id = ? AND to_user_id = ?) 
            OR 
            (from_user_id = ? AND to_user_id = ?)
        )
        LIMIT 1
    ");
    $stmt->execute([$senderId, $receiverId, $receiverId, $senderId]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only message users with whom you have an active skill exchange.']);
        exit;
    }

    $pdo->beginTransaction();

    // 2. Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, content, read_status, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$senderId, $receiverId, $content]);
    $messageId = $pdo->lastInsertId();

    // 3. Create notification for receiver
    $senderName = $_SESSION['user_name'] ?? 'A user'; // Ideally fetch name if not in session, but session usually has it or we query it. 
    // Let's query sender name to be safe and clean
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$senderId]);
    $senderName = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id, related_type, action_url, created_at)
        VALUES (?, 'new_message', ?, ?, 'message', ?, NOW())
    ");
    $notifMsg = "New message from $senderName";
    $actionUrl = "messages.html?user_id=$senderId";
    $stmt->execute([$receiverId, $notifMsg, $messageId, $actionUrl]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'timestamp' => date('Y-m-d H:i:s') // processed locally as UTC usually
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>