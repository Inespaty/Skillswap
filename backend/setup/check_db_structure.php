<?php
require_once __DIR__ . '/../db.php';

echo "<h2>Database Structure Check</h2>";

// Check users table structure
echo "<h3>Users Table Structure:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check if audit_logs table exists
echo "<h3>Audit Logs Table:</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Table exists</p>";
        $stmt = $pdo->query("DESCRIBE audit_logs");
        $columns = $stmt->fetchAll();
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ Table does not exist</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check if disputes table exists
echo "<h3>Disputes Table:</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'disputes'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Table exists</p>";
        $stmt = $pdo->query("DESCRIBE disputes");
        $columns = $stmt->fetchAll();
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ Table does not exist</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='index.html'>← Back to Home</a></p>";
?>
