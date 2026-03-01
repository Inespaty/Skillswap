<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$userId = requireAuth();

// Get input data
$input = getJsonInput();
$requestId = isset($input['request_id']) ? (int)$input['request_id'] : null;
$action = isset($input['action']) ? $input['action'] : ''; // 'accept' or 'reject'

if (!$requestId || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get request details
    $stmt = $pdo->prepare("
        SELECT r.*, s.title as skill_title, u.name as requester_name, u.email as requester_email 
        FROM requests r
        JOIN skills s ON r.skill_id = s.skill_id
        JOIN users u ON r.from_user_id = u.id
        WHERE r.request_id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        throw new Exception('Request not found');
    }

    // 2. Verify user is the recipient
    if ($request['to_user_id'] !== $userId) {
        http_response_code(403);
        throw new Exception('Unauthorized access');
    }

    // 3. Check status is pending
    if ($request['status'] !== 'pending') {
        throw new Exception('Request is already processed');
    }

    // 4. Update status
    $newStatus = ($action === 'accept') ? 'accepted' : 'rejected';
    $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE request_id = ?");
    $stmt->execute([$newStatus, $requestId]);

    // 5. Notify requester
    $notifMsg = ($action === 'accept') 
        ? "Great news! Your request for '{$request['skill_title']}' has been accepted."
        : "Your request for '{$request['skill_title']}' was declined.";
    
    $notifType = ($action === 'accept') ? 'request_accepted' : 'request_rejected';
    // If accepted, link to messages page with the requester's user_id for easy messaging
    $actionUrl = ($action === 'accept') ? "messages.html?user_id=" . $request['from_user_id'] : "requests.html";

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id, related_type, action_url, created_at)
        VALUES (?, ?, ?, ?, 'request', ?, NOW())
    ");
    $stmt->execute([$request['from_user_id'], $notifType, $notifMsg, $requestId, $actionUrl]);

    // 6. Send Email Notification
    require_once __DIR__ . '/../helpers/email.php';
    $subject = "Request Update: " . $request['skill_title'];
    $body = "
        <h3>Request " . ucfirst($newStatus) . "</h3>
        <p>Your request for <strong>{$request['skill_title']}</strong> has been <strong>$newStatus</strong>.</p>
        <p>" . ($action === 'accept' ? "You can now coordinate with the user." : "Keep browsing for other skills!") . "</p>
        <a href='http://localhost:8080/$actionUrl' class='btn'>View Details</a>
    ";
    sendEmail($request['requester_email'], $subject, $body);

    // 6. Log audit
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $userId, "request_$newStatus", 'request', $requestId, ['skill_title' => $request['skill_title']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Request $newStatus successfully",
        'status' => $newStatus
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>