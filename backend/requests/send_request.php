<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    // Get request parameters
    $skillId = isset($_POST['skill_id']) ? (int)$_POST['skill_id'] : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $userId = $_SESSION['user_id'];
    
    // Validate input
    if ($skillId <= 0) {
        throw new Exception('Invalid skill ID');
    }
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check if skill exists and is active
        $stmt = $pdo->prepare("
            SELECT s.*, u.credits as owner_credits
            FROM Skills s
            JOIN Users u ON s.user_id = u.user_id
            WHERE s.skill_id = ? 
            AND s.active_status = 1
            AND s.user_id != ?  // Cannot request own skill
            FOR UPDATE
        ");
        $stmt->execute([$skillId, $userId]);
        $skill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$skill) {
            throw new Exception('Skill not found, inactive, or you cannot request your own skill');
        }
        
        // Check if user already has a pending or accepted request for this skill
        $stmt = $pdo->prepare("
            SELECT request_id 
            FROM Requests 
            WHERE skill_id = ? 
            AND from_user_id = ? 
            AND status IN ('pending', 'accepted')
        ");
        $stmt->execute([$skillId, $userId]);
        
        if ($stmt->fetch()) {
            throw new Exception('You already have an active request for this skill');
        }
        
        // Check if user has enough credits
        if ($skill['credits_required'] > 0) {
            $stmt = $pdo->prepare("
                SELECT credits 
                FROM Users 
                WHERE user_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$userId]);
            $userCredits = $stmt->fetchColumn();
            
            if ($userCredits < $skill['credits_required']) {
                throw new Exception('Insufficient credits to request this skill');
            }
        }
        
        // Create the request
        $stmt = $pdo->prepare("
            INSERT INTO Requests 
            (skill_id, from_user_id, to_user_id, message, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $skillId,
            $userId,
            $skill['user_id'],
            $message
        ]);
        $requestId = $pdo->lastInsertId();
        
        // Create notification for skill owner
        $stmt = $pdo->prepare("
            INSERT INTO Notifications 
            (user_id, type, message, related_id, related_type)
            SELECT 
                ? as user_id,
                'new_request' as type,
                CONCAT('New request for your skill: ', ?) as message,
                ? as related_id,
                'request' as related_type
            FROM Users 
            WHERE user_id = ?
        ");
        $stmt->execute([
            $skill['user_id'],
            $skill['title'],
            $requestId,
            $skill['user_id']
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        // Get the created request with user details
        $stmt = $pdo->prepare("
            SELECT r.*,
                   u1.name as from_user_name,
                   u1.profile_pic as from_user_avatar,
                   u2.name as to_user_name,
                   u2.profile_pic as to_user_avatar,
                   s.title as skill_title
            FROM Requests r
            JOIN Users u1 ON r.from_user_id = u1.user_id
            JOIN Users u2 ON r.to_user_id = u2.user_id
            JOIN Skills s ON r.skill_id = s.skill_id
            WHERE r.request_id = ?
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Request sent successfully',
            'request' => $request
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}