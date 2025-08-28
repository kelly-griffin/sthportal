<?php
declare(strict_types=1);

/**
 * Simple login rate limit: max 5 failures per 15 minutes for (IP, username) and for IP alone.
 * Ensure table exists:
 *   CREATE TABLE IF NOT EXISTS login_attempts (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     ip VARCHAR(45) DEFAULT NULL,
 *     username VARCHAR(128) DEFAULT NULL,
 *     attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     success TINYINT(1) NOT NULL DEFAULT 0
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
 */

const RL_WINDOW_SECONDS = 15 * 60;
const RL_MAX_FAILURES   = 5;

function rl_is_blocked(mysqli $db, ?string $ip, ?string $user): bool {
    $ip = $ip ?? '';
    $user = $user ?? '';
    $sql = "
        SELECT
          SUM(CASE WHEN success=0 THEN 1 ELSE 0 END) AS fails
        FROM login_attempts
        WHERE attempted_at >= (NOW() - INTERVAL ? SECOND)
          AND (ip = ? OR ? = '')
          AND (username = ? OR ? = '')
    ";
    if ($stmt = $db->prepare($sql)) {
        $w = RL_WINDOW_SECONDS;
        $stmt->bind_param('issss', $w, $ip, $ip, $user, $user);
        $stmt->execute();
        $stmt->bind_result($fails);
        $stmt->fetch();
        $stmt->close();
        return (int)$fails >= RL_MAX_FAILURES;
    }
    return false;
}

function rl_note_attempt(mysqli $db, ?string $ip, ?string $user, bool $success): void {
    $ip = $ip ?? null;
    $user = $user ?? null;
    $s = $success ? 1 : 0;
    $sql = "INSERT INTO login_attempts (ip, username, success) VALUES (?, ?, ?)";
    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param('ssi', $ip, $user, $s);
        $stmt->execute();
        $stmt->close();
    }
}