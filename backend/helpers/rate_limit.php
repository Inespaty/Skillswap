<?php
/**
 * Rate Limiting Helper
 */

/**
 * Check if request is within rate limits
 * @param PDO $pdo Database connection
 * @param string $ip User IP address
 * @param string $endpoint Endpoint identifier (or 'global')
 * @param int $limit Max requests
 * @param int $windowTimeWindow in seconds
 * @return bool True if allowed, False if limit exceeded
 */
function checkRateLimit($pdo, $ip, $endpoint, $limit, $window) {
    // Clean up old records (randomly, to avoid performance hit on every req)
    if (rand(1, 100) === 1) {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE reset_time < NOW()");
        $stmt->execute();
    }

    $hash = md5($ip . ':' . $endpoint);
    $now = date('Y-m-d H:i:s');

    // Check existing record
    $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE endpoint_hash = ? AND ip_address = ?");
    $stmt->execute([$hash, $ip]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        if (strtotime($record['reset_time']) < time()) {
            // Window expired, reset
            $resetTime = date('Y-m-d H:i:s', time() + $window);
            $update = $pdo->prepare("UPDATE rate_limits SET request_count = 1, reset_time = ? WHERE id = ?");
            $update->execute([$resetTime, $record['id']]);
            return true;
        } else {
            // Within window
            if ($record['request_count'] >= $limit) {
                return false;
            } else {
                // Increment
                $update = $pdo->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE id = ?");
                $update->execute([$record['id']]);
                return true;
            }
        }
    } else {
        // New record
        $resetTime = date('Y-m-d H:i:s', time() + $window);
        $insert = $pdo->prepare("INSERT INTO rate_limits (ip_address, endpoint_hash, request_count, reset_time) VALUES (?, ?, 1, ?)");
        $insert->execute([$ip, $hash, $resetTime]);
        return true;
    }
}
?>
