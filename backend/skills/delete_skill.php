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
    // Get skill ID from request
    $skillId = isset($_POST['skill_id']) ? (int)$_POST['skill_id'] : 0;
    $userId = $_SESSION['user_id'];
    
    if ($skillId <= 0) {
        throw new Exception('Invalid skill ID');
    }

    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check if skill exists and user owns it
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COUNT(r.request_id) as active_requests
            FROM Skills s
            LEFT JOIN Requests r ON s.skill_id = r.skill_id 
                AND r.status IN ('pending', 'accepted')
            WHERE s.skill_id = ? AND s.user_id = ?
            GROUP BY s.skill_id
            FOR UPDATE
        ");
        $stmt->execute([$skillId, $userId]);
        $skill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$skill) {
            throw new Exception('Skill not found or access denied');
        }
        
        // Check for active requests
        if ($skill['active_requests'] > 0) {
            throw new Exception('Cannot delete skill with active or pending requests');
        }
        
        // Perform soft delete
        $stmt = $pdo->prepare("
            UPDATE Skills 
            SET active_status = 0 
            WHERE skill_id = ? AND user_id = ?
        ");
        $stmt->execute([$skillId, $userId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to delete skill');
        }
        
        // If the skill had an image, delete it (optional)
        if (!empty($skill['image']) && strpos($skill['image'], 'default-') === false) {
            $imagePath = __DIR__ . '/../../' . $skill['image'];
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Skill deleted successfully'
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