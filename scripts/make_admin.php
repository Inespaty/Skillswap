<?php
/**
 * Quick script to make a user an admin
 * Usage: php make_admin.php <user_id>
 */

require_once __DIR__ . '/../backend/db.php';

if ($argc < 2) {
    echo "Usage: php make_admin.php <user_id>\n";
    echo "\nTo find user IDs, run:\n";
    echo "php make_admin.php list\n";
    exit(1);
}

if ($argv[1] === 'list') {
    echo "=== All Users ===\n";
    $stmt = $pdo->query("SELECT id, name, email, is_admin FROM users ORDER BY id");
    while ($row = $stmt->fetch()) {
        $admin = $row['is_admin'] ? '[ADMIN]' : '';
        echo sprintf("ID: %d | %s | %s %s\n", $row['id'], $row['name'], $row['email'], $admin);
    }
    exit(0);
}

$userId = (int)$argv[1];

// Check if user exists
$stmt = $pdo->prepare("SELECT id, name, email, is_admin FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo "Error: User ID $userId not found.\n";
    exit(1);
}

if ($user['is_admin']) {
    echo "User '{$user['name']}' is already an admin.\n";
    exit(0);
}

// Make user admin
$stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
$stmt->execute([$userId]);

echo "✓ Success! User '{$user['name']}' ({$user['email']}) is now an admin.\n";
echo "\nYou can now log in with this account to access the admin panel at:\n";
echo "http://localhost:8080/admin.html\n";
