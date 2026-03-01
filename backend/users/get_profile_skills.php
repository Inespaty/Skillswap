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
    // Get user ID from query parameter or use the logged-in user's ID
    $requestedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Get filter parameters
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : null;
    
    // Build the base query
    $query = "SELECT 
        s.skill_id, 
        s.title, 
        s.description, 
        s.category, 
        s.image, 
        s.created_at,
        u.user_id,
        u.name as user_name,
        u.profile_pic as user_avatar,
        COUNT(DISTINCT r.review_id) as review_count,
        COALESCE(AVG(r.rating), 0) as avg_rating
        FROM Skills s
        JOIN Users u ON s.user_id = u.user_id
        LEFT JOIN Requests req ON s.skill_id = req.skill_id
        LEFT JOIN Reviews r ON req.request_id = r.request_id AND r.to_user_id = s.user_id
        WHERE s.user_id = ?";
    
    $params = [$requestedUserId];
    
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
    
    // Group by skill_id to avoid duplicates from joins
    $query .= " GROUP BY s.skill_id";
    
    // Get total count for pagination
    $countStmt = $pdo->prepare(preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(DISTINCT s.skill_id) as total FROM', $query));
    $countStmt->execute($params);
    $totalItems = $countStmt->fetch()['total'];
    $totalPages = ceil($totalItems / $limit);
    
    // Add pagination
    $query .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'skills' => $skills,
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
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}