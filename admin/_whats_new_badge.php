<?php
// admin/_whats_new_badge.php — small helper to link to latest devlog entry
declare(strict_types=1);

// Get DB handle safely
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { return; }
$dbc->set_charset('utf8mb4');

// Fetch latest entry; works for both legacy and new devlog schemas
$sql = "SELECT id, title, created_at FROM devlog ORDER BY created_at DESC, id DESC LIMIT 1";
if (!($res = $dbc->query($sql))) { return; }
$row = $res->fetch_assoc();
if (!$row) { return; }

$latestId = (int)$row['id'];
$title    = (string)($row['title'] ?? 'Devlog');
$link = (function_exists('has_perm') && has_perm('manage_devlog')) ? "devlog-edit.php?id={$latestId}" : "devlog.php";
?>
<div style="margin:10px 0 14px">
  <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" style="
    display:inline-flex;align-items:center;gap:.5rem;
    padding:.25rem .6rem;border:1px solid #ddd;border-radius:999px;
    text-decoration:none;">
    <strong>What’s New</strong> → <?= htmlspecialchars($title) ?>
  </a>
</div>
