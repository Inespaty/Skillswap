<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Function to handle file upload
function handleSkillImageUpload($file, $userId) {
    $uploadDir = __DIR__ . '/../../assets/uploads/skills/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds maximum limit of 2MB.');
    }
    
    // Generate unique filename
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = 'skill_' . $userId . '_' . time() . '.' . $fileExt;
    $targetPath = $uploadDir . $newFilename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to upload skill image.');
    }
    
    return 'assets/uploads/skills/' . $newFilename; // Return relative path
}

try {
    // Get user ID from session
    $userId = $_SESSION['user_id'];
    
    // Validate required fields
    $requiredFields = ['title', 'description', 'category'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required fields: ' . implode(', ', $missingFields)
        ]);
        exit;
    }
    
    // Handle image upload if present
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imagePath = handleSkillImageUpload($_FILES['image'], $userId);
    }
    
    // Prepare skill data
    $skillData = [
        'user_id' => $userId,
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'category' => $_POST['category'],
        'image' => $imagePath
    ];
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert skill
        $stmt = $pdo->prepare("
            INSERT INTO Skills (user_id, title, description, category, image)
            VALUES (:user_id, :title, :description, :category, :image)
        ");
        
        $stmt->execute($skillData);
        $skillId = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        // Get the created skill
        $stmt = $pdo->prepare("
            SELECT s.*, u.name as user_name, u.profile_pic as user_avatar
            FROM Skills s
            JOIN Users u ON s.user_id = u.id
            WHERE s.skill_id = ?
        ");
        $stmt->execute([$skillId]);
        $skill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add additional fields for consistency
        $skill['review_count'] = 0;
        $skill['avg_rating'] = 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Skill created successfully',
            'skill' => $skill
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error if active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create skill: ' . $e->getMessage()
    ]);
}