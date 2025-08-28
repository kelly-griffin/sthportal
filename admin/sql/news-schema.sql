-- news-schema.sql — tables for portal stories/news
CREATE TABLE IF NOT EXISTS stories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(180) DEFAULT NULL UNIQUE,
  summary VARCHAR(300) NOT NULL,
  body MEDIUMTEXT NULL,
  hero_image_url VARCHAR(255) DEFAULT NULL,
  team_id INT DEFAULT NULL,
  author_id INT DEFAULT NULL,
  is_auto TINYINT(1) NOT NULL DEFAULT 0,
  source_type ENUM('manual','game','transaction') NOT NULL DEFAULT 'manual',
  source_id INT DEFAULT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL,
  INDEX (published_at),
  INDEX (team_id),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
