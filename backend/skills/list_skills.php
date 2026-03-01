<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
    // Get query parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(max(1, (int)$_GET['limit']), 50) : 10;
    $offset = ($page - 1) * $limit;
    
    // Get filter parameters
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $search = isset($_GET['q']) ? '%' . $_GET['q'] . '%' : null;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $minRating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : null;
    
    // Get sort parameters
    $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
    $sortOrder = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Validate sort field - add reputation_score as default sort
    $allowedSortFields = ['created_at', 'title', 'rating', 'review_count', 'reputation_score'];
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'reputation_score'; // Default to reputation for visibility
    }
    
    // Build the base query
    // Domain requirement: reputation affects visibility - include reputation_score in ordering
    $query = "SELECT 
        s.skill_id, 
        s.title, 
        s.description, 
        s.category, 
        s.image, 
        s.created_at,
        s.user_id,
        u.name as user_name,
        u.profile_pic as user_avatar,
        u.reputation_score,
        COUNT(DISTINCT r.review_id) as review_count,
        COALESCE(AVG(r.rating), 0) as avg_rating
        FROM Skills s
        JOIN Users u ON s.user_id = u.user_id
        LEFT JOIN Requests req ON s.skill_id = req.skill_id
        LEFT JOIN Reviews r ON req.request_id = r.request_id AND r.to_user_id = s.user_id
        WHERE s.active_status = 1 AND u.is_banned = 0";
    
    $params = [];
    
    // Add filters
    if ($category) {
        $query .= " AND s.category = ?";
        $params[] = $category;
    }
    
    if ($search) {
        $query .= " AND (s.title LIKE ? OR s.description LIKE ?)";
        $params[] = $search;
        $params[] = $search;
    }
    
    if ($userId) {
        $query .= " AND s.user_id = ?";
        $params[] = $userId;
    }
    
    // Group by skill_id to avoid duplicates from joins
    $query .= " GROUP BY s.skill_id";
    
    // Add rating filter after grouping
    if ($minRating !== null) {
        $query .= " HAVING avg_rating >= ?";
        $params[] = $minRating;
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as count_table";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalItems = $countStmt->fetch()['total'];
    $totalPages = ceil($totalItems / $limit);
    
    // Add sorting and pagination - always include reputation_score as secondary sort
    if ($sortBy !== 'reputation_score') {
        $query .= " ORDER BY $sortBy $sortOrder, u.reputation_score DESC, s.skill_id DESC LIMIT ? OFFSET ?";
    } else {
        $query .= " ORDER BY u.reputation_score DESC, s.created_at DESC, s.skill_id DESC LIMIT ? OFFSET ?";
    }
    $params[] = $limit;
    $params[] = $offset;
    
    // Execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available categories for filtering
    $categories = $pdo->query("SELECT DISTINCT category FROM Skills WHERE active_status = 1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    
    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'skills' => $skills,
            'filters' => [
                'categories' => $categories,
                'total_skills' => (int)$totalItems
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => (int)$totalItems,
                'total_pages' => $totalPages
            ]
        ]
    ];
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch skills: ' . $e->getMessage()
    ]);
}