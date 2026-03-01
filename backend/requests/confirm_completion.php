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
        SELECT r.*, s.title as skill_title
        FROM requests r
        JOIN skills s ON r.skill_id = s.skill_id
        WHERE r.request_id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        throw new Exception('Request not found');
    }

    // 2. Verify user is the REQUESTER
    if ($userId !== $request['from_user_id']) {
        http_response_code(403);
        throw new Exception('Only the requester can confirm completion');
    }

    // 3. Verify request is in correct state
    if ($request['status'] !== 'accepted') {
        throw new Exception('Request must be in accepted status');
    }

    if (!$request['completed_by_helper']) {
        throw new Exception('The helper has not marked this request as complete yet');
    }

    if ($request['requester_confirmed_at']) {
        throw new Exception('You have already confirmed this completion');
    }

    // 4. Process credit transfer
    $credits = $request['hours_required'] ?? 1;
    $requesterId = $request['from_user_id'];
    $helperId = $request['to_user_id'];

    // Check requester balance
    $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt->execute([$requesterId]);
    $requesterBalance = $stmt->fetchColumn();

    if ($requesterBalance < $credits) {
        throw new Exception("Insufficient credits. You have $requesterBalance credits, but this request requires $credits.");
    }

    // Deduct from requester
    $stmt = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?");
    $stmt->execute([$credits, $requesterId]);

    // Add to helper
    $stmt = $pdo->prepare("UPDATE users SET credits = credits + ?, reputation_score = reputation_score + 0.1 WHERE id = ?");
    $stmt->execute([$credits, $helperId]);

    // 5. Update request status
    $stmt = $pdo->prepare("
        UPDATE requests 
        SET status = 'completed', 
            requester_confirmed_at = NOW(), 
            updated_at = NOW() 
        WHERE request_id = ?
    ");
    $stmt->execute([$requestId]);

    // 6. Record transaction
    $stmt = $pdo->prepare("
        INSERT INTO transactions (from_user_id, to_user_id, request_id, credits, type, status, description, created_at)
        VALUES (?, ?, ?, ?, 'skill_exchange', 'completed', ?, NOW())
    ");
    $stmt->execute([$requesterId, $helperId, $requestId, $credits, "Exchanged skill: {$request['skill_title']}"]);

    // 7. Send notifications
    $msgRequester = "You confirmed completion of '{$request['skill_title']}'. $credits credits have been transferred. Please rate your experience!";
    $msgHelper = "The requester confirmed completion of '{$request['skill_title']}'. You received $credits credits. Please rate your experience!";

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id, related_type, action_url, created_at) 
        VALUES (?, 'request_completed', ?, ?, 'request', 'requests.html', NOW())
    ");
    $stmt->execute([$requesterId, $msgRequester, $requestId]);
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id, related_type, action_url, created_at) 
        VALUES (?, 'request_completed', ?, ?, 'request', 'requests.html', NOW())
    ");
    $stmt->execute([$helperId, $msgHelper, $requestId]);

    // 8. Audit Log
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $userId, 'request_confirmed_completed', 'request', $requestId, ['credits' => $credits]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Completion confirmed! $credits credits transferred.",
        'credits_transferred' => $credits,
        'can_rate' => true
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
