<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$userId = requireAuth();

// Get input data
$input = getJsonInput();
$requestId = isset($input['request_id']) ? (int)$input['request_id'] : null;

if (!$requestId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Request ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get request details
    $stmt = $pdo->prepare("
        SELECT r.*, s.title as skill_title, s.user_id as skill_owner_id
        FROM requests r
        JOIN skills s ON r.skill_id = s.skill_id
        WHERE r.request_id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        throw new Exception('Request not found');
    }

    if ($request['status'] !== 'accepted') {
        throw new Exception('Request must be accepted to be marked as complete');
    }

    // 2. Verify user is the HELPER (skill owner / to_user_id)
    $isHelper = ($userId === $request['to_user_id']);

    if (!$isHelper) {
        http_response_code(403);
        throw new Exception('Only the helper can mark the request as complete');
    }

    // 3. Check if already marked complete by helper
    if ($request['completed_by_helper']) {
        throw new Exception('You have already marked this request as complete');
    }

    // 4. Mark as completed by helper
    $stmt = $pdo->prepare("UPDATE requests SET completed_by_helper = 1, updated_at = NOW() WHERE request_id = ?");
    $stmt->execute([$requestId]);

    // 5. Notify the requester to confirm completion
    $requesterId = $request['from_user_id'];
    $notifMsg = "The helper has marked the skill exchange '{$request['skill_title']}' as complete. Please review and confirm completion to release payment.";
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id, related_type, action_url, created_at) 
        VALUES (?, 'request_update', ?, ?, 'request', 'requests.html', NOW())
    ");
    $stmt->execute([$requesterId, $notifMsg, $requestId]);

    // 6. Audit Log
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $userId, 'request_marked_complete', 'request', $requestId, ['role' => 'helper']);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Request marked as complete. Waiting for requester confirmation.',
        'awaiting_confirmation' => true
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>