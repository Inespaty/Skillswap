<?php
require_once __DIR__ . '/../init.php';

// Public endpoint, but authentication helps for 'my reviews' context if needed
// For now, it's public based on user_id

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit;
}

try {
    // 1. Get stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as average_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as count_5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as count_4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as count_3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as count_2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as count_1
        FROM reviews
        WHERE to_user_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Get reviews list
    $stmt = $pdo->prepare("
        SELECT 
            r.review_id,
            r.rating,
            r.comment,
            r.created_at,
            u.id as reviewer_id,
            u.name as reviewer_name,
            u.profile_pic as reviewer_pic
        FROM reviews r
        JOIN users u ON r.from_user_id = u.id
        WHERE r.to_user_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'reviews' => $reviews,
        'pagination' => [
            'current_page' => $page,
            'limit' => $limit
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>