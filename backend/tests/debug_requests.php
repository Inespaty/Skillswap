<?php
require_once __DIR__ . '/../init.php';

echo "<h2>Debug Requests</h2>";

// List all users to identify IDs
echo "<h3>Users</h3>";
$stmt = $pdo->query("SELECT id, name, email FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Email</th></tr>";
foreach ($users as $user) {
    echo "<tr><td>{$user['id']}</td><td>{$user['name']}</td><td>{$user['email']}</td></tr>";
}
echo "</table>";

// List all requests
echo "<h3>Requests</h3>";
$stmt = $pdo->query("SELECT request_id, from_user_id, to_user_id, status, completed_by_helper, requester_confirmed_at FROM requests");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($requests)) {
    echo "<p>No requests found in database.</p>";
} else {
    echo "<table border='1'><tr><th>ID</th><th>From (Requester)</th><th>To (Helper)</th><th>Status</th><th>By Helper</th><th>Confirmed At</th></tr>";
    foreach ($requests as $req) {
        echo "<tr>
            <td>{$req['request_id']}</td>
            <td>{$req['from_user_id']}</td>
            <td>{$req['to_user_id']}</td>
            <td>{$req['status']}</td>
            <td>{$req['completed_by_helper']}</td>
            <td>{$req['requester_confirmed_at']}</td>
        </tr>";
    }
    echo "</table>";
}
?>
