<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$userId = requireAuth();

// Get input
$input = getJsonInput();
$notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : null;
$markAll = isset($input['mark_all']) && $input['mark_all'] === true;

try {
    if ($markAll) {
        // Mark all as read for user
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = ? AND read_status = 0");
        $stmt->execute([$userId]);
        $count = $stmt->rowCount();
        $message = "All notifications marked as read";
    } elseif ($notificationId) {
        // Mark single as read
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        if ($stmt->rowCount() === 0) {
            // Either already read or doesn't belong to user
           // No error needed, just success false or ignored
        }
        $message = "Notification marked as read";
        $count = 1;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID or mark_all required']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'updated_count' => $count
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
