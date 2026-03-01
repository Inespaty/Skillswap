<?php
/**
 * Credit Test (Refactored)
 * Verifies initial credit assignment using Test Helper
 */

require_once __DIR__ . '/test_helper.php';
require_once __DIR__ . '/../db.php';

$client = getClient();
echo "<h2>Credit System Tests</h2>";

// Initialize Session
initSession($client);

// Create dedicated credit test user
$testEmail = 'credit_test_' . time() . '@university.edu';
$userData = [
    'name' => 'Credit Tester',
    'email' => $testEmail,
    'password' => 'password123'
];

// Register
$res = makeRequest('/auth/register.php', 'POST', $userData, $client);
if ($res['status'] !== 201) {
    die("<p style='color: red;'>❌ Setup Failed: Could not register user. " . print_r($res['body'], true) . "</p>");
}

// Login to check credits via API (whoami)
$loginData = ['email' => $testEmail, 'password' => 'password123'];
$res = makeRequest('/auth/login.php', 'POST', $loginData, $client);

if ($res['status'] === 200) {
    // Call whoami to get profile data
    $res = makeRequest('/auth/whoami.php', 'GET', null, $client);
    
    if (isset($res['body']['user']['credits'])) {
        $credits = $res['body']['user']['credits'];
        if ($credits == 10) {
            echo "<p style='color: green;'>✓ Passed: User starts with 10 credits (verified via API).</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed: Expected 10 credits, got $credits.</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Failed: Could not retrieve user profile from whoami.</p>";
    }
    
    // Verify via DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$testEmail]);
    $user = $stmt->fetch();
    
    if ($user && $user['credits'] == 10) {
        echo "<p style='color: green;'>✓ Passed: Verified via DB: Credits = 10.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed: DB check failed.</p>";
    }

    // Verify Transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE from_user_id = ? OR to_user_id = ?");
    $stmt->execute([$user['id'], $user['id']]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "<p style='color: green;'>✓ Passed: No transaction history for new user.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed: Found $count transactions.</p>";
    }

} else {
    echo "<p style='color: red;'>❌ Setup Failed: Login failed.</p>";
}
?>
