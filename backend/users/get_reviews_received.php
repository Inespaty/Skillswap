<?php
require_once __DIR__ . '/../init.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get user ID (either logged in user or specific user profile)
$userId = isset($_GET['user_id']) ? sanitizeInput($_GET['user_id']) : ($_SESSION['user_id'] ?? null);

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit();
}

// Get pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

try {
    // Get total count and aggregate rating
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            COALESCE(AVG(rating), 0) as average_rating,
            COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
            COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
            COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
            COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
            COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
        FROM reviews 
        WHERE to_user_id = ?
    ");
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Get individual reviews
    $sql = "
        SELECT 
            r.review_id,
            r.from_user_id,
            r.rating,
            r.comment,
            r.created_at,
            u.name as reviewer_name,
            u.profile_pic as reviewer_pic
        FROM reviews r
        JOIN users u ON r.from_user_id = u.id
        WHERE r.to_user_id = ?
        ORDER BY r.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'stats' => [
            'total_reviews' => (int)$stats['total_reviews'],
            'average_rating' => round((float)$stats['average_rating'], 1),
            'rating_breakdown' => [
                5 => (int)$stats['five_star'],
                4 => (int)$stats['four_star'],
                3 => (int)$stats['three_star'],
                2 => (int)$stats['two_star'],
                1 => (int)$stats['one_star']
            ]
        ],
        'reviews' => $reviews,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($stats['total_reviews'] / $limit),
            'has_next' => $page < ceil($stats['total_reviews'] / $limit)
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Reviews fetch error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch reviews']);
}
?>
