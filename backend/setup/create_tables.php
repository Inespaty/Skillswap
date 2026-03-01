<?php
/**
 * SkillSwap Database Setup Script
 * This script creates all necessary tables for the SkillSwap application
 * Run this once to initialize the database
 */

include __DIR__ . '/../db.php'; // Connect to database

echo "<h2>SkillSwap Database Setup</h2>";

// Import and execute the schema
$schemaFile = __DIR__ . '/../../database/schema.sql';
if (file_exists($schemaFile)) {
    $schema = file_get_contents($schemaFile);

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $schema)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
                echo "<p>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
            } catch (PDOException $e) {
                echo "<p>⚠ Warning: " . $e->getMessage() . "</p>";
                // Continue with other statements
            }
        }
    }
} else {
    echo "<p>❌ Error: schema.sql file not found at $schemaFile</p>";
    exit();
}

echo "<h3>Database setup completed successfully!</h3>";
echo "<p>Default admin account: admin@skillswap.com / admin123</p>";
echo "<p><strong>⚠ IMPORTANT:</strong> Change the default admin password immediately!</p>";
?>
