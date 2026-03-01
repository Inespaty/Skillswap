<?php
/**
 * Migration: Add requester confirmation tracking
 * Date: 2025-12-15
 * Description: Adds requester_confirmed_at column to requests table to track when the requester confirms completion
 */

require_once __DIR__ . '/../../backend/db.php';

echo "Starting migration: add requester_confirmed_at to requests table...\n";

try {
    // Check if column already exists
    $checkColStmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :db 
        AND TABLE_NAME = 'requests' 
        AND COLUMN_NAME = 'requester_confirmed_at'
    ");
    $checkColStmt->execute([':db' => $db_name]);
    $colExists = (int)$checkColStmt->fetchColumn() > 0;

    if (!$colExists) {
        // Add the column
        $pdo->exec("
            ALTER TABLE `requests` 
            ADD COLUMN `requester_confirmed_at` TIMESTAMP NULL DEFAULT NULL 
            AFTER `completed_by_helper`
        ");
        echo "- Added column requests.requester_confirmed_at.\n";
        
        // Add index for better query performance
        $pdo->exec("
            ALTER TABLE `requests`
            ADD KEY `idx_requests_confirmation` (`completed_by_helper`, `requester_confirmed_at`)
        ");
        echo "- Added index idx_requests_confirmation.\n";
    } else {
        echo "- Column requests.requester_confirmed_at already exists; skipping.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: ", $e->getMessage(), "\n";
    exit(1);
}
?>
