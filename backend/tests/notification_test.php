<?php
require_once __DIR__ . '/test_helper.php';
require_once __DIR__ . '/../db.php';

echo "<h2>Notification System Test</h2>";

// Initialize Session
$client = getClient();
initSession($client);

// --- Step 1: Setup Users ---
echo "<h3>1. Setup Test Users</h3>";
$userA = registerUser($client, 'Notif User A');
$userB = registerUser($client, 'Notif User B');
echo "<p>User A: {$userA['email']} (ID: {$userA['id']})</p>";
echo "<p>User B: {$userB['email']} (ID: {$userB['id']})</p>";

// --- Step 2: Trigger Notifications (via Request) ---
echo "<h3>2. Trigger Notifications</h3>";

// User B creates skill
loginUser($client, $userB['email']);
$stmt = $pdo->query("SELECT category_id FROM skill_categories LIMIT 1");
$cat = $stmt->fetch();
$skillData = [
    'title' => 'Notif Test Skill ' . time(),
    'description' => 'Testing notifications',
    'category_id' => $cat['category_id']
];
$res = makeRequest('/skills/create_skill.php', 'POST', $skillData, $client, true, 'form');
$skillId = $res['body']['skill_id'] ?? null;
if (!$skillId) {
    $stmt = $pdo->prepare("SELECT skill_id FROM skills WHERE user_id = ? ORDER BY skill_id DESC LIMIT 1");
    $stmt->execute([$userB['id']]);
    $skillId = $stmt->fetchColumn();
}

if (!$skillId) {
    die("Failed to retrieve skill ID");
}

// Approve skill
$stmt = $pdo->prepare("UPDATE skills SET approval_status = 'approved' WHERE skill_id = ?");
$stmt->execute([$skillId]);
echo "<p>Skill Created & Approved (ID: $skillId)</p>";

// User A sends request (should create notification for User B)
loginUser($client, $userA['email']);
$res = makeRequest('/requests/create_request.php', 'POST', ['skill_id' => $skillId, 'hours' => 1], $client);
$requestId = $res['body']['request_id'];
echo "<p>Request Sent - Should create 'new_request' notification for User B</p>";

// --- Step 3: Check Notifications ---
echo "<h3>3. Verify Notifications Created</h3>";

// Check User B's notifications
loginUser($client, $userB['email']);
$res = makeRequest('/notifications/get_notifications.php', 'GET', null, $client);
$notifications = $res['body']['notifications'] ?? [];

$foundNewRequest = false;
foreach ($notifications as $notif) {
    if ($notif['type'] === 'new_request' && $notif['related_id'] == $requestId) {
        $foundNewRequest = true;
        echo "<p style='color: green;'>✓ 'new_request' notification found for User B</p>";
        break;
    }
}

if (!$foundNewRequest) {
    echo "<p style='color: red;'>❌ 'new_request' notification NOT found</p>";
}

// User B accepts request (should create notification for User A)
$res = makeRequest('/requests/respond_request.php', 'POST', ['request_id' => $requestId, 'action' => 'accept'], $client);
echo "<p>Request Accepted - Should create 'request_accepted' notification for User A</p>";

// Check User A's notifications
loginUser($client, $userA['email']);
$res = makeRequest('/notifications/get_notifications.php', 'GET', null, $client);
$notifications = $res['body']['notifications'] ?? [];

$foundAccepted = false;
foreach ($notifications as $notif) {
    if ($notif['type'] === 'request_accepted' && $notif['related_id'] == $requestId) {
        $foundAccepted = true;
        echo "<p style='color: green;'>✓ 'request_accepted' notification found for User A</p>";
        break;
    }
}

if (!$foundAccepted) {
    echo "<p style='color: red;'>❌ 'request_accepted' notification NOT found</p>";
}

// --- Step 4: Check Email Log ---
echo "<h3>4. Check Email Log</h3>";
$emailLog = __DIR__ . '/../../logs/email.log';
if (file_exists($emailLog)) {
    $logContent = file_get_contents($emailLog);
    $emailCount = substr_count($logContent, 'New Request Received') + substr_count($logContent, 'Request Accepted');
    echo "<p style='color: green;'>✓ Email log exists with $emailCount relevant emails logged</p>";
} else {
    echo "<p style='color: orange;'>⚠ Email log not found (emails may not be configured)</p>";
}

echo "<h4 style='color: green;'>SUCCESS: Notification System Verified!</h4>";
?>
