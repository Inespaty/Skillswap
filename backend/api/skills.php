<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
    // Parameters
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : null;
    $categoryId = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : null;
    $sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'rating'; // rating, recent, popular
    
    // Build query
    $where = ["s.active_status = 1", "s.approval_status = 'approved'", "u.is_banned = 0"];
    $params = [];
    
    // Check if user is logged in (to exclude their own skills)
    // init.php starts the session
    if (isset($_SESSION['user_id'])) {
        $where[] = "s.user_id != ?";
        $params[] = $_SESSION['user_id'];
    }
    
    if ($search) {
        $where[] = "(s.title LIKE ? OR s.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($categoryId) {
        $where[] = "s.category_id = ?";
        $params[] = $categoryId;
    }
    
    // Determine sort order
    $orderBy = "COALESCE((SELECT AVG(rating) FROM reviews r JOIN requests req ON r.request_id = req.request_id WHERE req.skill_id = s.skill_id AND r.to_user_id = s.user_id), 0) DESC, u.reputation_score DESC, s.created_at DESC";
    if ($sortBy === 'recent') {
        $orderBy = "s.created_at DESC";
    } elseif ($sortBy === 'popular') {
        // Sort by number of completed requests (requires join with request counts, simplified here to reputation)
        $orderBy = "u.reputation_score DESC, (SELECT COUNT(*) FROM requests WHERE skill_id = s.skill_id AND status = 'completed') DESC";
    }
    
    $whereClause = implode(" AND ", $where);
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM skills s JOIN users u ON s.user_id = u.id WHERE $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);
    
    // Skills list with owner name and usage stats
    // Handle credits_required column gracefully
    try {
        $sql = "SELECT s.skill_id, s.title, s.description, s.image, s.user_id, s.category_id, 
                       c.category_name AS category_name, u.name AS owner_name, u.profile_pic, u.reputation_score,
                       COALESCE(s.credits_required, 0) AS credits_required,
                       (SELECT COUNT(*) FROM requests WHERE skill_id = s.skill_id AND status = 'completed') as completed_sessions,
                       (SELECT AVG(rating) FROM reviews r 
                        JOIN requests req ON r.request_id = req.request_id 
                        WHERE req.skill_id = s.skill_id AND r.to_user_id = s.user_id) as avg_rating
                FROM skills s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN skill_categories c ON s.category_id = c.category_id
                WHERE $whereClause
                ORDER BY $orderBy
                LIMIT $limit OFFSET $offset";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        // If credits_required column doesn't exist, add it and retry
        if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'credits_required') !== false) {
            try {
                $pdo->exec('ALTER TABLE skills ADD COLUMN credits_required INT(11) DEFAULT 0');
                // Retry the query
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } catch (PDOException $e2) {
                error_log("Failed to add credits_required column: " . $e2->getMessage());
                // Fallback: query without credits_required
                $sql = "SELECT s.skill_id, s.title, s.description, s.image, s.user_id, s.category_id, 
                               c.category_name AS category_name, u.name AS owner_name, u.profile_pic, u.reputation_score,
                               0 AS credits_required,
                               (SELECT COUNT(*) FROM requests WHERE skill_id = s.skill_id AND status = 'completed') as completed_sessions,
                               (SELECT AVG(rating) FROM reviews r 
                                JOIN requests req ON r.request_id = req.request_id 
                                WHERE req.skill_id = s.skill_id AND r.to_user_id = s.user_id) as avg_rating
                        FROM skills s
                        JOIN users u ON s.user_id = u.id
                        LEFT JOIN skill_categories c ON s.category_id = c.category_id
                        WHERE $whereClause
                        ORDER BY $orderBy
                        LIMIT $limit OFFSET $offset";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
        } else {
            throw $e;
        }
    }

    $skills = [];
    while ($row = $stmt->fetch()) {
        $avgRating = $row['avg_rating'] ? round((float)$row['avg_rating'], 1) : null;
        
        $skills[] = [
            'id' => (int)$row['skill_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'category_id' => $row['category_id'] ? (int)$row['category_id'] : null,
            'category_name' => $row['category_name'] ?? 'Uncategorized',
            'image' => $row['image'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['owner_name'],
            'user_profile_pic' => $row['profile_pic'],
            'reputation' => (float)$row['reputation_score'],
            'credits_required' => isset($row['credits_required']) ? (int)$row['credits_required'] : 0,
            'completed_sessions' => (int)$row['completed_sessions'],
            'avg_rating' => $avgRating
        ];
    }

    echo json_encode([
        'ok' => true, 
        'skills' => $skills,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'has_next' => $page < $totalPages
        ]
    ]);
} catch (PDOException $e) {
    error_log("Skills list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to load skills']);
}
