<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    // Get request parameters
    $toUserId = isset($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $userId = $_SESSION['user_id'];
    
    // Validate input
    if ($toUserId <= 0 || $userId === $toUserId) {
        throw new Exception('Invalid recipient');
    }
    
    if ($amount <= 0) {
        throw new Exception('Amount must be greater than 0');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check if recipient exists and get current credits for both users
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN user_id = ? THEN credits ELSE 0 END) as sender_credits,
                SUM(CASE WHEN user_id = ? THEN 1 ELSE 0 END) as recipient_exists
            FROM Users 
            WHERE user_id IN (?, ?)
            FOR UPDATE
        ");
        $stmt->execute([$userId, $toUserId, $userId, $toUserId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['recipient_exists'] == 0) {
            throw new Exception('Recipient not found');
        }
        
        if ($result['sender_credits'] < $amount) {
            throw new Exception('Insufficient credits');
        }
        
        // Deduct from sender
        $stmt = $pdo->prepare("
            UPDATE Users 
            SET credits = credits - ? 
            WHERE user_id = ? 
            AND credits >= ?
        ");
        $stmt->execute([$amount, $userId, $amount]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to deduct credits');
        }
        
        // Add to recipient
        $stmt = $pdo->prepare("
            UPDATE Users 
            SET credits = credits + ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$amount, $toUserId]);
        
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO Transactions 
            (from_user_id, to_user_id, credits, type, status)
            VALUES (?, ?, ?, 'credit_transfer', 'completed')
        ");
        $stmt->execute([$userId, $toUserId, $amount]);
        $transactionId = $pdo->lastInsertId();
        
        // Create notification for recipient
        $message = "You received {$amount} credits from a user";
        $stmt = $pdo->prepare("
            INSERT INTO Notifications 
            (user_id, type, message, related_id, related_type)
            VALUES (?, 'credit_received', ?, ?, 'transaction')
        ");
        $stmt->execute([$toUserId, $message, $transactionId]);
        
        // Log credit transfer (non-functional requirement: log all major actions)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Admin_Logs 
                (admin_id, action, target_user_id, details, ip_address, user_agent)
                VALUES (?, 'credit_transfer', ?, ?, ?, ?)
            ");
            $details = json_encode([
                'from_user_id' => $userId,
                'to_user_id' => $toUserId,
                'amount' => $amount,
                'transaction_id' => $transactionId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            $stmt->execute([
                $userId,
                $toUserId,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Don't fail transfer if logging fails
            error_log('Failed to log credit transfer: ' . $e->getMessage());
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Get updated balances
        $stmt = $pdo->prepare("
            SELECT user_id, credits 
            FROM Users 
            WHERE user_id IN (?, ?)
        ");
        $stmt->execute([$userId, $toUserId]);
        $balances = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        echo json_encode([
            'success' => true,
            'message' => 'Transfer completed successfully',
            'balances' => [
                'current_user' => $balances[$userId] ?? 0,
                'recipient' => $balances[$toUserId] ?? 0
            ],
            'transaction_id' => $transactionId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}