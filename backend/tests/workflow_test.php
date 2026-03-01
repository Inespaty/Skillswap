<?php
/**
 * Skill Exchange Workflow Test
 * Simulates a full interaction between two users:
 * 1. User A (Provider) creates a Skill
 * 2. User B (Requester) requests the Skill
 * 3. User A accepts the request
 * 4. Both users mark it as complete
 * 5. Verify Credit Transfer (B -1, A +1)
 */

require_once __DIR__ . '/test_helper.php';
require_once __DIR__ . '/../db.php';

$client = getClient();
echo "<h2>Skill Exchange Workflow Test</h2>";

// --- Helper Functions ---

// --- Step 0: Initialize Session (Get CSRF Token) ---

// --- Step 0: Initialize Session (Get CSRF Token) ---
initSession($client);

// --- Step 1: Setup Users ---
echo "<h3>1. Setup Users</h3>";
$provider = registerUser($client, 'Skill Provider');
echo "<p>Created Provider: {$provider['email']} (ID: {$provider['id']})</p>";

$requester = registerUser($client, 'Skill Requester');
echo "<p>Created Requester: {$requester['email']} (ID: {$requester['id']})</p>";


// --- Step 2: Provider creates a Skill ---
echo "<h3>2. Provider Creates Skill</h3>";
loginUser($client, $provider['email']);

$skillData = [
    'title' => 'Math Tutoring ' . time(),
    'description' => 'I can help with Calculus',
    'category' => 'Academic', // Assuming 'Academic' exists, if not need to fetch categories first. 
                              // Let's use a generic ID or existing category name if dynamic.
                              // Actually implementation might require category_id or name.
                              // Let's check api/skills/create_skill.php inputs.
                              // Assuming category name is fine or we fallback to ID 1.
    'category_id' => 1 // Safest bet if we don't know names, usually 1 exists.
];

// Check db for categories just in case
// Check db for categories just in case
$stmt = $pdo->query("SELECT category_id FROM skill_categories LIMIT 1");
$cat = $stmt->fetch();
if ($cat) $skillData['category_id'] = $cat['category_id'];

$res = makeRequest('/skills/create_skill.php', 'POST', $skillData, $client, true, 'form');
if ($res['status'] !== 200 && $res['status'] !== 201) die("Failed to create skill: " . print_r($res['body'], true));

$skillId = $res['body']['skill_id']; // Assuming response structure
// If response uses 'skill_id' or 'id', let's check. 
// Standard is often 'message' and maybe data. 
// If create_skill doesn't return ID, we need to fetch it.
if (!isset($skillId)) {
    // Try to fetch latest skill by user
    $stmt = $pdo->prepare("SELECT skill_id FROM skills WHERE user_id = ? ORDER BY skill_id DESC LIMIT 1");
    $stmt->execute([$provider['id']]);
    $skillId = $stmt->fetchColumn();
}
echo "<p style='color: green;'>✓ Skill Created (ID: $skillId)</p>";

// --- Step 2.5: Admin Approves Skill (Direct DB Update for Test) ---
echo "<h3>2.5. Admin Approves Skill</h3>";
$stmt = $pdo->prepare("UPDATE skills SET approval_status = 'approved' WHERE skill_id = ?");
$stmt->execute([$skillId]);
echo "<p>Skill manually approved for testing.</p>";


// --- Step 3: Requester sends Request ---
echo "<h3>3. Requester Sends Request</h3>";
loginUser($client, $requester['email']);

$reqData = [
    'skill_id' => $skillId,
    'hours' => 1,
    'date' => date('Y-m-d', strtotime('+1 day')),
    'time' => '10:00',
    'message' => 'Please help me!'
];

$res = makeRequest('/requests/create_request.php', 'POST', $reqData, $client);
if ($res['status'] !== 200 && $res['status'] !== 201) {
    echo "<p style='color: red;'>❌ Failed to create request: " . print_r($res['body'], true) . "</p>";
} else {
    echo "<p style='color: green;'>✓ Request Sent. (Req ID: " . $res['body']['request_id'] . ")</p>";
    $requestId = $res['body']['request_id'];
}

if (!isset($requestId)) die("Cannot proceed without request ID");


// --- Step 4: Provider Accepts Request ---
echo "<h3>4. Provider Accepts Request</h3>";
loginUser($client, $provider['email']);

$respondData = [
    'request_id' => $requestId,
    'action'        => 'accept'
];

$res = makeRequest('/requests/respond_request.php', 'POST', $respondData, $client);
if ($res['status'] === 200) {
    echo "<p style='color: green;'>✓ Request Accepted.</p>";
} else {
    die("Failed to accept request: " . print_r($res['body'], true));
}


// --- Step 5: Both Mark as Complete ---
echo "<h3>5. Both Users Mark Complete</h3>";

// Provider Marks Complete
echo "<p>Provider marking complete...</p>";
$completeData = ['request_id' => $requestId];
$res = makeRequest('/requests/complete_request.php', 'POST', $completeData, $client);
if ($res['status'] !== 200) echo "<p style='color:orange'>Provider complete warning: " . print_r($res['body'], true) . "</p>";
else echo "<p>Provider marked complete.</p>";

// Requester Marks Complete
echo "<p>Requester marking complete...</p>";
loginUser($client, $requester['email']);
$res = makeRequest('/requests/complete_request.php', 'POST', $completeData, $client);

if ($res['status'] === 200 && isset($res['body']['status']) && $res['body']['status'] === 'completed') {
    echo "<p style='color: green;'>✓ Exchange Finished! Credit transfer triggered.</p>";
} else {
    echo "<p style='color: red;'>❌ Exchange finish failed: ".print_r($res['body'],true)."</p>";
}


// --- Step 6: Verify Credits ---
echo "<h3>6. Verify Credit Balances</h3>";
// Provider should have 10 + 1 = 11
// Requester should have 10 - 1 = 9

$stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$provider['id']]);
$provCredits = $stmt->fetchColumn();

$stmt->execute([$requester['id']]);
$reqCredits = $stmt->fetchColumn();

echo "<p>Provider Credits: $provCredits (Expected 11)</p>";
echo "<p>Requester Credits: $reqCredits (Expected 9)</p>";

if ($provCredits == 11 && $reqCredits == 9) {
    echo "<h4 style='color: green;'>SUCCESS: Workflow Verification Complete!</h4>";
} else {
    echo "<h4 style='color: red;'>FAILURE: Credit math incorrect.</h4>";
}
?>
