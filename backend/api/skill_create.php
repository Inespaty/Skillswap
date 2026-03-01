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

require_once __DIR__ . '/../init.php';

// Override init.php's error display settings to prevent HTML output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Clear any output and ensure JSON header
ob_clean();
header('Content-Type: application/json; charset=utf-8', true);

// Must be logged in
$userId = requireAuth();

// Accept multipart/form-data for image upload
try {
    // Validate required fields
    $validationErrors = validateRequired($_POST, ['title']);
    if (!empty($validationErrors)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => implode(', ', $validationErrors)]);
        exit();
    }

    // Sanitize and validate inputs
    $title = sanitizeInput($_POST['title'], 'string');
    $description = isset($_POST['description']) ? sanitizeInput($_POST['description'], 'text') : null;
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $credits = isset($_POST['credits']) ? max(0, (int)$_POST['credits']) : 0; // Credits required for this skill

    // Validate lengths
    if (!validateLength($title, 3, 100)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Title must be between 3 and 100 characters']);
        exit();
    }

    if ($description && !validateLength($description, 0, 1000)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Description must be less than 1000 characters']);
        exit();
    }

    // Validate category_id if provided
    if ($category_id !== null && !validateInt($category_id, 1)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid category ID']);
        exit();
    }

    // Handle image upload if sent
    $imagePath = null;
    if (!empty($_FILES['image'])) {
        // Validate file upload
        $fileErrors = validateFileUpload($_FILES['image']);
        if (!empty($fileErrors)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => implode(', ', $fileErrors)]);
            exit();
        }

        $uploadDir = realpath(__DIR__ . '/../../assets/img') . DIRECTORY_SEPARATOR . 'uploads';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $origName = basename($_FILES['image']['name']);
        $finalName = generateSecureFilename($origName);
        $dest = $uploadDir . DIRECTORY_SEPARATOR . $finalName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $imagePath = 'assets/img/uploads/' . $finalName;
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Failed to save uploaded file']);
            exit();
        }
    }

    // Insert skill with auto-approval (active and approved immediately)
    // Check if credits_required column exists, if not add it
    try {
        $testStmt = $pdo->query("SELECT credits_required FROM skills LIMIT 1");
        $columnExists = true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            try {
                $pdo->exec('ALTER TABLE skills ADD COLUMN credits_required INT(11) DEFAULT 0');
                $columnExists = true;
            } catch (PDOException $e2) {
                error_log("Failed to add credits_required column: " . $e2->getMessage());
                $columnExists = false;
            }
        } else {
            throw $e;
        }
    }
    
    // Insert skill
    if ($columnExists) {
        $stmt = $pdo->prepare('INSERT INTO skills (user_id, title, description, category_id, image, credits_required, active_status, approval_status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, \'approved\', NOW())');
        $stmt->execute([$userId, $title, $description, $category_id, $imagePath, $credits]);
    } else {
        // Fallback: insert without credits_required
        $stmt = $pdo->prepare('INSERT INTO skills (user_id, title, description, category_id, image, active_status, approval_status, created_at) VALUES (?, ?, ?, ?, ?, 1, \'approved\', NOW())');
        $stmt->execute([$userId, $title, $description, $category_id, $imagePath]);
    }

    $skillId = (int)$pdo->lastInsertId();

    // Log to audit logs
    require_once __DIR__ . '/../helpers/audit.php';
    logAudit($pdo, $userId, 'skill_created', 'skill', $skillId, json_encode(['title' => $title, 'category_id' => $category_id]));

    echo json_encode([
        'ok' => true, 
        'skill_id' => $skillId,
        'message' => 'Skill created successfully and is now live!'
    ]);
} catch (PDOException $e) {
    ob_clean();
    error_log('Skill creation DB error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['ok' => false, 'error' => 'Database error occurred']);
    exit();
} catch (Exception $e) {
    ob_clean();
    error_log('Skill creation error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit();
}
ob_end_flush();
