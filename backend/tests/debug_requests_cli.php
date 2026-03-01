<?php
// Bypass init.php and connect directly
require_once __DIR__ . '/../db.php';

echo "<h2>Debug Requests (Direct DB)</h2>\n";

// List all users
echo "<h3>Users</h3>\n";
try {
    $stmt = $pdo->query("SELECT id, name, email FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "ID: {$user['id']} | Name: {$user['name']} | Email: {$user['email']}\n";
    }
} catch (Exception $e) {
    echo "Error fetching users: " . $e->getMessage() . "\n";
}

// Simulate API call for User 48 (Lewis) - Sent Requests
echo "\n<h3>Simulated API Response for User 48 (Sent)</h3>\n";
try {
    $userId = 48; // Lewis
    $where = ["r.from_user_id = ?"];
    $params = [$userId];
    $whereClause = implode(' AND ', $where);

    $sql = "
        SELECT 
            r.request_id, 
            r.status, 
            r.created_at, 
            r.hours_required,
            r.from_user_id,
            r.to_user_id,
            r.completed_by_requester,
            r.completed_by_helper,
            r.requester_confirmed_at,
            s.skill_id, 
            s.title as skill_title, 
            s.image as skill_image,
            u.id as other_user_id, 
            u.name as other_user_name, 
            u.profile_pic as other_user_pic,
            u.profile_pic as other_user_avatar,
            u.email as other_user_email
        FROM requests r
        JOIN skills s ON r.skill_id = s.skill_id
        JOIN users u ON r.to_user_id = u.id
        WHERE $whereClause
        ORDER BY r.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'requests' => $requests], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
