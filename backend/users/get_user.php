<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $requestedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];
    
    // Get user basic info
    $stmt = $pdo->prepare("SELECT 
        user_id, name, email, credits, reputation_score, profile_pic, 
        created_at, bio, phone, department, year_of_study
        FROM Users WHERE user_id = ?");
    $stmt->execute([$requestedUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Remove sensitive data
    unset($user['password']);

    // Get user's offered skills
    $stmt = $pdo->prepare("SELECT 
        s.skill_id, s.title, s.description, s.category, s.image, s.created_at,
        COUNT(DISTINCT r.review_id) as review_count,
        COALESCE(AVG(r.rating), 0) as avg_rating
        FROM Skills s
        LEFT JOIN Requests req ON s.skill_id = req.skill_id
        LEFT JOIN Reviews r ON req.request_id = r.request_id AND r.to_user_id = s.user_id
        WHERE s.user_id = ? AND s.active_status = 1
        GROUP BY s.skill_id");
    $stmt->execute([$requestedUserId]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's reviews
    $stmt = $pdo->prepare("SELECT 
        r.review_id, r.rating, r.comment, r.created_at,
        u.user_id as reviewer_id, u.name as reviewer_name, u.profile_pic as reviewer_avatar
        FROM Reviews r
        JOIN Users u ON r.from_user_id = u.user_id
        WHERE r.to_user_id = ?
        ORDER BY r.created_at DESC");
    $stmt->execute([$requestedUserId]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate average rating
    $avgRating = 0;
    if (!empty($reviews)) {
        $sum = array_sum(array_column($reviews, 'rating'));
        $avgRating = $sum / count($reviews);
    }

    // Prepare response
    $response = [
        'success' => true,
        'user' => [
            'id' => $user['user_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'credits' => (int)$user['credits'],
            'reputation' => (float)$user['reputation_score'],
            'profile_pic' => $user['profile_pic'],
            'bio' => $user['bio'] ?? '',
            'phone' => $user['phone'] ?? null,
            'department' => $user['department'] ?? null,
            'year_of_study' => $user['year_of_study'] ?? null,
            'member_since' => $user['created_at'],
            'stats' => [
                'skills_offered' => count($skills),
                'reviews_count' => count($reviews),
                'avg_rating' => round($avgRating, 1)
            ]
        ],
        'skills' => $skills,
        'reviews' => $reviews
    ];

    // Add is_owner flag if the requested user is the logged-in user
    $response['user']['is_owner'] = ($requestedUserId === $_SESSION['user_id']);

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
