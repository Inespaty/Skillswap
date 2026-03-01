<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');

// Admin only
$userId = requireAdmin();

try {
    $data = getJsonInput();

    // Validate required fields
    $validationErrors = validateRequired($data, ['id']);
    if (!empty($validationErrors)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => implode(', ', $validationErrors)]);
        exit();
    }

    $category_id = (int)$data['id'];

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

    // Check if category has skills (optional - could prevent deletion)
    $skillCountStmt = $pdo->prepare('SELECT COUNT(*) as count FROM skills WHERE category_id = ?');
    $skillCountStmt->execute([$category_id]);
    $skillCount = $skillCountStmt->fetch()['count'];

    if ($skillCount > 0) {
        // Set skills' category_id to NULL before deleting category
        $updateStmt = $pdo->prepare('UPDATE skills SET category_id = NULL WHERE category_id = ?');
        $updateStmt->execute([$category_id]);
    }

    $stmt = $pdo->prepare('DELETE FROM skill_categories WHERE category_id = ?');
    $stmt->execute([$category_id]);

    echo json_encode(['ok' => true, 'message' => $skillCount > 0 ? 'Category deleted and ' . $skillCount . ' skills uncategorized' : 'Category deleted']);
} catch (PDOException $e) {
    error_log("Category delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to delete category']);
}
