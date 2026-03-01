<?php
/**
 * Audit Logging Helper Functions
 * Provides centralized logging for all system actions
 */

/**
 * Log an audit event
 * @param PDO $pdo Database connection
 * @param int|null $userId User performing the action (null for system actions)
 * @param string $action Action being performed
 * @param string|null $entityType Type of entity (user, skill, request, etc.)
 * @param int|null $entityId ID of the entity
 * @param mixed $details Additional details (will be JSON encoded if array/object)
 * @return bool Success status
 */
function logAudit($pdo, $userId, $action, $entityType = null, $entityId = null, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Convert details to JSON if it's an array or object
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$userId, $action, $entityType, $entityId, $details, $ip]);
    } catch (PDOException $e) {
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs with filtering and pagination
 * @param PDO $pdo Database connection
 * @param array $filters Filters (user_id, action, entity_type, date_from, date_to)
 * @param int $limit Number of records to return
 * @param int $offset Offset for pagination
 * @return array Audit logs
 */
function getAuditLogs($pdo, $filters = [], $limit = 50, $offset = 0) {
    try {
        $where = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = "entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
            SELECT 
                al.*,
                u.name as user_name,
                u.email as user_email
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $whereClause
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get audit logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Count total audit logs matching filters
 * @param PDO $pdo Database connection
 * @param array $filters Filters (user_id, action, entity_type, date_from, date_to)
 * @return int Total count
 */
function countAuditLogs($pdo, $filters = []) {
    try {
        $where = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = "entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "SELECT COUNT(*) as total FROM audit_logs $whereClause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return (int)$result['total'];
    } catch (PDOException $e) {
        error_log("Failed to count audit logs: " . $e->getMessage());
        return 0;
    }
}

// Common audit action constants
define('AUDIT_USER_REGISTERED', 'user_registered');
define('AUDIT_USER_LOGIN', 'user_login');
define('AUDIT_USER_LOGOUT', 'user_logout');
define('AUDIT_USER_UPDATED', 'user_updated');
define('AUDIT_USER_SUSPENDED', 'user_suspended');
define('AUDIT_USER_ACTIVATED', 'user_activated');
define('AUDIT_USER_DELETED', 'user_deleted');

define('AUDIT_SKILL_CREATED', 'skill_created');
define('AUDIT_SKILL_UPDATED', 'skill_updated');
define('AUDIT_SKILL_DELETED', 'skill_deleted');
define('AUDIT_SKILL_APPROVED', 'skill_approved');
define('AUDIT_SKILL_REJECTED', 'skill_rejected');

define('AUDIT_REQUEST_CREATED', 'request_created');
define('AUDIT_REQUEST_ACCEPTED', 'request_accepted');
define('AUDIT_REQUEST_REJECTED', 'request_rejected');
define('AUDIT_REQUEST_COMPLETED', 'request_completed');
define('AUDIT_REQUEST_CANCELLED', 'request_cancelled');

define('AUDIT_CREDIT_TRANSFER', 'credit_transfer');
define('AUDIT_REVIEW_POSTED', 'review_posted');
define('AUDIT_MESSAGE_SENT', 'message_sent');
?>
