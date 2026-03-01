<?php
require_once __DIR__ . '/../init.php';

// Check if user is admin
$adminId = requireAdmin();

// Get input data
$input = getJsonInput();
$skillId = isset($input['skill_id']) ? (int)$input['skill_id'] : null;
$action = isset($input['action']) ? $input['action'] : ''; // 'approve' or 'reject'
$reason = isset($input['reason']) ? trim($input['reason']) : null; // required for rejection

if (!$skillId || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if ($action === 'reject' && empty($reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Reason is required for rejection']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Check if skill exists
    $stmt = $pdo->prepare("SELECT user_id, title, approval_status FROM skills WHERE skill_id = ?");
    $stmt->execute([$skillId]);
    $skill = $stmt->fetch();
    
    if (!$skill) {
        throw new Exception('Skill not found');
    }

    $updates = [];
    $params = [];
    $logAction = '';
    $notifMsg = '';
    $notifType = '';

    if ($action === 'approve') {
        $updates = "approval_status = 'approved', active_status = 1, approved_by = ?, approved_at = NOW(), rejection_reason = NULL";
        $params = [$adminId, $skillId];
        $logAction = 'skill_approved';
        $notifType = 'skill_approved';
        $notifMsg = "Your skill '{$skill['title']}' has been approved and is now live!";
    } else {
        $updates = "approval_status = 'rejected', active_status = 0, approved_by = ?, approved_at = NOW(), rejection_reason = ?";
        $params = [$adminId, $reason, $skillId];
        $logAction = 'skill_rejected';
        $notifType = 'skill_rejected';
        $notifMsg = "Your skill '{$skill['title']}' was rejected. Reason: $reason";
    }

    // Update skill
    $stmt = $pdo->prepare("UPDATE skills SET $updates WHERE skill_id = ?");
    $stmt->execute($params);

    // Log action
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $adminId, $logAction, 'skill', $skillId, ['title' => $skill['title'], 'reason' => $reason]);

    // Notify owner
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id, related_type, action_url) VALUES (?, ?, ?, ?, 'skill', ?)");
    $actionUrl = $action === 'approve' ? "skills.html?id=$skillId" : "profile.html"; // Redirect to profile to edit if rejected
    $stmt->execute([$skill['user_id'], $notifType, $notifMsg, $skillId, $actionUrl]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Skill " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully",
        'status' => $action === 'approve' ? 'approved' : 'rejected'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>