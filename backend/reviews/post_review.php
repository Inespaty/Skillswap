<?php
require_once __DIR__ . '/../init.php';

// Check if user is logged in
$reviewerId = requireAuth();

// Get input data
$input = getJsonInput();
$requestId = isset($input['request_id']) ? (int)$input['request_id'] : null;
$rating = isset($input['rating']) ? (int)$input['rating'] : null;
$comment = isset($input['comment']) ? trim($input['comment']) : '';

if (!$requestId || !$rating) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Request ID and Rating are required']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verify request details
    $stmt = $pdo->prepare("
        SELECT r.status, r.from_user_id, r.to_user_id, s.title as skill_title
        FROM requests r
        JOIN skills s ON r.skill_id = s.skill_id
        WHERE r.request_id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        throw new Exception('Request not found');
    }

    if ($request['status'] !== 'completed') {
        throw new Exception('You can only review completed exchanges');
    }

    // Determine who is being reviewed
    if ($reviewerId === $request['from_user_id']) {
        $revieweeId = $request['to_user_id'];
    } elseif ($reviewerId === $request['to_user_id']) {
        $revieweeId = $request['from_user_id'];
    } else {
        http_response_code(403);
        throw new Exception('You are not a participant in this exchange');
    }

    // 2. Check if already reviewed
    $stmt = $pdo->prepare("SELECT review_id FROM reviews WHERE request_id = ? AND from_user_id = ?");
    $stmt->execute([$requestId, $reviewerId]);
    if ($stmt->fetch()) {
        throw new Exception('You have already reviewed this exchange');
    }

    // 3. Insert review
    $stmt = $pdo->prepare("
        INSERT INTO reviews (from_user_id, to_user_id, request_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$reviewerId, $revieweeId, $requestId, $rating, $comment]);
    $reviewId = $pdo->lastInsertId();

    // 4. Update user reputation score
    // Calculate new average
    $stmt = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE to_user_id = ?");
    $stmt->execute([$revieweeId]);
    $newAvg = $stmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE users SET reputation_score = ? WHERE id = ?");
    $stmt->execute([$newAvg, $revieweeId]);

    // 5. Create Notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id, related_type, action_url, created_at)
        VALUES (?, 'new_review', ?, ?, 'review', 'profile.html', NOW())
    ");
    $message = "You received a $rating-star review for '{$request['skill_title']}'";
    $stmt->execute([$revieweeId, $message, $reviewId]);

    // 6. Log Audit
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $reviewerId, 'review_posted', 'review', $reviewId, ['rating' => $rating, 'target_user' => $revieweeId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Review posted successfully'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); 
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>