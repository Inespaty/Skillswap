<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = min(isset($_GET['limit']) ? (int)$_GET['limit'] : 20, 100);
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM Transactions 
        WHERE from_user_id = ? OR to_user_id = ?
    ");
    $stmt->execute([$userId, $userId]);
    $total = $stmt->fetchColumn();
    
    // Get transactions
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            from_user.name as from_user_name,
            to_user.name as to_user_name,
            CASE 
                WHEN t.from_user_id = ? THEN 'debit'
                ELSE 'credit'
            END as transaction_direction
        FROM Transactions t
        LEFT JOIN Users from_user ON t.from_user_id = from_user.user_id
        LEFT JOIN Users to_user ON t.to_user_id = to_user.user_id
        WHERE t.from_user_id = ? OR t.to_user_id = ?
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $userId, $userId, $limit, $offset]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $result = array_map(function($t) use ($userId) {
        $isDebit = $t['from_user_id'] == $userId;
        return [
            'id' => $t['transaction_id'],
            'type' => $t['type'],
            'status' => $t['status'],
            'amount' => (float)$t['credits'],
            'direction' => $isDebit ? 'outgoing' : 'incoming',
            'counterparty' => $isDebit ? $t['to_user_name'] : $t['from_user_name'],
            'counterparty_id' => $isDebit ? $t['to_user_id'] : $t['from_user_id'],
            'created_at' => $t['created_at'],
            'description' => $this->getTransactionDescription($t, $userId)
        ];
    }, $transactions);
    
    echo json_encode([
        'success' => true,
        'transactions' => $result,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper function to generate transaction descriptions
function getTransactionDescription($transaction, $currentUserId) {
    $isDebit = $transaction['from_user_id'] == $currentUserId;
    $amount = (float)$transaction['credits'];
    
    switch ($transaction['type']) {
        case 'credit_transfer':
            return $isDebit 
                ? "Sent {$amount} credits to {$transaction['to_user_name']}"
                : "Received {$amount} credits from {$transaction['from_user_name']}";
            
        case 'skill_exchange':
            return $isDebit
                ? "Paid {$amount} credits for skill exchange"
                : "Earned {$amount} credits from skill exchange";
            
        case 'system_credit':
            return $amount >= 0 
                ? "Received {$amount} system credits"
                : "Deducted " . abs($amount) . " system credits";
                
        default:
            return $isDebit
                ? "Sent {$amount} credits"
                : "Received {$amount} credits";
    }
}