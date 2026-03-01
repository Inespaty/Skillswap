<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Validate category_id if provided
if ($category_id < 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid category ID']);
    exit();
}

try {
    if ($category_id > 0) {
        $stmt = $pdo->prepare("SELECT s.id, s.title, s.description, c.name AS category_name, s.image, s.user_id, u.name AS owner_name, u.profile_pic, u.reputation_score
                              FROM skills s
                              LEFT JOIN users u ON s.user_id = u.id
                              LEFT JOIN skill_categories c ON s.category_id = c.id
                              WHERE s.active_status = 1 AND s.category_id = ? AND u.is_banned = 0
                              ORDER BY u.reputation_score DESC, s.created_at DESC");
        $stmt->execute([$category_id]);
    } else {
        $stmt = $pdo->query("SELECT s.id, s.title, s.description, c.name AS category_name, s.image, s.user_id, u.name AS owner_name, u.profile_pic, u.reputation_score
                            FROM skills s
                            LEFT JOIN users u ON s.user_id = u.id
                            LEFT JOIN skill_categories c ON s.category_id = c.id
                            WHERE s.active_status = 1 AND u.is_banned = 0
                            ORDER BY u.reputation_score DESC, s.created_at DESC");
    }

    $skills = [];
    while ($row = $stmt->fetch()) {
        $skills[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'category_name' => $row['category_name'] ?? 'Uncategorized',
            'image' => $row['image'],
            'user_id' => (int)$row['user_id'],
            'owner_name' => $row['owner_name'],
            'owner_avatar' => $row['profile_pic']
        ];
    }

    echo json_encode(['ok' => true, 'skills' => $skills]);
} catch (PDOException $e) {
    error_log("Skills by category error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to load skills']);
}
