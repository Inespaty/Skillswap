<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');

// Admin only
$userId = requireAdmin();

try {
    $data = getJsonInput();

    // Validate required fields
    $validationErrors = validateRequired($data, ['name']);
    if (!empty($validationErrors)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => implode(', ', $validationErrors)]);
        exit();
    }

    $name = sanitizeInput($data['name'], 'string');

    // Validate name length
    if (!validateLength($name, 2, 100)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Category name must be between 2 and 100 characters']);
        exit();
    }

    // Check if category already exists
    $checkStmt = $pdo->prepare('SELECT category_id FROM skill_categories WHERE category_name = ?');
    $checkStmt->execute([$name]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Category already exists']);
        exit();
    }

    $stmt = $pdo->prepare('INSERT INTO skill_categories (category_name) VALUES (?)');
    $stmt->execute([$name]);

    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (PDOException $e) {
    error_log("Category create error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to create category']);
}
