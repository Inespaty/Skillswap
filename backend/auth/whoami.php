<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// If there's a user session, return basic user info
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT id, name, email, credits, reputation_score, profile_pic, is_admin FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            echo json_encode(['ok' => true, 'user' => [
                'user_id' => (int)$user['id'],
                'id' => (int)$user['id'],
                'name' => $user['name'] ?? $user['email'],
                'email' => $user['email'],
                'credits' => isset($user['credits']) ? (int)$user['credits'] : 0,
                'reputation_score' => isset($user['reputation_score']) ? (float)$user['reputation_score'] : 0.0,
                'reputation' => isset($user['reputation_score']) ? (float)$user['reputation_score'] : 0.0,
                'profile_pic' => $user['profile_pic'] ?? 'assets/img/default-avatar.png',
                'is_admin' => !empty($user['is_admin']),
                'csrf_token' => generateCsrfToken()
            ]]);
            exit();
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'DB error', 'debug' => $e->getMessage()]);
        exit();
    }
}

// Not logged in
echo json_encode([
    'ok' => true, 
    'user' => null,
    'csrf_token' => generateCsrfToken()
]);
