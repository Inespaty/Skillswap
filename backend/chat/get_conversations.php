<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$userId = requireAuth();

try {
    // Get conversations from two sources:
    // 1. Users with existing messages
    // 2. Users with accepted/completed requests (even if no messages yet)
    
    // Use UNION to combine users with messages and users with accepted requests
    $sql = "
        (
            SELECT DISTINCT
                u.id as user_id,
                u.id as other_user_id,
                u.name, 
                u.profile_pic,
                m.content as last_message,
                m.created_at as last_message_time,
                m.sender_id as last_message_sender,
                COALESCE((
                    SELECT COUNT(*) 
                    FROM messages 
                    WHERE sender_id = u.id AND receiver_id = ? AND read_status = 0
                ), 0) as unread_count
            FROM users u
            INNER JOIN messages m ON (
                (m.sender_id = ? AND m.receiver_id = u.id) 
                OR 
                (m.sender_id = u.id AND m.receiver_id = ?)
            )
            WHERE m.message_id IN (
                SELECT MAX(message_id) 
                FROM messages 
                WHERE (sender_id = ? OR receiver_id = ?)
                GROUP BY CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END
            )
            AND u.id != ?
        )
        UNION
        (
            SELECT DISTINCT
                u.id as user_id,
                u.id as other_user_id,
                u.name, 
                u.profile_pic,
                NULL as last_message,
                NULL as last_message_time,
                NULL as last_message_sender,
                0 as unread_count
            FROM users u
            INNER JOIN requests r ON (
                r.status IN ('accepted', 'completed')
                AND (
                    (r.from_user_id = ? AND r.to_user_id = u.id)
                    OR
                    (r.from_user_id = u.id AND r.to_user_id = ?)
                )
            )
            WHERE u.id != ?
            AND NOT EXISTS (
                SELECT 1 FROM messages m2 
                WHERE (m2.sender_id = ? AND m2.receiver_id = u.id)
                   OR (m2.sender_id = u.id AND m2.receiver_id = ?)
            )
        )
        ORDER BY COALESCE(last_message_time, '1970-01-01') DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        // First SELECT (messages)
        $userId, // unread_count
        $userId, // join condition 1
        $userId, // join condition 2
        $userId, // subquery 1
        $userId, // subquery 2
        $userId, // case logic
        $userId, // exclude self
        // Second SELECT (requests without messages)
        $userId, // request check 1
        $userId, // request check 2
        $userId, // exclude self
        $userId, // NOT EXISTS check 1
        $userId  // NOT EXISTS check 2
    ]);
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If conversation has no messages, set last_message to empty
    foreach ($conversations as &$conv) {
        if (!$conv['last_message']) {
            $conv['last_message'] = '';
            $conv['last_message_time'] = null;
        }
    }

    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
