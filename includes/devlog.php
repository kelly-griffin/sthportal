<?php
// includes/devlog.php — tiny devlog helper
require_once __DIR__ . '/log.php'; // __log_db(), log_audit(), __log_ip()

function devlog_ensure(mysqli $db = null): mysqli {
    $db = $db instanceof mysqli ? $db : __log_db();
    $db->query("CREATE TABLE IF NOT EXISTS devlog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tag VARCHAR(32) NOT NULL DEFAULT 'note',
        title VARCHAR(190) NULL,
        body TEXT NOT NULL,
        actor VARCHAR(190) NULL,
        ip VARCHAR(64) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_devlog_created_at (created_at),
        INDEX idx_devlog_tag (tag)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    return $db;
}

/**
 * Add a devlog entry.
 * @return int Inserted row id
 */
function devlog_add(string $body, string $tag = 'note', string $title = '', string $actor = '', ?string $ip = null): int {
    $db = devlog_ensure();
    $actor = (string)$actor;
    $ip = (string)($ip ?? __log_ip());
    $tag = substr(trim($tag), 0, 32) ?: 'note';
    $title = trim($title);

    if ($stmt = $db->prepare("INSERT INTO devlog (tag, title, body, actor, ip) VALUES (?,?,?,?,?)")) {
        $stmt->bind_param('sssss', $tag, $title, $body, $actor, $ip);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
    } else {
        return 0;
    }

    // Also audit it (lightweight)
    log_audit('devlog_add', ['id'=>$id,'tag'=>$tag,'title'=>$title], $actor, $ip);
    return $id;
}
