<?php
declare(strict_types=1);

/**
 * Minimal audit logger.
 * Ensure table exists:
 *   CREATE TABLE IF NOT EXISTS audit_log (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     ip VARCHAR(45) DEFAULT NULL,
 *     admin_user VARCHAR(128) DEFAULT NULL,
 *     action VARCHAR(64) NOT NULL,
 *     details JSON DEFAULT NULL
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
 */
function audit(mysqli $db, string $action, array $details = []): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $admin = isset($_SESSION['is_admin']) && !empty($_SESSION['is_admin']) 
             ? ($_SESSION['admin_name'] ?? 'admin') 
             : null;
    $j = $details ? json_encode($details, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;

    $sql = "INSERT INTO audit_log (ip, admin_user, action, details) VALUES (?, ?, ?, ?)";
    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param('ssss', $ip, $admin, $action, $j);
        $stmt->execute();
        $stmt->close();
    }
}