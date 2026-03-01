<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query('SELECT category_id, category_name, created_at FROM skill_categories ORDER BY category_name ASC');
    $categories = $stmt->fetchAll();

    // Format response
    $formattedCategories = array_map(function($cat) {
        return [
            'id' => (int)$cat['category_id'],
            'name' => $cat['category_name'],
            'created_at' => $cat['created_at'] ?? null
        ];
    }, $categories);

    echo json_encode(['ok' => true, 'categories' => $formattedCategories]);
} catch (PDOException $e) {
    error_log("Categories list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to load categories']);
}
