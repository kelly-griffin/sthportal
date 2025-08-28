<?php
// admin/licenses-export.php — export Licenses as CSV using current filters + sort
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_perm('manage_licenses');

// DB handle
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// ---- read filters
$q       = trim((string)($_GET['q'] ?? ''));
$status  = strtolower(trim((string)($_GET['status'] ?? '')));
$type    = strtolower(trim((string)($_GET['type'] ?? '')));

$allowedStatus = ['','issued','active','revoked','expired'];
$allowedType   = ['','full','demo'];
if (!in_array($status, $allowedStatus, true)) { $status = ''; }
if (!in_array($type,   $allowedType,   true)) { $type   = ''; }

// ---- read sort
$allowedSorts = [
  'id' => 'id',
  'key' => 'license_key',
  'status' => 'status',
  'type' => 'type',
  'issued_to' => 'issued_to',
  'domain' => 'site_domain',
  'expires' => 'expires_at',
  'activated' => 'activated_at',
  'created' => 'created_at',
  'updated' => 'updated_at',
];
$sort = strtolower(trim((string)($_GET['sort'] ?? 'id')));
$sortCol = $allowedSorts[$sort] ?? 'id';
$dir = strtolower(trim((string)($_GET['dir'] ?? 'desc')));
$dir = ($dir === 'asc') ? 'ASC' : 'DESC';

// ---- WHERE builder
$where = [];
$typesBind = '';
$args  = [];

if ($status !== '') { $where[] = 'status = ?'; $typesBind .= 's'; $args[] = $status; }
if ($type   !== '') { $where[] = 'type = ?';   $typesBind .= 's'; $args[] = $type;   }
if ($q !== '') {
    $where[] = '(license_key LIKE ? OR issued_to LIKE ? OR site_domain LIKE ?)';
    $kw = '%' . $q . '%';
    $typesBind .= 'sss';
    $args[] = $kw; $args[] = $kw; $args[] = $kw;
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// ---- query (NO LIMIT — export all matches)
$sql = "SELECT id, license_key, status, type, issued_to, site_domain,
               expires_at, activated_at, created_by, created_at, updated_at
          FROM licenses $whereSql
         ORDER BY $sortCol $dir";

$stmt = $dbc->prepare($sql);
if ($where) { $stmt->bind_param($typesBind, ...$args); }
$stmt->execute();
$res = $stmt->get_result();

// ---- headers
$filename = 'licenses-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

// (Excel-friendly) BOM
echo "\xEF\xBB\xBF";

// ---- stream CSV
$out = fopen('php://output', 'w');
fputcsv($out, ['id','license_key','status','type','issued_to','site_domain','expires_at','activated_at','created_by','created_at','updated_at']);

if ($res) {
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [
            (int)$r['id'],
            (string)$r['license_key'],
            (string)$r['status'],
            (string)$r['type'],
            (string)$r['issued_to'],
            (string)$r['site_domain'],
            (string)$r['expires_at'],
            (string)$r['activated_at'],
            (string)$r['created_by'],
            (string)$r['created_at'],
            (string)$r['updated_at'],
        ]);
    }
}
fclose($out);
$stmt->close();
exit;
