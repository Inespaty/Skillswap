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
function handleFileUpload($file, $userId) {
    $uploadDir = __DIR__ . '/../../assets/uploads/profiles/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds maximum limit of 5MB.');
    }
    
    // Generate unique filename
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = 'profile_' . $userId . '_' . time() . '.' . $fileExt;
    $targetPath = $uploadDir . $newFilename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to upload file.');
    }
    
    return 'assets/uploads/profiles/' . $newFilename; // Return relative path
}

try {
    // Get user ID from session
    $userId = $_SESSION['user_id'];
    $updates = [];
    $params = [];
    
    // Handle profile picture upload if present
    $profilePic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $profilePic = handleFileUpload($_FILES['profile_pic'], $userId);
        $updates[] = 'profile_pic = ?';
        $params[] = $profilePic;
    }
    
    // Handle other profile fields
    $allowedFields = ['name', 'bio', 'phone', 'department', 'year_of_study'];
    
    foreach ($allowedFields as $field) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = $_POST[$field];
        }
    }
    
    // If no updates were provided
    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'No updates provided']);
        exit;
    }
    
    // Add user_id to params for WHERE clause
    $params[] = $userId;
    
    // Build and execute the update query
    $sql = "UPDATE Users SET " . implode(', ', $updates) . " WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Get updated user data
    $stmt = $pdo->prepare("SELECT user_id, name, email, profile_pic, bio, phone, department, year_of_study FROM Users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => $user
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
