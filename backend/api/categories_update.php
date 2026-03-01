<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');

// Admin only
$userId = requireAdmin();

try {
    $data = getJsonInput();

    // Validate required fields
    $validationErrors = validateRequired($data, ['id', 'name']);
    if (!empty($validationErrors)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => implode(', ', $validationErrors)]);
        exit();
    }

    $category_id = (int)$data['id'];
    $name = sanitizeInput($data['name'], 'string');

    // Validate name length
    if (!validateLength($name, 2, 100)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Category name must be between 2 and 100 characters']);
        exit();
    }

    // Validate category ID
    if (!validateInt($category_id, 1)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid category ID']);
        exit();
    }

    // Check if category exists
    $checkStmt = $pdo->prepare('SELECT category_id FROM skill_categories WHERE category_id = ?');
    $checkStmt->execute([$category_id]);
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Category not found']);
        exit();
    }

    // Check if another category with the same name exists (excluding current category)
    $duplicateStmt = $pdo->prepare('SELECT category_id FROM skill_categories WHERE category_name = ? AND category_id != ?');
    $duplicateStmt->execute([$name, $category_id]);
    if ($duplicateStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Category name already exists']);
        exit();
    }

    $stmt = $pdo->prepare('UPDATE skill_categories SET category_name = ? WHERE category_id = ?');
    $stmt->execute([$name, $category_id]);

    echo json_encode(['ok' => true, 'message' => 'Category updated successfully']);
} catch (PDOException $e) {
    error_log("Category update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to update category']);
}

