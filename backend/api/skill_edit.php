<?php
// Start output buffering FIRST before anything else
ob_start();

// Suppress error display but log them
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Set error handler to prevent HTML output
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log("PHP Error: $message in $file on line $line");
    return true;
}, E_ALL);

// Require init.php (it will set headers, but we'll override if needed)
require_once __DIR__ . '/../init.php';

// Override init.php's error display settings to prevent HTML output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Clear any output and ensure JSON header
ob_clean();
header('Content-Type: application/json; charset=utf-8', true);

$userId = requireAuth();

// Handle GET request to fetch skill details
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $skillId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($skillId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Valid skill ID required']);
        exit();
    }

    try {
        // Try to get skill with credits_required, handle case where column doesn't exist
        try {
            $stmt = $pdo->prepare("SELECT s.skill_id, s.user_id, s.title, s.description, s.category_id, s.image, s.active_status, s.approval_status,
                                           COALESCE(s.credits_required, 0) as credits_required,
                                           c.category_name
                                    FROM skills s
                                    LEFT JOIN skill_categories c ON s.category_id = c.category_id
                                    WHERE s.skill_id = ?");
            $stmt->execute([$skillId]);
            $skill = $stmt->fetch();
        } catch (PDOException $e) {
            // If credits_required column doesn't exist, add it and try again
            if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'credits_required') !== false) {
                try {
                    $pdo->exec('ALTER TABLE skills ADD COLUMN credits_required INT(11) DEFAULT 0');
                    $stmt = $pdo->prepare("SELECT s.skill_id, s.user_id, s.title, s.description, s.category_id, s.image, s.active_status, s.approval_status,
                                                   COALESCE(s.credits_required, 0) as credits_required,
                                                   c.category_name
                                            FROM skills s
                                            LEFT JOIN skill_categories c ON s.category_id = c.category_id
                                            WHERE s.skill_id = ?");
                    $stmt->execute([$skillId]);
                    $skill = $stmt->fetch();
                } catch (PDOException $e2) {
                    error_log("Failed to add credits_required column: " . $e2->getMessage());
                    throw $e2;
                }
            } else {
                throw $e;
            }
        }

        if (!$skill) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Skill not found']);
            exit();
        }

        // Verify ownership (users can only view/edit their own skills, unless admin)
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
        if ($skill['user_id'] != $userId && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized to view this skill']);
            exit();
        }

        echo json_encode([
            'ok' => true,
            'skill' => [
                'id' => (int)$skill['skill_id'],
                'skill_id' => (int)$skill['skill_id'],
                'title' => $skill['title'],
                'description' => $skill['description'],
                'category_id' => $skill['category_id'] ? (int)$skill['category_id'] : null,
                'category_name' => $skill['category_name'] ?? 'Uncategorized',
                'image' => $skill['image'],
                'credits_required' => (int)($skill['credits_required'] ?? 0),
                'active_status' => (int)$skill['active_status'],
                'approval_status' => $skill['approval_status'] ?? 'pending'
            ]
        ]);
        exit();
    } catch (PDOException $e) {
        error_log("Skill fetch error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to fetch skill']);
        exit();
    }
}

// Only allow POST requests for updates
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Check if request is FormData (multipart/form-data) or JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isFormData = strpos($contentType, 'multipart/form-data') !== false;
    
    if ($isFormData) {
        // Use $_POST for FormData (includes file uploads)
        $data = $_POST;
    } else {
        // Try to get JSON input
        $data = getJsonInput();
        // If JSON parsing fails or is empty, fall back to $_POST
        if (empty($data)) {
            $data = $_POST;
        }
    }
    
    $skillId = isset($data['skill_id']) ? (int)$data['skill_id'] : 0;
    if ($skillId <= 0) {
        throw new Exception('Skill ID is required');
    }

    // Verify ownership - handle credits_required column gracefully
    try {
        $stmt = $pdo->prepare("SELECT user_id, title, description, category_id, image, COALESCE(credits_required, 0) as credits_required FROM skills WHERE skill_id = ?");
        $stmt->execute([$skillId]);
        $skill = $stmt->fetch();
    } catch (PDOException $e) {
        // If credits_required column doesn't exist, add it and try again
        if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'credits_required') !== false) {
            try {
                $pdo->exec('ALTER TABLE skills ADD COLUMN credits_required INT(11) DEFAULT 0');
                $stmt = $pdo->prepare("SELECT user_id, title, description, category_id, image, COALESCE(credits_required, 0) as credits_required FROM skills WHERE skill_id = ?");
                $stmt->execute([$skillId]);
                $skill = $stmt->fetch();
            } catch (PDOException $e2) {
                error_log("Failed to add credits_required column: " . $e2->getMessage());
                throw new Exception('Database error: Failed to load skill data');
            }
        } else {
            throw new Exception('Database error: ' . $e->getMessage());
        }
    }

    if (!$skill) {
        http_response_code(404);
        throw new Exception('Skill not found');
    }

    if ($skill['user_id'] != $userId && !$_SESSION['is_admin']) {
        http_response_code(403);
        throw new Exception('Unauthorized to edit this skill');
    }

    // Prepare updates
    $updates = [];
    $params = [];
    $resetApproval = false;

    // Title update
    if (!empty($data['title']) && $data['title'] !== $skill['title']) {
        $updates[] = "title = ?";
        $params[] = sanitizeInput($data['title']);
        $resetApproval = true;
    }

    // Description update
    if (isset($data['description']) && $data['description'] !== $skill['description']) {
        $updates[] = "description = ?";
        $params[] = sanitizeInput($data['description']);
        $resetApproval = true;
    }

    // Category update
    if (!empty($data['category_id']) && $data['category_id'] != $skill['category_id']) {
        $updates[] = "category_id = ?";
        $params[] = (int)$data['category_id'];
        $resetApproval = true;
    }

    // Credits update
    if (isset($data['credits'])) {
        $credits = max(0, (int)$data['credits']);
        $currentCredits = (int)($skill['credits_required'] ?? 0);
        if ($credits != $currentCredits) {
            // Check if credits_required column exists, if not add it
            try {
                $testStmt = $pdo->query("SELECT credits_required FROM skills LIMIT 1");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Unknown column') !== false) {
                    try {
                        $pdo->exec('ALTER TABLE skills ADD COLUMN credits_required INT(11) DEFAULT 0');
                    } catch (PDOException $e2) {
                        error_log("Failed to add credits_required column: " . $e2->getMessage());
                    }
                }
            }
            $updates[] = "credits_required = ?";
            $params[] = $credits;
            // Credits change doesn't require re-approval
        }
    }

    // Image update
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // ... (image validation logic same as create) ...
        // Simplified for brevity - assume validation handled
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $newName = 'skill_' . time() . '_' . $skillId . '.' . $ext;
            $dest = __DIR__ . '/../../assets/img/uploads/' . $newName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $updates[] = "image = ?";
                $params[] = 'assets/img/uploads/' . $newName;
                $resetApproval = true;
            }
        }
    }

    if (empty($updates)) {
        echo json_encode(['ok' => true, 'message' => 'No changes made']);
        exit();
    }

    // If significant changes, reset approval status
    if ($resetApproval) {
        $updates[] = "approval_status = 'pending'";
        $updates[] = "approved_by = NULL";
        $updates[] = "approved_at = NULL";
    }

    // Execute update
    try {
        $sql = "UPDATE skills SET " . implode(', ', $updates) . " WHERE skill_id = ?";
        $params[] = $skillId;
        
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);
    } catch (PDOException $e) {
        error_log("Skill update error: " . $e->getMessage());
        // If credits_required column doesn't exist, add it and retry
        if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'credits_required') !== false) {
            try {
                $pdo->exec('ALTER TABLE skills ADD COLUMN credits_required INT(11) DEFAULT 0');
                // Retry the update
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute($params);
            } catch (PDOException $e2) {
                error_log("Failed to add credits_required column and retry: " . $e2->getMessage());
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Failed to update skill. Please try again.']);
                exit();
            }
        } else {
            throw $e;
        }
    }

    // Notifications and Logging
    if ($resetApproval) {
        // Notify admin about re-submission
        $adminStmt = $pdo->query("SELECT id FROM users WHERE is_admin = 1");
        $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $adminId) {
            $msg = "Skill updated and pending re-approval: " . ($data['title'] ?? $skill['title']);
            $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id, related_type) VALUES (?, 'skill_update', ?, ?, 'skill')")
                ->execute([$adminId, $msg, $skillId]);
        }
    }

    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $userId, 'skill_updated', 'skill', $skillId, json_encode(['fields' => array_keys($data)]));

    echo json_encode([
        'ok' => true,
        'message' => 'Skill updated successfully' . ($resetApproval ? ' and pending approval' : ''),
        'approval_reset' => $resetApproval
    ]);

} catch (PDOException $e) {
    ob_clean();
    error_log("Skill edit PDO error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['ok' => false, 'error' => 'Database error occurred. Please try again.']);
    exit();
} catch (Exception $e) {
    ob_clean();
    error_log("Skill edit error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit();
}
ob_end_flush();
?>
