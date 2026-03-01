<?php
// Suppress error display to prevent HTML output before JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../init.php';

// Set JSON header explicitly
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit();
}

$userId = requireAuth();

try {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload failed');
    }

    $file = $_FILES['avatar'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Get MIME type - try multiple methods
    $mimeType = null;
    if (function_exists('finfo_open') && function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        // Fallback to file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WEBP');
        }
        // Map extension to MIME type
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $mimeType = $mimeMap[$ext] ?? $file['type'];
    }

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WEBP');
    }

    // Validate size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size exceeds 5MB limit');
    }

    // Generate safe filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
    
    // Create upload directory if not exists
    $uploadDir = __DIR__ . '/../../uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Move file
    $targetPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Update database
    $relativePath = 'uploads/avatars/' . $filename;
    $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
    $stmt->execute([$relativePath, $userId]);

    // Log action (optional - don't fail if audit doesn't exist)
    if (file_exists(__DIR__ . '/../helpers/audit.php')) {
        try {
            require_once __DIR__ . '/../helpers/audit.php';
            if (function_exists('logAudit')) {
                logAudit($pdo, $userId, 'avatar_updated', 'user', $userId, json_encode(['file' => $relativePath]));
            }
        } catch (Exception $e) {
            // Ignore audit log errors
        }
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Avatar updated successfully',
        'profile_pic' => $relativePath,
        'url' => $relativePath
    ]);

} catch (Exception $e) {
    http_response_code(400);
    // Log error but return clean JSON
    error_log('Avatar upload error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit();
} catch (Error $e) {
    http_response_code(500);
    error_log('Avatar upload fatal error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'An unexpected error occurred']);
    exit();
}
?>
