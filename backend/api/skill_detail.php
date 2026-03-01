<?php
ob_start();
require_once __DIR__ . '/../init.php';
ini_set('display_errors', '0');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    return true;
});
header('Content-Type: application/json');

$skillId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($skillId <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid skill ID required']);
    exit();
}

try {
    // Check if current user is viewing their own skill
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    
    // Get skill details with owner information
    // If user is viewing their own skill, show it regardless of status
    // Otherwise, only show active and approved skills
    if ($currentUserId > 0) {
        $stmt = $pdo->prepare("SELECT s.skill_id, s.user_id, s.title, s.description, s.category_id, s.image, 
                                      s.active_status, s.approval_status, s.created_at,
                                      COALESCE(s.credits_required, 0) as credits_required,
                                      c.category_name,
                                      u.name as owner_name, u.profile_pic as owner_avatar, 
                                      u.reputation_score, u.location as owner_location,
                                      (SELECT COUNT(*) FROM requests WHERE skill_id = s.skill_id AND status = 'completed') as completed_sessions,
                                      (SELECT AVG(rating) FROM reviews r 
                                       JOIN requests req ON r.request_id = req.request_id 
                                       WHERE req.skill_id = s.skill_id AND r.to_user_id = s.user_id) as avg_rating
                               FROM skills s
                               JOIN users u ON s.user_id = u.id
                               LEFT JOIN skill_categories c ON s.category_id = c.category_id
                               WHERE s.skill_id = ? AND u.is_banned = 0 
                               AND (s.user_id = ? OR (s.active_status = 1 AND s.approval_status = 'approved'))");
        $stmt->execute([$skillId, $currentUserId]);
    } else {
        // Not logged in - only show active and approved skills
        $stmt = $pdo->prepare("SELECT s.skill_id, s.user_id, s.title, s.description, s.category_id, s.image, 
                                      s.active_status, s.approval_status, s.created_at,
                                      COALESCE(s.credits_required, 0) as credits_required,
                                      c.category_name,
                                      u.name as owner_name, u.profile_pic as owner_avatar, 
                                      u.reputation_score, u.location as owner_location,
                                      (SELECT COUNT(*) FROM requests WHERE skill_id = s.skill_id AND status = 'completed') as completed_sessions,
                                      (SELECT AVG(rating) FROM reviews r 
                                       JOIN requests req ON r.request_id = req.request_id 
                                       WHERE req.skill_id = s.skill_id AND r.to_user_id = s.user_id) as avg_rating
                               FROM skills s
                               JOIN users u ON s.user_id = u.id
                               LEFT JOIN skill_categories c ON s.category_id = c.category_id
                               WHERE s.skill_id = ? AND s.active_status = 1 AND s.approval_status = 'approved' AND u.is_banned = 0");
        $stmt->execute([$skillId]);
    }
    
    $skill = $stmt->fetch();

    if (!$skill) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Skill not found or not available']);
        exit();
    }

    // Get reviews for this skill
    $reviewStmt = $pdo->prepare("SELECT r.review_id, r.rating, r.comment, r.created_at,
                                         u.name as reviewer_name, u.profile_pic as reviewer_avatar
                                  FROM reviews r
                                  JOIN users u ON r.from_user_id = u.id
                                  JOIN requests req ON r.request_id = req.request_id
                                  WHERE req.skill_id = ? AND r.to_user_id = ?
                                  ORDER BY r.created_at DESC
                                  LIMIT 10");
    $reviewStmt->execute([$skillId, $skill['user_id']]);
    $reviews = $reviewStmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'skill' => [
            'id' => (int)$skill['skill_id'],
            'skill_id' => (int)$skill['skill_id'],
            'title' => $skill['title'],
            'description' => $skill['description'],
            'category_id' => $skill['category_id'] ? (int)$skill['category_id'] : null,
            'category_name' => $skill['category_name'] ?? 'Uncategorized',
            'image' => $skill['image'],
            'credits_required' => (int)($skill['credits_required'] ?? 0),
            'created_at' => $skill['created_at'],
            'active_status' => (int)$skill['active_status'],
            'approval_status' => $skill['approval_status'] ?? 'approved',
            'owner' => [
                'id' => (int)$skill['user_id'],
                'name' => $skill['owner_name'],
                'avatar' => $skill['owner_avatar'],
                'reputation' => (float)$skill['reputation_score'],
                'location' => $skill['owner_location']
            ],
            'stats' => [
                'completed_sessions' => (int)$skill['completed_sessions'],
                'avg_rating' => $skill['avg_rating'] ? round((float)$skill['avg_rating'], 1) : 0
            ],
            'reviews' => array_map(function($r) {
                return [
                    'id' => (int)$r['review_id'],
                    'rating' => (int)$r['rating'],
                    'comment' => $r['comment'],
                    'created_at' => $r['created_at'],
                    'reviewer_name' => $r['reviewer_name'],
                    'reviewer_avatar' => $r['reviewer_avatar']
                ];
            }, $reviews)
        ]
    ]);
    ob_end_flush();
} catch (PDOException $e) {
    ob_end_clean();
    error_log("Skill detail error: " . $e->getMessage());
    
    // If error is about missing credits_required column, try to add it and retry
    if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'credits_required') !== false) {
        try {
            $pdo->exec('ALTER TABLE skills ADD COLUMN credits_required INT(11) DEFAULT 0');
            // Retry the query - but this is complex, so just return error
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Please refresh the page and try again']);
            exit();
        } catch (PDOException $e2) {
            error_log("Failed to add credits_required column: " . $e2->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Failed to load skill details']);
            exit();
        }
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to load skill details']);
        exit();
    }
} catch (Exception $e) {
    ob_end_clean();
    error_log("Skill detail error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to load skill details']);
    exit();
}
?>

