<?php
/**
 * Migration: create Skill_Categories and add category_id to Skills
 * Run this script from the project root with PHP CLI or via your webserver.
 */

require_once __DIR__ . '/../db.php';

echo "Starting migration: add Skill_Categories and category_id to Skills...\n";

try {
    // 1) Create Skill_Categories table if not exists
    $createTableSql = "CREATE TABLE IF NOT EXISTS Skill_Categories (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($createTableSql);
    echo "- Ensured table Skill_Categories exists.\n";

    // 2) Insert default categories (idempotent)
    $defaultCategories = [
        'Programming', 'Graphic Design', 'Tutoring', 'Photography', 'Fitness',
        'Music', 'Web Development', 'Languages', 'Art & Craft', 'Public Speaking',
        'Cooking', 'Writing'
    ];

    $insertStmt = $pdo->prepare('INSERT INTO Skill_Categories (category_name) VALUES (?) ON DUPLICATE KEY UPDATE category_name = category_name');
    foreach ($defaultCategories as $cat) {
        $insertStmt->execute([$cat]);
    }

    echo "- Inserted/verified default categories.\n";

    // 3) Check if `category_id` column exists on Skills
    $checkColStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'Skills' AND COLUMN_NAME = 'category_id'");
    $checkColStmt->execute([':db' => $db_name]);
    $colExists = (int)$checkColStmt->fetchColumn() > 0;

    if (!$colExists) {
        // Add the column
        $pdo->exec("ALTER TABLE `Skills` ADD COLUMN category_id INT NULL AFTER description");
        echo "- Added column Skills.category_id.\n";
    } else {
        echo "- Column Skills.category_id already exists; skipping add.\n";
    }

    // 4) Add foreign key constraint if not exists
    $checkFkStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'Skills' AND COLUMN_NAME = 'category_id' AND REFERENCED_TABLE_NAME = 'Skill_Categories'");
    $checkFkStmt->execute([':db' => $db_name]);
    $fkExists = (int)$checkFkStmt->fetchColumn() > 0;

    if (!$fkExists) {
        // We need to ensure the referenced table and column exist (they do)
        // Use a safe constraint name that is unlikely to collide
        $constraintName = 'fk_skills_category';

        // If there is an existing constraint with that name, skip adding
        $checkConstraint = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'Skills' AND CONSTRAINT_NAME = :cname");
        $checkConstraint->execute([':db' => $db_name, ':cname' => $constraintName]);
        $constraintExists = (int)$checkConstraint->fetchColumn() > 0;

        if (!$constraintExists) {
            $pdo->exec("ALTER TABLE `Skills` ADD CONSTRAINT `" . $constraintName . "` FOREIGN KEY (category_id) REFERENCES `Skill_Categories` (category_id) ON DELETE SET NULL ON UPDATE CASCADE");
            echo "- Added foreign key constraint fk_skills_category.\n";
        } else {
            echo "- Constraint fk_skills_category already exists; skipping.\n";
        }
    } else {
        echo "- Foreign key for Skills.category_id → Skill_Categories.category_id already exists; skipping.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: ", $e->getMessage(), "\n";
    exit(1);
}

?>