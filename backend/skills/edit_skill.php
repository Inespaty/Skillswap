<?php
require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Reuse the handleSkillImageUpload function from create_skill.php
function handleSkillImageUpload($file, $userId) {
    $uploadDir = __DIR__ . '/../../assets/uploads/skills/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds maximum limit of 2MB.');
    }
    
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = 'skill_' . $userId . '_' . time() . '.' . $fileExt;
    $targetPath = $uploadDir . $newFilename;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to upload skill image.');
    }
    
    return 'assets/uploads/skills/' . $newFilename;
}

try {
    // Get skill ID from request
    $skillId = isset($_POST['skill_id']) ? (int)$_POST['skill_id'] : 0;
    $userId = $_SESSION['user_id'];
    
    if ($skillId <= 0) {
        throw new Exception('Invalid skill ID');
    }

    // Check if skill exists and user owns it
    $stmt = $pdo->prepare("SELECT * FROM Skills WHERE skill_id = ? AND user_id = ?");
    $stmt->execute([$skillId, $userId]);
    $existingSkill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingSkill) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Skill not found or access denied']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();
    
    try {
        $updates = [];
        $params = [];
        
        // Handle title update
        if (isset($_POST['title'])) {
            $title = trim($_POST['title']);
            if (empty($title)) {
                throw new Exception('Title cannot be empty');
            }
            $updates[] = 'title = ?';
            $params[] = $title;
        }
        
        // Handle description update
        if (isset($_POST['description'])) {
            $description = trim($_POST['description']);
            $updates[] = 'description = ?';
            $params[] = $description;
        }
        
        // Handle category update
        if (isset($_POST['category'])) {
            $category = trim($_POST['category']);
            if (empty($category)) {
                throw new Exception('Category cannot be empty');
            }
            $updates[] = 'category = ?';
            $params[] = $category;
        }
        
        // Handle image upload if present
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = handleSkillImageUpload($_FILES['image'], $userId);
            $updates[] = 'image = ?';
            $params[] = $imagePath;
            
            // Delete old image if it exists and is not the default
            if (!empty($existingSkill['image']) && strpos($existingSkill['image'], 'default-') === false) {
                $oldImagePath = __DIR__ . '/../../' . $existingSkill['image'];
                if (file_exists($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            }
        }
        
        // If no updates were provided
        if (empty($updates)) {
            throw new Exception('No updates provided');
        }
        
        // Add skill_id to params for WHERE clause
        $params[] = $skillId;
        
        // Build and execute the update query
        $sql = "UPDATE Skills SET " . implode(', ', $updates) . " WHERE skill_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Get the updated skill
        $stmt = $pdo->prepare("
            SELECT s.*, u.name as user_name, u.profile_pic as user_avatar,
                   (SELECT COUNT(*) FROM Reviews r 
                    JOIN Requests req ON r.request_id = req.request_id 
                    WHERE req.skill_id = s.skill_id) as review_count,
                   (SELECT COALESCE(AVG(rating), 0) FROM Reviews r 
                    JOIN Requests req ON r.request_id = req.request_id 
                    WHERE req.skill_id = s.skill_id) as avg_rating
            FROM Skills s
            JOIN Users u ON s.user_id = u.user_id
            WHERE s.skill_id = ?
        ");
        $stmt->execute([$skillId]);
        $updatedSkill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Skill updated successfully',
            'skill' => $updatedSkill
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update skill: ' . $e->getMessage()
    ]);
}