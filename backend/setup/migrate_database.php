<?php
/**
 * SkillSwap Database Migration Script
 * This script updates the existing database to include all new columns and tables
 * Run this ONCE to migrate from old schema to new schema
 */

require_once __DIR__ . '/../db.php';

echo "<h2>SkillSwap Database Migration</h2>";
echo "<p>Starting migration process...</p>";

$migrations = [];
$errors = [];

// Migration 1: Add new columns to users table
$migrations[] = [
    'name' => 'Add profile columns to users table',
    'queries' => [
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `bio` TEXT DEFAULT NULL",
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL",
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `location` VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `title` VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `website` VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_verified` TINYINT(1) DEFAULT 0",
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `status` ENUM('active','suspended','deactivated') DEFAULT 'active'",
        "ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_users_status` (`status`)"
    ]
];

// Migration 2: Add approval columns to skills table
$migrations[] = [
    'name' => 'Add approval workflow to skills table',
    'queries' => [
        "ALTER TABLE `skills` ADD COLUMN IF NOT EXISTS `approval_status` ENUM('pending','approved','rejected') DEFAULT 'pending'",
        "ALTER TABLE `skills` ADD COLUMN IF NOT EXISTS `approved_by` INT(11) DEFAULT NULL",
        "ALTER TABLE `skills` ADD COLUMN IF NOT EXISTS `approved_at` TIMESTAMP NULL DEFAULT NULL",
        "ALTER TABLE `skills` ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT DEFAULT NULL",
        "ALTER TABLE `skills` ADD INDEX IF NOT EXISTS `idx_skills_approval` (`approval_status`)",
        "ALTER TABLE `skills` ADD CONSTRAINT IF NOT EXISTS `fk_skills_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL"
    ]
];

// Migration 3: Add hours and completion tracking to requests table
$migrations[] = [
    'name' => 'Add hours and completion tracking to requests table',
    'queries' => [
        "ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `hours_required` INT(11) DEFAULT 1",
        "ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `completed_by_requester` TINYINT(1) DEFAULT 0",
        "ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `completed_by_helper` TINYINT(1) DEFAULT 0",
        "ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ]
];

// Migration 4: Add action_url to notifications table
$migrations[] = [
    'name' => 'Add action URL to notifications table',
    'queries' => [
        "ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `action_url` VARCHAR(255) DEFAULT NULL"
    ]
];

// Migration 5: Create audit_logs table
$migrations[] = [
    'name' => 'Create audit_logs table',
    'queries' => [
        "CREATE TABLE IF NOT EXISTS `audit_logs` (
            `log_id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) DEFAULT NULL,
            `action` VARCHAR(100) NOT NULL,
            `entity_type` VARCHAR(50) DEFAULT NULL,
            `entity_id` INT(11) DEFAULT NULL,
            `details` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`log_id`),
            KEY `idx_audit_logs_user` (`user_id`),
            KEY `idx_audit_logs_action` (`action`),
            KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`),
            KEY `idx_audit_logs_created` (`created_at`),
            CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ]
];

// Migration 6: Create disputes table
$migrations[] = [
    'name' => 'Create disputes table',
    'queries' => [
        "CREATE TABLE IF NOT EXISTS `disputes` (
            `dispute_id` INT(11) NOT NULL AUTO_INCREMENT,
            `request_id` INT(11) NOT NULL,
            `raised_by` INT(11) NOT NULL,
            `reason` TEXT NOT NULL,
            `status` ENUM('open','investigating','resolved','closed') DEFAULT 'open',
            `resolution` TEXT DEFAULT NULL,
            `resolved_by` INT(11) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `resolved_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`dispute_id`),
            KEY `idx_disputes_request` (`request_id`),
            KEY `idx_disputes_raised_by` (`raised_by`),
            KEY `idx_disputes_status` (`status`),
            CONSTRAINT `fk_disputes_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE,
            CONSTRAINT `fk_disputes_raised_by` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_disputes_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ]
];

// Execute migrations
foreach ($migrations as $migration) {
    echo "<h3>{$migration['name']}</h3>";
    
    foreach ($migration['queries'] as $query) {
        try {
            // MySQL doesn't support IF NOT EXISTS for ALTER TABLE, so we need to check first
            if (strpos($query, 'ALTER TABLE') !== false && strpos($query, 'ADD COLUMN') !== false) {
                // Extract table and column name
                preg_match('/ALTER TABLE `(\w+)` ADD COLUMN (?:IF NOT EXISTS )?`(\w+)`/', $query, $matches);
                if (count($matches) >= 3) {
                    $table = $matches[1];
                    $column = $matches[2];
                    
                    // Check if column exists
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                    if ($checkStmt->rowCount() > 0) {
                        echo "<p style='color: orange;'>⚠ Column `$column` already exists in `$table`, skipping...</p>";
                        continue;
                    }
                }
                // Remove IF NOT EXISTS from query as MySQL doesn't support it
                $query = str_replace('IF NOT EXISTS ', '', $query);
            }
            
            // Similar check for indexes
            if (strpos($query, 'ADD INDEX') !== false || strpos($query, 'ADD CONSTRAINT') !== false) {
                $query = str_replace('IF NOT EXISTS ', '', $query);
            }
            
            $pdo->exec($query);
            echo "<p style='color: green;'>✓ Executed: " . substr($query, 0, 80) . "...</p>";
        } catch (PDOException $e) {
            // Check if error is because item already exists
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'Duplicate key name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Already exists, skipping: " . substr($query, 0, 80) . "...</p>";
            } else {
                echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
                $errors[] = $e->getMessage();
            }
        }
    }
}

echo "<hr>";
if (empty($errors)) {
    echo "<h3 style='color: green;'>✓ Migration completed successfully!</h3>";
    echo "<p>Your database is now up to date with all required tables and columns.</p>";
} else {
    echo "<h3 style='color: orange;'>⚠ Migration completed with some warnings</h3>";
    echo "<p>Some operations were skipped because they already existed. This is normal if you've run this script before.</p>";
    echo "<details><summary>View errors</summary><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul></details>";
}

echo "<p><a href='index.html'>← Back to Home</a></p>";
?>
