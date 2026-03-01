<?php
// Start output buffering to prevent any output from breaking JSON
ob_start();

// Suppress error display to prevent HTML output in JSON response
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Set error handler to log errors instead of displaying
set_error_handler(function($severity, $message, $file, $line) {
    error_log("Profile Update Error [$severity]: $message in $file:$line");
    return true; // Don't execute PHP internal error handler
});

require_once __DIR__ . '/../init.php';

// Require login
try {
    $user_id = requireAuth();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit();
}

$response = ['ok' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [];
    $params = [];
    
    // Handle Text Fields
    $allowable_fields = ['name', 'bio', 'phone', 'location', 'title', 'website'];
    
    foreach ($allowable_fields as $field) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = sanitizeInput($_POST[$field]);
        }
    }

    // Handle File Upload (Avatar)
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $errors = validateFileUpload($_FILES['avatar'], $allowed, 5 * 1024 * 1024);
        
        if (empty($errors)) {
            $filename = $_FILES['avatar']['name'];
            $new_filename = generateSecureFilename($filename);
            $upload_dir = __DIR__ . '/../../uploads/avatars/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_filename)) {
                $updates[] = "profile_pic = ?";
                $params[] = 'uploads/avatars/' . $new_filename;
            } else {
                $response['error'] = 'Failed to save uploaded file.';
            }
        } else {
            $response['error'] = implode(' ', $errors);
        }
    }

    if (!empty($updates) && empty($response['error'])) {
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $user_id; // Add user_id for WHERE clause
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Fetch updated user to return (including all fields)
            $stmt = $pdo->prepare("SELECT id, name, email, credits, reputation_score, profile_pic, bio, phone, location, title, website, created_at, status, email_verified FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $updatedUser = $stmt->fetch();
            
            // Log profile update action (optional, don't fail if it doesn't work)
            try {
                if (file_exists(__DIR__ . '/../helpers/audit.php')) {
                    require_once __DIR__ . '/../helpers/audit.php';
                    if (function_exists('logAudit')) {
                        logAudit($pdo, $user_id, 'user_updated', 'user', $user_id, json_encode(['updates' => array_keys($_POST)]));
                    }
                }
            } catch (Exception $auditError) {
                // Don't fail the update if audit logging fails
                error_log("Audit log failed: " . $auditError->getMessage());
            }
            
            // Update session data
            $_SESSION['user_id'] = $updatedUser['id'];
            $_SESSION['name'] = $updatedUser['name'];
            
            $response['ok'] = true;
            $response['user'] = [
                'id' => (int)$updatedUser['id'],
                'name' => $updatedUser['name'],
                'email' => $updatedUser['email'],
                'credits' => (int)$updatedUser['credits'],
                'reputation_score' => (float)$updatedUser['reputation_score'],
                'profile_pic' => $updatedUser['profile_pic'] ?? 'assets/img/default-avatar.png',
                'bio' => $updatedUser['bio'],
                'phone' => $updatedUser['phone'],
                'location' => $updatedUser['location'],
                'title' => $updatedUser['title'],
                'website' => $updatedUser['website'],
                'created_at' => $updatedUser['created_at'],
                'status' => $updatedUser['status'],
                'email_verified' => (bool)$updatedUser['email_verified']
            ];
            $response['message'] = 'Profile updated successfully.';
        } catch (PDOException $e) {
            error_log("Profile update database error: " . $e->getMessage());
            $response['error'] = 'Database error occurred. Please try again.';
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $response['error'] = 'An error occurred while updating your profile.';
        }
    } elseif (empty($updates) && empty($response['error'])) {
         $response['error'] = 'No changes provided.';
    }

} else {
    $response['error'] = 'Invalid request method.';
}

// Clean any output that might have been generated
ob_end_clean();

// Set proper headers
header('Content-Type: application/json');

// Output JSON response
echo json_encode($response);
exit();
?>
