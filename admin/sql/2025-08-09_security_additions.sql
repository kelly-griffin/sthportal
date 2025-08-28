-- Create tables if they don't exist
CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip VARCHAR(45) DEFAULT NULL,
  admin_user VARCHAR(128) DEFAULT NULL,
  action VARCHAR(64) NOT NULL,
  details JSON DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) DEFAULT NULL,
  username VARCHAR(128) DEFAULT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_attempt_time (attempted_at),
  KEY idx_ip (ip),
  KEY idx_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;