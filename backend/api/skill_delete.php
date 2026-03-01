<?php
ob_start();
require_once __DIR__ . '/../init.php';
ini_set('display_errors', '0');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    return true;
});
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit();
}

$userId = requireAuth();

try {
    // Handle both JSON and FormData
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isFormData = strpos($contentType, 'multipart/form-data') !== false;
    
    if ($isFormData) {
        $skillId = isset($_POST['skill_id']) ? (int)$_POST['skill_id'] : 0;
    } else {
        $data = getJsonInput();
        $skillId = isset($data['skill_id']) ? (int)$data['skill_id'] : 0;
    }
    
    if ($skillId <= 0) {
        throw new Exception('Skill ID is required');
    }

    // Verify ownership
    $stmt = $pdo->prepare("SELECT user_id, title FROM skills WHERE skill_id = ?");
    $stmt->execute([$skillId]);
    $skill = $stmt->fetch();

    if (!$skill) {
        http_response_code(404);
        throw new Exception('Skill not found');
    }

    if ($skill['user_id'] != $userId && !$_SESSION['is_admin']) {
        http_response_code(403);
        throw new Exception('Unauthorized to delete this skill');
    }

    // Check for active requests
    // Prevent deletion if there are pending or accepted requests
    $reqStmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE skill_id = ? AND status IN ('pending', 'accepted')");
    $reqStmt->execute([$skillId]);
    $activeRequests = $reqStmt->fetchColumn();

    if ($activeRequests > 0) {
        http_response_code(409); // Conflict
        throw new Exception('Cannot delete skill with active or pending exchange requests. Please resolve them first.');
    }

    // Soft delete (set active_status = 0) or Hard delete?
    // Implementation plan suggests deletion with auditing. Let's do hard delete but dependent tables should cascade or set null.
    // However, to keep history, soft delete is safer. Let's stick to requirements: "Add skill deletion"
    // I will implementation SOFT DELETE to preserve history in completed requests.
    
    $delStmt = $pdo->prepare("UPDATE skills SET active_status = 0 WHERE skill_id = ?");
    $delStmt->execute([$skillId]);

    // Log deletion
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $userId, 'skill_deleted', 'skill', $skillId, json_encode(['title' => $skill['title']]));

    ob_end_clean();
    echo json_encode([
        'ok' => true, 
        'message' => 'Skill deleted successfully'
    ]);
    exit();

} catch (PDOException $e) {
    ob_end_clean();
    error_log('Skill deletion DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error occurred']);
    exit();
} catch (Exception $e) {
    ob_end_clean();
    error_log('Skill deletion error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit();
}
?>
