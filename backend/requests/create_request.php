<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$requesterId = requireAuth();

// Get input data
$input = getJsonInput();
$skillId = isset($input['skill_id']) ? (int)$input['skill_id'] : null;
$hours = isset($input['hours']) ? (int)$input['hours'] : 1;
$note = isset($input['note']) ? trim($input['note']) : '';

if (!$skillId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Skill ID is required']);
    exit;
}

if ($hours < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Hours must be at least 1']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get skill details and owner
    $stmt = $pdo->prepare("
        SELECT s.skill_id, s.title, s.user_id, s.active_status, s.approval_status, u.email, u.name 
        FROM skills s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.skill_id = ?
    ");
    $stmt->execute([$skillId]);
    $skill = $stmt->fetch();

    if (!$skill) {
        throw new Exception('Skill not found');
    }

    if ($skill['user_id'] === $requesterId) {
        throw new Exception('You cannot request your own skill');
    }

    if ($skill['active_status'] != 1 || $skill['approval_status'] !== 'approved') {
        throw new Exception('This skill is not currently available');
    }

    // 2. Check if requester has sufficient credits
    $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt->execute([$requesterId]);
    $requester = $stmt->fetch();

    if ($requester['credits'] < $hours) {
        throw new Exception("Insufficient credits. You have {$requester['credits']} credits, but this request requires $hours.");
    }

    // 3. Check for existing active (pending or accepted) requests for same skill/user
    // Allow re-requesting if previous request was completed, rejected, or cancelled
    // Users can request the same skill again after completing and paying credits
    $stmt = $pdo->prepare("
        SELECT request_id, status 
        FROM requests 
        WHERE skill_id = ? 
        AND from_user_id = ? 
        AND status IN ('pending', 'accepted')
    ");
    $stmt->execute([$skillId, $requesterId]);
    $existingRequest = $stmt->fetch();
    if ($existingRequest) {
        $statusMsg = $existingRequest['status'] === 'pending' 
            ? 'pending' 
            : 'accepted (in progress)';
        throw new Exception("You already have a $statusMsg request for this skill. Please wait for it to be completed before requesting again.");
    }

    // 4. Create request
    $stmt = $pdo->prepare("
        INSERT INTO requests (skill_id, from_user_id, to_user_id, hours_required, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$skillId, $requesterId, $skill['user_id'], $hours]);
    $requestId = $pdo->lastInsertId();

    // 5. Create notification for skill owner
    $message = "You have a new request for your skill '{$skill['title']}' from a user.";
    if ($note) {
        $message .= " Note: $note";
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id, related_type, action_url, created_at)
        VALUES (?, 'new_request', ?, ?, 'request', 'requests.html', NOW())
    ");
    $stmt->execute([$skill['user_id'], $message, $requestId]);

    // 6. Send Email Notification
    require_once __DIR__ . '/../helpers/email.php';
    $emailSubject = "New Skill Request: " . $skill['title'];
    $emailBody = "
        <h3>New Request Received</h3>
        <p><strong>{$_SESSION['name']}</strong> has requested a session for your skill <strong>{$skill['title']}</strong>.</p>
        <p><strong>Hours Requested:</strong> $hours</p>
        " . ($note ? "<p><strong>Note:</strong> " . htmlspecialchars($note) . "</p>" : "") . "
        <a href='http://localhost:8080/requests.html' class='btn'>View Request</a>
    ";
    sendEmail($skill['email'], $emailSubject, $emailBody);

    // 6. Log audit event
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $requesterId, 'request_created', 'request', $requestId, [
        'skill_id' => $skillId, 
        'skill_title' => $skill['title'],
        'hours' => $hours
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Request sent successfully',
        'request_id' => $requestId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request for validation errors
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
