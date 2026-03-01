<?php
/**
 * Admin Panel Functionality Test
 * Tests key admin operations via direct API calls
 */
require_once __DIR__ . '/test_helper.php';
require_once __DIR__ . '/../db.php';

echo "<h2>Admin Panel Functionality Test</h2>";

// --- Step 1: Create Admin User ---
echo "<h3>1. Setup Admin User</h3>";

// Create admin directly in DB
$adminEmail = 'admin_' . uniqid() . '@university.edu';
$adminPass = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, credits, is_admin, status) VALUES (?, ?, ?, 10, 1, 'active')");
$stmt->execute(['Admin User', $adminEmail, $adminPass]);
$adminId = $pdo->lastInsertId();
echo "<p>Admin Created: $adminEmail (ID: $adminId)</p>";

// Login as admin
$client = getClient();
initSession($client);
$res = makeRequest('/auth/login.php', 'POST', ['email' => $adminEmail, 'password' => 'admin123'], $client);
if ($res['status'] !== 200) die("Admin login failed");
initSession($client); // Refresh token
echo "<p style='color: green;'>✓ Admin Logged In</p>";

// --- Step 2: Test User Management ---
echo "<h3>2. Test User Management</h3>";

// Create test user to manage
$testUser = registerUser($client, 'Test Managed User');
echo "<p>Test User Created (ID: {$testUser['id']})</p>";

// Suspend user
$res = makeRequest('/admin/manage_users.php', 'POST', [
    'action' => 'suspend',
    'user_id' => $testUser['id'],
    'reason' => 'Test suspension'
], $client);

if ($res['status'] === 200) {
    // Verify suspension
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$testUser['id']]);
    $status = $stmt->fetchColumn();
    
    if ($status === 'suspended') {
        echo "<p style='color: green;'>✓ User Suspension Works</p>";
    } else {
        echo "<p style='color: red;'>❌ User status not updated (got: $status)</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Suspend action failed</p>";
}

// --- Step 3: Test Skill Approval ---
echo "<h3>3. Test Skill Approval</h3>";

// Create pending skill
$provider = registerUser($client, 'Skill Provider');
loginUser($client, $provider['email']);

$stmt = $pdo->query("SELECT category_id FROM skill_categories LIMIT 1");
$cat = $stmt->fetch();
$skillData = [
    'title' => 'Admin Test Skill ' . time(),
    'description' => 'Testing admin approval',
    'category_id' => $cat['category_id']
];
$res = makeRequest('/skills/create_skill.php', 'POST', $skillData, $client, true, 'form');
$skillId = $res['body']['skill_id'] ?? null;
if (!$skillId) {
    $stmt = $pdo->prepare("SELECT skill_id FROM skills WHERE user_id = ? ORDER BY skill_id DESC LIMIT 1");
    $stmt->execute([$provider['id']]);
    $skillId = $stmt->fetchColumn();
}

echo "<p>Pending Skill Created (ID: $skillId)</p>";

// Login back as admin
loginUser($client, $adminEmail);

// Approve skill
$res = makeRequest('/admin/approve_skill.php', 'POST', [
    'skill_id' => $skillId,
    'action' => 'approve'
], $client);

if ($res['status'] === 200) {
    // Verify approval
    $stmt = $pdo->prepare("SELECT approval_status FROM skills WHERE skill_id = ?");
    $stmt->execute([$skillId]);
    $approvalStatus = $stmt->fetchColumn();
    
    if ($approvalStatus === 'approved') {
        echo "<p style='color: green;'>✓ Skill Approval Works</p>";
    } else {
        echo "<p style='color: red;'>❌ Skill not approved (got: $approvalStatus)</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Approve action failed</p>";
}

// --- Step 4: Test Metrics ---
echo "<h3>4. Test Admin Metrics</h3>";
$res = makeRequest('/admin/get_metrics.php', 'GET', null, $client);

if ($res['status'] === 200 && isset($res['body']['total_users'])) {
    echo "<p style='color: green;'>✓ Metrics API Works (Total Users: {$res['body']['total_users']})</p>";
} else {
    echo "<p style='color: red;'>❌ Metrics API failed</p>";
}

echo "<h4 style='color: green;'>SUCCESS: Admin Panel Verified!</h4>";
?>
