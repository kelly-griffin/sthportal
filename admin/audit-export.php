<?php
// admin/audit-export.php — export CSV (matches current filters + sort)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// DB
$dbc = null;
if (isset($db) && $db instanceof mysqli) { $dbc = $db; }
elseif (function_exists('get_db')) { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('DB not initialized'); }

// Filters
$q         = trim((string)($_GET['q'] ?? ''));
$event     = trim((string)($_GET['event'] ?? ''));
$actor     = trim((string)($_GET['actor'] ?? ''));
$ip        = trim((string)($_GET['ip'] ?? ''));
$date_from = trim((string)($_GET['from'] ?? ''));
$date_to   = trim((string)($_GET['to'] ?? ''));

// Sort
$allowedSorts = ['time'=>'created_at','event'=>'event','actor'=>'actor','ip'=>'ip'];
$sort = strtolower(trim((string)($_GET['sort'] ?? 'time')));
$sortCol = $allowedSorts[$sort] ?? 'created_at';
$dir = strtolower(trim((string)($_GET['dir'] ?? 'desc')));
$dir = ($dir === 'asc') ? 'ASC' : 'DESC';

// WHERE
$where=[]; $types=''; $args=[];
if ($q!==''){ $kw='%'.$q.'%'; $where[]="(event LIKE ? OR details LIKE ? OR actor LIKE ? OR ip LIKE ?)"; $types.='ssss'; $args[]=$kw;$args[]=$kw;$args[]=$kw;$args[]=$kw; }
if ($event!==''){ $where[]="event=?"; $types.='s'; $args[]=$event; }
if ($actor!==''){ $where[]="actor=?"; $types.='s'; $args[]=$actor; }
if ($ip!==''){ $where[]="ip=?"; $types.='s'; $args[]=$ip; }
if ($date_from!==''){ $where[]="created_at >= ?"; $types.='s'; $args[]=$date_from." 00:00:00"; }
if ($date_to!==''){ $where[]="created_at <= ?"; $types.='s'; $args[]=$date_to." 23:59:59"; }
$whereSql = $where ? (' WHERE '.implode(' AND ',$where)) : '';

$sql = "SELECT id,event,details,actor,ip,created_at FROM audit_log $whereSql ORDER BY $sortCol $dir";
$stmt = $dbc->prepare($sql);
if ($where) $stmt->bind_param($types, ...$args);
$stmt->execute(); $res = $stmt->get_result();

// Output
$filename = 'audit-'.date('Ymd-His').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output','w');
fputcsv($out, ['id','event','details','actor','ip','created_at']);
while($row = $res->fetch_assoc()){
  fputcsv($out, [(int)$row['id'],(string)$row['event'],(string)$row['details'],(string)$row['actor'],(string)$row['ip'],(string)$row['created_at']]);
}
fclose($out);
$stmt->close();
exit;
