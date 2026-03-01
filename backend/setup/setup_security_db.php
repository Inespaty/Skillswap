<?php
require_once __DIR__ . '/../db.php';

try {
    echo "Creating rate_limits table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS `rate_limits` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `ip_address` VARCHAR(45) NOT NULL,
        `endpoint_hash` VARCHAR(32) NOT NULL,
        `request_count` INT(11) DEFAULT 1,
        `reset_time` DATETIME NOT NULL,
        INDEX `idx_hash_ip` (`endpoint_hash`, `ip_address`),
        INDEX `idx_reset_time` (`reset_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table 'rate_limits' created successfully.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
?>
