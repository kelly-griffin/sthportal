<?php
// admin/users-export.php — export Users as CSV with current filters + sort
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_perm('manage_users');

// DB
$dbc = null;
if (isset($db) && $db instanceof mysqli) { $dbc = $db; }
elseif (function_exists('get_db')) { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('DB not initialized'); }

// filters
$q      = trim((string)($_GET['q'] ?? ''));
$status = strtolower(trim((string)($_GET['status'] ?? '')));
$role   = trim((string)($_GET['role'] ?? ''));

// sort
$allowedSorts = [
  'id'=>'id','name'=>'name','email'=>'email','role'=>'role',
  'active'=>'active','locked'=>'locked_until','created'=>'created_at','updated'=>'updated_at'
];
$sort = strtolower(trim((string)($_GET['sort'] ?? 'id')));
$sortCol = $allowedSorts[$sort] ?? 'id';
$dir = strtolower(trim((string)($_GET['dir'] ?? 'desc')));
$dir = ($dir === 'asc') ? 'ASC' : 'DESC';

// WHERE
$where=[]; $types=''; $args=[];
if ($q!==''){ $kw='%'.$q.'%'; $where[]='(name LIKE ? OR email LIKE ?)'; $types.='ss'; $args[]=$kw; $args[]=$kw; }
if ($role!==''){ $where[]='role=?'; $types.='s'; $args[]=$role; }
if ($status==='active'){ $where[]='active=1 AND (locked_until IS NULL OR locked_until <= NOW())'; }
elseif ($status==='inactive'){ $where[]='active=0'; }
elseif ($status==='locked'){ $where[]='locked_until IS NOT NULL AND locked_until > NOW()'; }
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// query (no LIMIT)
$sql = "SELECT id,name,email,role,active,locked_until,created_at,updated_at
          FROM users {$whereSql}
         ORDER BY {$sortCol} {$dir}";

$stmt = $dbc->prepare($sql);
if ($where) $stmt->bind_param($types, ...$args);
$stmt->execute(); $res = $stmt->get_result();

// headers
$filename = 'users-'.date('Ymd-His').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "\xEF\xBB\xBF"; // BOM

$out = fopen('php://output','w');
fputcsv($out, ['id','name','email','role','active','locked_until','created_at','updated_at']);
while ($row = $res->fetch_assoc()) {
  fputcsv($out, [
    (int)$row['id'],
    (string)$row['name'],
    (string)$row['email'],
    (string)$row['role'],
    (int)$row['active'],
    (string)$row['locked_until'],
    (string)$row['created_at'],
    (string)$row['updated_at'],
  ]);
}
fclose($out);
$stmt->close();
exit;
