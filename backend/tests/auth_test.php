<?php
/**
 * Auth Flow Integration Test (Refactored)
 * Tests Registration and Login using Test Helper (CSRF/Cookies)
 */

require_once __DIR__ . '/test_helper.php';
require_once __DIR__ . '/../db.php'; // For DB verification if needed

$client = getClient();

echo "<h2>Authentication System Tests</h2>";

// Initialize Session (Get CSRF Token)
initSession($client);

// 1. Test Registration with Previously Invalid Email Domain (gmail.com) - NOW ALLOWED
echo "<h3>Test 1: Register with Gmail (gmail.com) - Should Succeed</h3>";
$gmailUser = [
    'name' => 'Gmail User',
    'email' => 'testuser_' . time() . '@gmail.com',
    'password' => 'password123'
];
$res = makeRequest('/auth/register.php', 'POST', $gmailUser, $client);

if ($res['status'] === 201) {
    echo "<p style='color: green;'>✓ Passed: Registration allowed for gmail.com.</p>";
} else {
    echo "<p style='color: red;'>❌ Failed: Expected 201 for gmail.com, got {$res['status']}</p>";
    echo "<pre>"; print_r($res['body']); echo "</pre>";
}

// 1b. Test Registration with truly invalid domain (e.g., yahoo.com if not allowed, or random)
echo "<h3>Test 1b: Register with Invalid Domain (yahoo.com)</h3>";
$invalidUser = [
    'name' => 'Bad User',
    'email' => 'testuser_' . time() . '@yahoo.com',
    'password' => 'password123'
];
$res = makeRequest('/auth/register.php', 'POST', $invalidUser, $client);

if ($res['status'] === 400 && strpos($res['body']['error'], 'student email') !== false) {
    echo "<p style='color: green;'>✓ Passed: Registration rejected for non-student email (yahoo.com).</p>";
} else {
    echo "<p style='color: red;'>❌ Failed: Expected 400 for yahoo.com, got {$res['status']}</p>";
}

// 2. Test Registration with Valid Email Domain
echo "<h3>Test 2: Register with Valid Email (.edu)</h3>";
$validEmail = 'testuser' . time() . '@university.edu';
$validUser = [
    'name' => 'Test Student',
    'email' => $validEmail,
    'password' => 'password123'
];
$res = makeRequest('/auth/register.php', 'POST', $validUser, $client);

if ($res['status'] === 201) {
    echo "<p style='color: green;'>✓ Passed: Registration successful for .edu email.</p>";
} else {
    echo "<p style='color: red;'>❌ Failed: Expected 201, got {$res['status']}</p>";
    echo "<pre>"; print_r($res['body']); echo "</pre>";
}

// 3. Test Login
echo "<h3>Test 3: Login with New Account</h3>";
$loginData = [
    'email' => $validEmail,
    'password' => 'password123'
];
$res = makeRequest('/auth/login.php', 'POST', $loginData, $client);

if ($res['status'] === 200) {
    // Determine if token is returned or just success
    echo "<p style='color: green;'>✓ Passed: Login successful.</p>";
    
    // Update CSRF token if login refreshed it?
    // Usually login might regenerate session id, so we might need new CSRF token.
    // Let's call initSession again just to be sure, or check response.
    initSession($client); 
} else {
    echo "<p style='color: red;'>❌ Failed: Login failed with {$res['status']}</p>";
    echo "<pre>"; print_r($res['body']); echo "</pre>";
}

// 4. Test Login with Wrong Password
echo "<h3>Test 4: Login with Wrong Password</h3>";
$badLogin = [
    'email' => $validEmail,
    'password' => 'wrongpass'
];
$res = makeRequest('/auth/login.php', 'POST', $badLogin, $client);

if ($res['status'] === 401) {
    echo "<p style='color: green;'>✓ Passed: Login rejected for wrong password.</p>";
} else {
    echo "<p style='color: red;'>❌ Failed: Expected 401, got {$res['status']}</p>";
    echo "<pre>"; print_r($res['body']); echo "</pre>";
}
?>
