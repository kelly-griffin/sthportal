<?php
// includes/license.php
// Helpers for license CRUD + key generation + logging

if (!defined('STD_OUT')) {
    define('STD_OUT', true);
}

function generate_portal_id(): string {
    return 'PORTAL-' . strtoupper(bin2hex(random_bytes(4))); // e.g., PORTAL-8F2C9A11
}

function generate_license_key(): string {
    // e.g., STHS-8F2C-A11B-77D3
    return 'STHS-' .
        strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)) . '-' .
        strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)) . '-' .
        strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

function get_licenses(mysqli $db): array {
    $rows = [];
    $res = $db->query("SELECT * FROM licenses ORDER BY created_at DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $res->free();
    }
    return $rows;
}

function create_license(mysqli $db, string $licensed_to, ?string $email, string $status = 'active'): array {
    $portal_id   = generate_portal_id();
    $license_key = generate_license_key();

    $stmt = $db->prepare("INSERT INTO licenses (portal_id, license_key, licensed_to, email, status) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss", $portal_id, $license_key, $licensed_to, $email, $status);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return [
        'id'          => $id,
        'portal_id'   => $portal_id,
        'license_key' => $license_key,
        'licensed_to' => $licensed_to,
        'email'       => $email,
        'status'      => $status,
    ];
}

function set_license_status(mysqli $db, int $id, string $status): bool {
    $allowed = ['active','blocked','demo'];
    if (!in_array($status, $allowed, true)) return false;
    $stmt = $db->prepare("UPDATE licenses SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function delete_license(mysqli $db, int $id): bool {
    $stmt = $db->prepare("DELETE FROM licenses WHERE id=?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function find_license_by_key(mysqli $db, string $license_key): ?array {
    $stmt = $db->prepare("SELECT * FROM licenses WHERE license_key=? LIMIT 1");
    $stmt->bind_param("s", $license_key);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

/**
 * Write a simple line to /logs/license_checks.log
 * Use this from portal-side checks, e.g. after validating a key.
 */
function log_license_check(string $portal_id, string $status, string $note = ''): void {
    $logFile = __DIR__ . '/../logs/license_checks.log';
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $ua      = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $line    = sprintf(
        "[%s]\tportal=%s\tstatus=%s\tip=%s\tua=%s\tnote=%s\n",
        date('Y-m-d H:i:s'),
        $portal_id,
        $status,
        $ip,
        $ua,
        str_replace(["\r","\n"], ' ', $note)
    );
    // Best-effort; ignore failures on local dev
    @file_put_contents($logFile, $line, FILE_APPEND);
}
