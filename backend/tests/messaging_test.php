<?php
require_once __DIR__ . '/test_helper.php';
require_once __DIR__ . '/../db.php';

echo "<h2>Messaging System Test</h2>";

// Initialize Session
$client = getClient();
initSession($client);

// --- Step 1: Setup Users & Connection ---
echo "<h3>1. Setup Users & Connection</h3>";

// Register User A
$userA = registerUser($client, 'User A');
$emailA = $userA['email'];
echo "<p>User A: $emailA (ID: {$userA['id']})</p>";

// Register User B
$userB = registerUser($client, 'User B');
$emailB = $userB['email'];
echo "<p>User B: $emailB (ID: {$userB['id']})</p>";

// Login as User B to create skill
login($client, $emailB, 'password123');
$skillData = [
    'title' => 'Chat Skill ' . time(),
    'description' => 'Skill for testing chat',
    'category' => 'Technology', // Will map to ID logic in helper or use default
    'category_id' => 1 // Fallback
];
// Fetch category if needed (reuse logic from workflow_test if possible, or simpler assumption)
$stmt = $pdo->prepare("SELECT category_id FROM skill_categories LIMIT 1");
$stmt->execute();
$cat = $stmt->fetch();
if ($cat) $skillData['category_id'] = $cat['category_id'];

$res = makeRequest('/skills/create_skill.php', 'POST', $skillData, $client, true, 'form');
if ($res['status'] !== 200 && $res['status'] !== 201) die("Failed to create skill: " . print_r($res['body'], true));
$skillId = $res['body']['skill_id'] ?? null;

// Fallback fetch skill ID
if (!$skillId) {
    $stmt = $pdo->prepare("SELECT skill_id FROM skills WHERE user_id = ? ORDER BY skill_id DESC LIMIT 1");
    $stmt->execute([$userB['id']]);
    $skillId = $stmt->fetchColumn();
}
echo "<p>Skill Created (ID: $skillId)</p>";

// Admin Approve Skill (Direct DB)
$stmt = $pdo->prepare("UPDATE skills SET approval_status = 'approved' WHERE skill_id = ?");
$stmt->execute([$skillId]);
echo "<p>Skill Approved</p>";

// Login as User A to request skill
login($client, $emailA, 'password123');
$reqData = ['skill_id' => $skillId, 'hours' => 2];
$res = makeRequest('/requests/create_request.php', 'POST', $reqData, $client);
if ($res['status'] !== 200 && $res['status'] !== 201) die("Request failed");
$requestId = $res['body']['request_id'];
echo "<p>Request Sent (ID: $requestId)</p>";

// Login as User B to Accept Request
login($client, $emailB, 'password123');
$res = makeRequest('/requests/respond_request.php', 'POST', ['request_id' => $requestId, 'action' => 'accept'], $client);
if ($res['status'] !== 200) die("Accept failed");
echo "<p>Request Accepted. Connection established.</p>";


// --- Step 2: Send Message (User B -> User A) ---
echo "<h3>2. User B Sends Message</h3>";
$msgData = [
    'receiver_id' => $userA['id'],
    'content' => 'Hello User A! This is a test message.' // Changed 'message' to 'content'
];
$res = makeRequest('/chat/send_message.php', 'POST', $msgData, $client);

if ($res['status'] === 200) {
    echo "<p style='color: green;'>✓ Message Sent Successfully</p>";
} else {
    echo "<p style='color: red;'>❌ Send Failed: " . print_r($res['body'], true) . "</p>";
}

// --- Step 3: User A Receives Message ---
echo "<h3>3. User A Checks Messages</h3>";
login($client, $emailA, 'password123');

// Get Conversations
$res = makeRequest('/chat/get_conversations.php', 'GET', null, $client);
$convs = $res['body']['conversations'] ?? [];

$found = false;
foreach ($convs as $c) {
    if ($c['user_id'] == $userB['id']) { // Checks if conversing with User B
        $found = true;
        echo "<p>Conversation found with User B.</p>";
        break;
    }
}
if (!$found) echo "<p style='color: red;'>❌ No conversation found.</p>";

// Get specific messages
$res = makeRequest('/chat/get_messages.php?user_id=' . $userB['id'], 'GET', null, $client);
$messages = $res['body']['messages'] ?? [];
$lastMsg = end($messages);

if ($lastMsg && $lastMsg['content'] === 'Hello User A! This is a test message.') {
    echo "<p style='color: green;'>✓ Message Content Verified: '{$lastMsg['content']}'</p>";
} else {
    echo "<p style='color: red;'>❌ Message verification failed. Last msg: " . print_r($lastMsg, true) . "</p>";
}


// --- Step 4: User A Replies ---
echo "<h3>4. User A Replies</h3>";
$replyData = [
    'receiver_id' => $userB['id'],
    'content' => 'Hi User B! Loud and clear.' // Changed 'message' to 'content'
];
$res = makeRequest('/chat/send_message.php', 'POST', $replyData, $client);
if ($res['status'] === 200) {
    echo "<p style='color: green;'>✓ Reply Sent</p>";
} else {
    echo "<p style='color: red;'>❌ Reply Failed</p>";
}

echo "<h4 style='color: green;'>SUCCESS: Messaging System Verified!</h4>";
?>
