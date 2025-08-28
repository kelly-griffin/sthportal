<?php
// includes/sqlite.php
declare(strict_types=1);

/** Where the live snapshot symlink points */
function sths_sqlite_path(): string {
    return __DIR__ . '/../data/sqlite/current.sqlite';
}

/** Directory where we version snapshots */
function sths_sqlite_dir(): string {
    return __DIR__ . '/../data/sqlite';
}

/** Open READ-ONLY with safe pragmas */
function sths_sqlite_open(): SQLite3 {
    $path = sths_sqlite_path();
    if (!is_file($path)) {
        throw new RuntimeException("SQLite not found: " . $path);
    }
    $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
    $db->exec('PRAGMA query_only = ON;');
    $db->exec('PRAGMA journal_mode = OFF;');
    $db->exec('PRAGMA synchronous = OFF;');
    return $db;
}

/** Validate a SQLite file’s integrity */
function sths_sqlite_check_file(string $absPath): bool {
    $db = new SQLite3($absPath, SQLITE3_OPEN_READONLY);
    $res = $db->querySingle("PRAGMA integrity_check;", true);
    $ok  = isset($res['integrity_check']) ? ($res['integrity_check'] === 'ok') : false;
    $db->close();
    return $ok;
}

/** Atomically point current.sqlite at a versioned file, fallback to copy on systems without symlink */
function sths_sqlite_point_current(string $absPath): void {
    $dir = sths_sqlite_dir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $current = $dir . '/current.sqlite';
    @unlink($current);
    // try symlink first
    @symlink($absPath, $current);
    if (!is_file($current)) {
        // fallback to copy
        @copy($absPath, $current);
        @chmod($current, 0644);
    }
}
