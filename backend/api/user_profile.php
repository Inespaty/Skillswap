<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid user ID required']);
    exit();
}

try {
    $stmt = $pdo->prepare('SELECT id, name, email, credits, reputation_score, profile_pic, bio, phone, location, title, website, created_at FROM users WHERE id = ? AND is_banned = 0 LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'User not found']);
        exit();
    }

    // Check if current user is viewing their own profile
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $isOwnProfile = ($currentUserId === $id);
    
    // fetch user's skills with category names
    // If viewing own profile, show all skills (including pending). Otherwise, only show approved/active skills.
    // Get skills - handle credits_required column gracefully
    try {
        if ($isOwnProfile) {
            // For own profile, show all skills EXCEPT soft-deleted ones (active_status = 0)
            $stmt2 = $pdo->prepare('SELECT s.skill_id, s.user_id, s.title, s.description, s.image, s.category_id, 
                                           s.active_status, s.approval_status, s.created_at,
                                           COALESCE(s.credits_required, 0) as credits_required,
                                           c.category_name AS category_name
                                    FROM skills s
                                    LEFT JOIN skill_categories c ON s.category_id = c.category_id
                                    WHERE s.user_id = ? AND s.active_status = 1
                                    ORDER BY s.created_at DESC');
        } else {
            $stmt2 = $pdo->prepare('SELECT s.skill_id, s.title, s.description, s.image, s.category_id,
                                           s.active_status, s.approval_status, s.created_at,
                                           COALESCE(s.credits_required, 0) as credits_required,
                                           c.category_name AS category_name
                                    FROM skills s
                                    LEFT JOIN skill_categories c ON s.category_id = c.category_id
                                    WHERE s.user_id = ? AND s.active_status = 1 AND s.approval_status = \'approved\'
                                    ORDER BY s.created_at DESC');
        }
        $stmt2->execute([$id]);
        $skillRows = $stmt2->fetchAll();
    } catch (PDOException $e) {
        // If credits_required column doesn't exist, add it and retry
        if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'credits_required') !== false) {
            try {
                $pdo->exec('ALTER TABLE skills ADD COLUMN credits_required INT(11) DEFAULT 0');
                // Retry the query
                if ($isOwnProfile) {
                    // For own profile, show all skills EXCEPT soft-deleted ones (active_status = 0)
                    $stmt2 = $pdo->prepare('SELECT s.skill_id, s.user_id, s.title, s.description, s.image, s.category_id, 
                                                   s.active_status, s.approval_status, s.created_at,
                                                   COALESCE(s.credits_required, 0) as credits_required,
                                                   c.category_name AS category_name
                                            FROM skills s
                                            LEFT JOIN skill_categories c ON s.category_id = c.category_id
                                            WHERE s.user_id = ? AND s.active_status = 1
                                            ORDER BY s.created_at DESC');
                } else {
                    $stmt2 = $pdo->prepare('SELECT s.skill_id, s.title, s.description, s.image, s.category_id,
                                                   s.active_status, s.approval_status, s.created_at,
                                                   COALESCE(s.credits_required, 0) as credits_required,
                                                   c.category_name AS category_name
                                            FROM skills s
                                            LEFT JOIN skill_categories c ON s.category_id = c.category_id
                                            WHERE s.user_id = ? AND s.active_status = 1 AND s.approval_status = \'approved\'
                                            ORDER BY s.created_at DESC');
                }
                $stmt2->execute([$id]);
                $skillRows = $stmt2->fetchAll();
            } catch (PDOException $e2) {
                error_log("Failed to add credits_required column: " . $e2->getMessage());
                // Fallback: query without credits_required
                if ($isOwnProfile) {
                    // For own profile, show all skills EXCEPT soft-deleted ones (active_status = 0)
                    $stmt2 = $pdo->prepare('SELECT s.skill_id, s.user_id, s.title, s.description, s.image, s.category_id, 
                                                   s.active_status, s.approval_status, s.created_at,
                                                   c.category_name AS category_name
                                            FROM skills s
                                            LEFT JOIN skill_categories c ON s.category_id = c.category_id
                                            WHERE s.user_id = ? AND s.active_status = 1
                                            ORDER BY s.created_at DESC');
                } else {
                    $stmt2 = $pdo->prepare('SELECT s.skill_id, s.title, s.description, s.image, s.category_id,
                                                   s.active_status, s.approval_status, s.created_at,
                                                   c.category_name AS category_name
                                            FROM skills s
                                            LEFT JOIN skill_categories c ON s.category_id = c.category_id
                                            WHERE s.user_id = ? AND s.active_status = 1 AND s.approval_status = \'approved\'
                                            ORDER BY s.created_at DESC');
                }
                $stmt2->execute([$id]);
                $skillRows = $stmt2->fetchAll();
            }
        } else {
            throw $e;
        }
    }

    // Format skills
    $skills = [];
    foreach ($skillRows as $skill) {
        $skills[] = [
            'id' => (int)$skill['skill_id'],
            'skill_id' => (int)$skill['skill_id'], // Also include skill_id for compatibility
            'title' => $skill['title'],
            'description' => $skill['description'],
            'image' => $skill['image'],
            'category_id' => $skill['category_id'] ? (int)$skill['category_id'] : null,
            'category_name' => $skill['category_name'] ?? 'Uncategorized',
            'credits_required' => (int)($skill['credits_required'] ?? 0),
            'active_status' => (int)$skill['active_status'],
            'approval_status' => $skill['approval_status'] ?? 'pending',
            'created_at' => $skill['created_at'] ?? null
        ];
    }
    
    // Count skills
    $skill_count = count($skills);
    
    // Get reviews received
    $stmt3 = $pdo->prepare('
        SELECT r.review_id, r.rating, r.comment, r.created_at,
               u.name as reviewer_name, u.profile_pic as reviewer_avatar,
               s.title as skill_title
        FROM reviews r
        JOIN users u ON r.from_user_id = u.id
        LEFT JOIN requests req ON r.request_id = req.request_id
        LEFT JOIN skills s ON req.skill_id = s.skill_id
        WHERE r.to_user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 20
    ');
    $stmt3->execute([$id]);
    $reviewRows = $stmt3->fetchAll();

    $reviews = [];
    foreach ($reviewRows as $review) {
        $reviews[] = [
            'id' => (int)$review['review_id'],
            'rating' => (int)$review['rating'],
            'comment' => $review['comment'],
            'created_at' => $review['created_at'],
            'reviewer_name' => $review['reviewer_name'],
            'reviewer_avatar' => $review['reviewer_avatar'],
            'skill_title' => $review['skill_title']
        ];
    }
    
    // Get skills received (skills the user has learned/requested)
    $stmt4 = $pdo->prepare('
        SELECT DISTINCT s.skill_id, s.title, s.description, s.image, s.category_id, c.category_name AS category_name,
               req.status as request_status, req.created_at as request_date
        FROM requests req
        JOIN skills s ON req.skill_id = s.skill_id
        LEFT JOIN skill_categories c ON s.category_id = c.category_id
        WHERE req.from_user_id = ? AND req.status IN (\'accepted\', \'completed\')
        ORDER BY req.created_at DESC
        LIMIT 20
    ');
    $stmt4->execute([$id]);
    $receivedSkillRows = $stmt4->fetchAll();

    $receivedSkills = [];
    foreach ($receivedSkillRows as $skill) {
        $receivedSkills[] = [
            'id' => (int)$skill['skill_id'],
            'title' => $skill['title'],
            'description' => $skill['description'],
            'image' => $skill['image'],
            'category_id' => $skill['category_id'] ? (int)$skill['category_id'] : null,
            'category_name' => $skill['category_name'] ?? 'Uncategorized',
            'request_status' => $skill['request_status'],
            'request_date' => $skill['request_date']
        ];
    }
    
    // Get credit transaction history
    $stmt5 = $pdo->prepare('
        SELECT t.transaction_id, t.credits, t.type, t.status, t.created_at,
               CASE
                   WHEN t.from_user_id = ? THEN u2.name
                   ELSE u1.name
               END as other_user_name,
               CASE
                   WHEN t.from_user_id = ? THEN \'sent\'
                   ELSE \'received\'
               END as transaction_direction
        FROM transactions t
        LEFT JOIN users u1 ON t.from_user_id = u1.id
        LEFT JOIN users u2 ON t.to_user_id = u2.id
        WHERE (t.from_user_id = ? OR t.to_user_id = ?)
        ORDER BY t.created_at DESC
        LIMIT 20
    ');
    $stmt5->execute([$id, $id, $id, $id]);
    $transactionRows = $stmt5->fetchAll();

    $transactions = [];
    foreach ($transactionRows as $trans) {
        $transactions[] = [
            'id' => (int)$trans['transaction_id'],
            'credits' => (int)$trans['credits'],
            'type' => $trans['type'],
            'status' => $trans['status'],
            'created_at' => $trans['created_at'],
            'other_user_name' => $trans['other_user_name'],
            'direction' => $trans['transaction_direction']
        ];
    }

    // Format user data
    $userData = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'credits' => (int)$user['credits'],
        'reputation_score' => (float)$user['reputation_score'],
        'profile_pic' => $user['profile_pic'],
        'bio' => $user['bio'],
        'phone' => $user['phone'],
        'location' => $user['location'],
        'title' => $user['title'],
        'website' => $user['website'],
        'created_at' => $user['created_at'],
        'skill_count' => $skill_count
    ];

    echo json_encode([
        'ok' => true,
        'user' => $userData,
        'skills' => $skills,
        'reviews' => $reviews,
        'received_skills' => $receivedSkills,
        'transactions' => $transactions
    ]);
} catch (PDOException $e) {
    error_log("User profile error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to load user profile']);
}
