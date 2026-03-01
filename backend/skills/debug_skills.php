<?php
// Direct DB include to bypass init.php checks
require_once __DIR__ . '/backend/db.php';

// Force show errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Debug Pending Skills CLI (Direct DB)\n";

try {
    // 1. Check Raw Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM skills WHERE approval_status = 'pending'");
    $count = $stmt->fetchColumn();
    echo "Raw Count: $count\n\n";

    // 2. Check Raw Rows
    $stmt = $pdo->query("SELECT skill_id, title, user_id, category_id, approval_status FROM skills WHERE approval_status = 'pending'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Raw Rows:\n";
    print_r($rows);
    echo "\n";

    // 3. Test the QUERY from get_pending_skills.php
    $limit = 10;
    $offset = 0;
    
    // Note: I am pasting the EXACT query from get_pending_skills.php (with the previous fix)
    $sql = "SELECT s.skill_id, s.title, s.user_id, COALESCE(u.name, 'Deleted User') as user_name
            FROM skills s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN skill_categories c ON s.category_id = c.category_id
            WHERE s.approval_status = 'pending'
            ORDER BY s.created_at ASC
            LIMIT $limit OFFSET $offset";
            
    echo "Testing Query: $sql\n";
    
    $stmt = $pdo->query($sql);
    $joinRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Query Result:\n";
    print_r($joinRows);
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
