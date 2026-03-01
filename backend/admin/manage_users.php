<?php
require_once __DIR__ . '/../init.php';

// Check if user is admin
$adminId = requireAdmin();

// Get input data
$input = getJsonInput();
$userId = isset($input['user_id']) ? (int)$input['user_id'] : null;
$action = isset($input['action']) ? $input['action'] : '';
$reason = isset($input['reason']) ? trim($input['reason']) : '';

if (!$userId || !in_array($action, ['activate', 'suspend', 'deactivate', 'ban', 'unban', 'delete', 'make_admin', 'remove_admin'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Prevent modifying self
if ($userId === $adminId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot perform this action on your own account']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get user details
    $stmt = $pdo->prepare("SELECT id, email, is_banned, status, is_admin FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        throw new Exception('User not found');
    }
    
    $updates = [];
    $params = [];
    $logAction = '';
    $notificationType = '';
    $notificationMessage = '';
    
    switch ($action) {
        case 'activate':
            $updates[] = "status = 'active'";
            $updates[] = "is_banned = 0";
            $logAction = 'user_activated';
            $notificationType = 'account_activated';
            $notificationMessage = 'Your account has been activated by an administrator.';
            break;
            
        case 'suspend':
            $updates[] = "status = 'suspended'";
            $logAction = 'user_suspended';
            $notificationType = 'account_suspended';
            $notificationMessage = 'Your account has been suspended by an administrator.';
            if ($reason) $notificationMessage .= ' Reason: ' . $reason;
            break;

        case 'deactivate':
            $updates[] = "status = 'deactivated'";
            $logAction = 'user_deactivated';
            $notificationType = 'account_deactivated';
            $notificationMessage = 'Your account has been deactivated by an administrator.';
            break;
            
        case 'ban':
            $updates[] = "is_banned = 1";
            $logAction = 'user_banned';
            $notificationType = 'account_banned';
            $notificationMessage = 'Your account has been banned by an administrator.';
            if ($reason) $notificationMessage .= ' Reason: ' . $reason;
            break;
            
        case 'unban':
            $updates[] = "is_banned = 0";
            $logAction = 'user_unbanned';
            $notificationType = 'account_unbanned';
            $notificationMessage = 'Your account has been unbanned by an administrator.';
            break;
        
        case 'delete':
            // Instead of DELETE, we might want to anonymize or Soft Delete
            // But based on my delete_skill implementation, I might need to check dependencies or rely on CASCADE
            // Let's settle for hard delete as per previous file logic, referencing ON DELETE CASCADE in schema
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $logAction = 'user_deleted';
            // Cannot notify a deleted user
            $notificationType = null; 
            break;
            
        case 'make_admin':
            $updates[] = "is_admin = 1";
            $logAction = 'admin_promoted';
            $notificationType = 'admin_promoted';
            $notificationMessage = 'You have been promoted to administrator.';
            break;
            
        case 'remove_admin':
            $updates[] = "is_admin = 0";
            $logAction = 'admin_demoted';
            $notificationType = 'admin_demoted';
            $notificationMessage = 'Your administrator privileges have been removed.';
            break;
    }
    
    // Execute updates if not delete
    if ($action !== 'delete' && !empty($updates)) {
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $params = $updates; // wait, this array logic is wrong, need parameters? 
        // Actually, my updates array strings are literals right now. 
        // Let's fix this structure.
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    }
    
    // Log the action
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $adminId, $logAction, 'user', $userId, ['reason' => $reason, 'target_email' => $targetUser['email']]);
    
    // Add notification for the affected user
    if ($notificationType && $action !== 'delete') {
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, type, message, related_id, related_type, created_at)
            VALUES (?, ?, ?, ?, 'user', NOW())
        ");
        $stmt->execute([
            $userId,
            $notificationType,
            $notificationMessage,
            $adminId
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully',
        'action' => $action,
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to update user: ' . $e->getMessage()
    ]);
}
?>