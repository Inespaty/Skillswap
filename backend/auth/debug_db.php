<?php
require_once __DIR__ . '/../../backend/init.php';

// Simple DB health check for debugging
try {
    $stmt = $pdo->query('SELECT 1');
    $ok = $stmt->fetchColumn();
    echo json_encode(['ok' => true, 'message' => 'Database connection OK']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('DB debug error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database connection failed', 'debug' => $e->getMessage()]);
}

