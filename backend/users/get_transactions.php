<?php
require_once __DIR__ . '/../init.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check authentication
$userId = requireAuth();

// Get pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// Filter by type (optional)
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : null;

try {
    $whereClause = "WHERE (from_user_id = ? OR to_user_id = ?)";
    $params = [$userId, $userId];

    if ($type) {
        $whereClause .= " AND type = ?";
        $params[] = $type;
    }

    // Get total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    // Get transactions with details
    $sql = "
        SELECT 
            t.transaction_id,
            t.from_user_id,
            t.to_user_id,
            t.request_id,
            t.credits,
            t.created_at,
            t.status,
            t.type,
            t.description,
            u_from.name as from_user_name,
            u_to.name as to_user_name
        FROM transactions t
        LEFT JOIN users u_from ON t.from_user_id = u_from.id
        LEFT JOIN users u_to ON t.to_user_id = u_to.id
        $whereClause
        ORDER BY t.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for frontend
    $formattedTransactions = [];
    foreach ($transactions as $t) {
        $isCredit = $t['to_user_id'] == $userId;
        
        $formattedTransactions[] = [
            'id' => (int)$t['transaction_id'],
            'from_user_id' => (int)$t['from_user_id'],
            'to_user_id' => (int)$t['to_user_id'],
            'type' => $t['type'],
            'credits' => (int)$t['credits'],
            'is_credit' => $isCredit,
            'status' => $t['status'],
            'created_at' => $t['created_at'],
            'description' => $t['description'],
            'other_party' => $isCredit ? $t['from_user_name'] : $t['to_user_name'],
            'other_party_id' => $isCredit ? (int)$t['from_user_id'] : (int)$t['to_user_id']
        ];
    }

    echo json_encode([
        'success' => true,
        'transactions' => $formattedTransactions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Transaction fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch transactions']);
}
?>
