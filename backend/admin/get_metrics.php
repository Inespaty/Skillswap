<?php
require_once __DIR__ . '/../init.php';

// Check if user is admin
requireAdmin();

try {
    // Get total active users (not banned)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_banned = 0");
    $totalUsers = $stmt->fetch()['count'];
    
    // Get total completed transactions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'completed'");
    $totalTransactions = $stmt->fetch()['count'];
    
    // Get active skills count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM skills WHERE active_status = 1 AND approval_status = 'approved'");
    $activeSkills = $stmt->fetch()['count'];
    
    // Get pending skill approvals
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM skills WHERE approval_status = 'pending'");
    $pendingApprovals = $stmt->fetch()['count'];

    // Disputes query removed
    
    // Get most popular skills (by number of completed requests)
    $stmt = $pdo->query("
        SELECT 
            s.skill_id,
            s.title,
            s.description,
            s.image,
            COUNT(r.request_id) as request_count
        FROM skills s
        LEFT JOIN requests r ON s.skill_id = r.skill_id AND r.status = 'completed'
        WHERE s.active_status = 1 AND s.approval_status = 'approved'
        GROUP BY s.skill_id
        ORDER BY request_count DESC
        LIMIT 10
    ");
    $popularSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user growth data (last 12 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $userGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent users
    $stmt = $pdo->query("
        SELECT 
            id as user_id,
            name,
            email,
            profile_pic,
            is_banned,
            status,
            created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get transaction history (last 30 days)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d') as date,
            COUNT(*) as count
        FROM transactions
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status = 'completed'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')
        ORDER BY date ASC
    ");
    $transactionHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'metrics' => [
            'totalUsers' => (int)$totalUsers,
            'totalTransactions' => (int)$totalTransactions,
            'activeSkills' => (int)$activeSkills,
            'pendingApprovals' => (int)$pendingApprovals,
            'popularSkills' => $popularSkills,
            'userGrowth' => $userGrowth,
            'recentUsers' => $recentUsers,
            'transactionHistory' => $transactionHistory
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
