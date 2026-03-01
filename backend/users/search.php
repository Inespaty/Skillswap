<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit();
}

// Require authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit();
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(['ok' => true, 'users' => []]);
    exit();
}

try {
    // Basic search - sanitize and order by reputation (domain requirement: reputation affects visibility)
    $qLike = "%" . $q . "%";
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, profile_pic, reputation_score, credits 
        FROM Users 
        WHERE (name LIKE ? OR email LIKE ?) 
        AND user_id != ? 
        AND is_banned = 0
        ORDER BY reputation_score DESC, name ASC
        LIMIT 20
    ");
    $stmt->execute([$qLike, $qLike, $_SESSION['user_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    $users = [];
    foreach ($rows as $r) {
        $users[] = [
            'id' => (int)$r['user_id'],
            'user_id' => (int)$r['user_id'],
            'name' => $r['name'],
            'email' => $r['email'],
            'profile_pic' => $r['profile_pic'],
            'reputation_score' => isset($r['reputation_score']) ? (float)$r['reputation_score'] : 0.0,
            'credits' => isset($r['credits']) ? (int)$r['credits'] : 0
        ];
    }

    echo json_encode(['ok' => true, 'users' => $users]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error', 'debug' => $e->getMessage()]);
}
