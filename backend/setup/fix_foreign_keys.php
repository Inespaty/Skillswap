<?php
/**
 * SkillSwap Database Migration Script - Fixed Version
 * This script creates tables without foreign keys first, then adds constraints
 */

require_once __DIR__ . '/../db.php';

echo "<h2>SkillSwap Database Migration (Fixed)</h2>";
echo "<p>Starting migration process...</p>";

$errors = [];

// Step 0: Fix users table schema (user_id -> id)
echo "<h3>Checking users table schema</h3>";
try {
    // Check if user_id exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_id'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: blue;'>Found 'user_id' column, renaming to 'id' to match codebase...</p>";
        $pdo->exec("ALTER TABLE users CHANGE user_id id INT(11) NOT NULL AUTO_INCREMENT");
        echo "<p style='color: green;'>✓ Renamed user_id to id</p>";
    } else {
        // Check if id exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'id'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Users table already has 'id' column</p>";
        } else {
            echo "<p style='color: red;'>❌ Error: Users table is missing primary key column (id/user_id)</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error checking/fixing users table: " . $e->getMessage() . "</p>";
}

// Step 1: Create audit_logs table WITHOUT foreign key
echo "<h3>Creating audit_logs table</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `audit_logs` (
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
            KEY `idx_audit_logs_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✓ Created audit_logs table</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "<p style='color: orange;'>⚠ Table already exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        $errors[] = $e->getMessage();
    }
}

// Step 2: Create disputes table WITHOUT foreign keys
echo "<h3>Creating disputes table</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `disputes` (
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
            KEY `idx_disputes_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✓ Created disputes table</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "<p style='color: orange;'>⚠ Table already exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        $errors[] = $e->getMessage();
    }
}

// Step 3: Add foreign key to skills table for approver
echo "<h3>Adding foreign key constraints</h3>";
try {
    // Check if constraint already exists
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'skills' 
        AND CONSTRAINT_NAME = 'fk_skills_approver'
    ");
    
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE `skills` 
            ADD CONSTRAINT `fk_skills_approver` 
            FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ");
        echo "<p style='color: green;'>✓ Added fk_skills_approver constraint</p>";
    } else {
        echo "<p style='color: orange;'>⚠ fk_skills_approver already exists</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error adding fk_skills_approver: " . $e->getMessage() . "</p>";
    $errors[] = $e->getMessage();
}

// Step 4: Add foreign key to audit_logs
try {
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'audit_logs' 
        AND CONSTRAINT_NAME = 'fk_audit_logs_user'
    ");
    
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE `audit_logs` 
            ADD CONSTRAINT `fk_audit_logs_user` 
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ");
        echo "<p style='color: green;'>✓ Added fk_audit_logs_user constraint</p>";
    } else {
        echo "<p style='color: orange;'>⚠ fk_audit_logs_user already exists</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error adding fk_audit_logs_user: " . $e->getMessage() . "</p>";
    $errors[] = $e->getMessage();
}

// Step 5: Add foreign keys to disputes table
try {
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'disputes' 
        AND CONSTRAINT_NAME = 'fk_disputes_request'
    ");
    
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE `disputes` 
            ADD CONSTRAINT `fk_disputes_request` 
            FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE
        ");
        echo "<p style='color: green;'>✓ Added fk_disputes_request constraint</p>";
    } else {
        echo "<p style='color: orange;'>⚠ fk_disputes_request already exists</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error adding fk_disputes_request: " . $e->getMessage() . "</p>";
    $errors[] = $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'disputes' 
        AND CONSTRAINT_NAME = 'fk_disputes_raised_by'
    ");
    
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE `disputes` 
            ADD CONSTRAINT `fk_disputes_raised_by` 
            FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ");
        echo "<p style='color: green;'>✓ Added fk_disputes_raised_by constraint</p>";
    } else {
        echo "<p style='color: orange;'>⚠ fk_disputes_raised_by already exists</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error adding fk_disputes_raised_by: " . $e->getMessage() . "</p>";
    $errors[] = $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'disputes' 
        AND CONSTRAINT_NAME = 'fk_disputes_resolved_by'
    ");
    
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE `disputes` 
            ADD CONSTRAINT `fk_disputes_resolved_by` 
            FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ");
        echo "<p style='color: green;'>✓ Added fk_disputes_resolved_by constraint</p>";
    } else {
        echo "<p style='color: orange;'>⚠ fk_disputes_resolved_by already exists</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error adding fk_disputes_resolved_by: " . $e->getMessage() . "</p>";
    $errors[] = $e->getMessage();
}

echo "<hr>";
if (empty($errors)) {
    echo "<h3 style='color: green;'>✓ Migration completed successfully!</h3>";
    echo "<p>All foreign key constraints have been added.</p>";
} else {
    echo "<h3 style='color: orange;'>⚠ Migration completed with some errors</h3>";
    echo "<p>Please review the errors above. If the errors are about missing columns, please check your database structure.</p>";
    echo "<details><summary>View errors</summary><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul></details>";
}

echo "<p><a href='check_db_structure.php'>Check Database Structure</a> | <a href='index.html'>← Back to Home</a></p>";
?>
