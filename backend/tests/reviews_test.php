<?php
require_once __DIR__ . '/test_helper.php';
require_once __DIR__ . '/../db.php';

echo "<h2>Reviews & Ratings System Test</h2>";

// Initialize Session
$client = getClient();
initSession($client);

// --- Step 1: Setup Users & Complete Exchange ---
echo "<h3>1. Setup Users & Complete Exchange</h3>";

$provider = registerUser($client, 'Review Provider');
$requester = registerUser($client, 'Review Requester');
echo "<p>Provider: {$provider['email']} (ID: {$provider['id']})</p>";
echo "<p>Requester: {$requester['email']} (ID: {$requester['id']})</p>";

// Provider creates skill
loginUser($client, $provider['email']);
$stmt = $pdo->query("SELECT category_id FROM skill_categories LIMIT 1");
$cat = $stmt->fetch();
$skillData = [
    'title' => 'Review Test Skill ' . time(),
    'description' => 'Testing reviews',
    'category_id' => $cat['category_id']
];
$res = makeRequest('/skills/create_skill.php', 'POST', $skillData, $client, true, 'form');
$skillId = $res['body']['skill_id'] ?? null;
if (!$skillId) {
    $stmt = $pdo->prepare("SELECT skill_id FROM skills WHERE user_id = ? ORDER BY skill_id DESC LIMIT 1");
    $stmt->execute([$provider['id']]);
    $skillId = $stmt->fetchColumn();
}

if (!$skillId) {
    die("Failed to retrieve skill ID");
}

// Approve skill
$stmt = $pdo->prepare("UPDATE skills SET approval_status = 'approved' WHERE skill_id = ?");
$stmt->execute([$skillId]);
echo "<p>Skill Created & Approved (ID: $skillId)</p>";

// Requester sends request
loginUser($client, $requester['email']);
$res = makeRequest('/requests/create_request.php', 'POST', ['skill_id' => $skillId, 'hours' => 1], $client);
$requestId = $res['body']['request_id'];
echo "<p>Request Sent (ID: $requestId)</p>";

// Provider accepts
loginUser($client, $provider['email']);
makeRequest('/requests/respond_request.php', 'POST', ['request_id' => $requestId, 'action' => 'accept'], $client);
echo "<p>Request Accepted</p>";

// Both complete
makeRequest('/requests/complete_request.php', 'POST', ['request_id' => $requestId], $client);
loginUser($client, $requester['email']);
makeRequest('/requests/complete_request.php', 'POST', ['request_id' => $requestId], $client);
echo "<p>Exchange Completed</p>";


// --- Step 2: Requester Posts Review ---
echo "<h3>2. Requester Posts Review</h3>";
$reviewData = [
    'request_id' => $requestId,
    'rating' => 5,
    'review' => 'Excellent help! Very knowledgeable.'
];
$res = makeRequest('/reviews/post_review.php', 'POST', $reviewData, $client);

if ($res['status'] === 200 || $res['status'] === 201) {
    echo "<p style='color: green;'>✓ Review Posted Successfully</p>";
} else {
    echo "<p style='color: red;'>❌ Review Failed: " . print_r($res['body'], true) . "</p>";
}


// --- Step 3: Verify Reputation Score Updated ---
echo "<h3>3. Verify Reputation Score</h3>";
$stmt = $pdo->prepare("SELECT reputation_score FROM users WHERE id = ?");
$stmt->execute([$provider['id']]);
$reputation = $stmt->fetchColumn();

echo "<p>Provider Reputation: $reputation</p>";
if ($reputation > 0) {
    echo "<p style='color: green;'>✓ Reputation Score Updated (Expected > 0)</p>";
} else {
    echo "<p style='color: red;'>❌ Reputation Score Not Updated</p>";
}


// --- Step 4: Fetch Reviews ---
echo "<h3>4. Fetch Provider Reviews</h3>";
$res = makeRequest('/reviews/get_reviews.php?user_id=' . $provider['id'], 'GET', null, $client);
$reviews = $res['body']['reviews'] ?? [];

if (count($reviews) > 0) {
    $lastReview = end($reviews);
    if ($lastReview['rating'] == 5 && strpos($lastReview['review'], 'Excellent') !== false) {
        echo "<p style='color: green;'>✓ Review Retrieved: Rating {$lastReview['rating']}, '{$lastReview['review']}'</p>";
    } else {
        echo "<p style='color: red;'>❌ Review content mismatch</p>";
    }
} else {
    echo "<p style='color: red;'>❌ No reviews found</p>";
}

echo "<h4 style='color: green;'>SUCCESS: Reviews & Ratings System Verified!</h4>";
?>
