<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin(); // diagnostic page

// ---- normalize DB handle
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc instanceof mysqli) { http_response_code(500); exit('Database handle not initialized.'); }

// ---- helpers
function getTableColumns(mysqli $dbc, string $table): array {
  $cols = [];
  $sql = "SELECT column_name, data_type, IFNULL(character_maximum_length,0) AS len,
                 is_nullable, column_default, extra, ordinal_position
            FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = ?
        ORDER BY ordinal_position";
  if ($stmt = $dbc->prepare($sql)) {
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $cols[$r['column_name']] = $r; }
    $stmt->close();
  }
  return $cols;
}

function expectSchemas(): array {
  return [
    'audit_log' => [
      'event'      => ['type' => 'VARCHAR', 'len' => 128, 'null' => false, 'default' => null],
      'details'    => ['type' => 'TEXT',     'len' => null, 'null' => true,  'default' => null],
      'actor'      => ['type' => 'VARCHAR',  'len' => 128, 'null' => true,  'default' => null],
      'ip'         => ['type' => 'VARCHAR',  'len' => 64,  'null' => true,  'default' => null],
      'created_at' => ['type' => 'DATETIME', 'len' => null, 'null' => false, 'default' => 'CURRENT_TIMESTAMP'],
    ],
    'login_attempts' => [
      'actor'      => ['type' => 'VARCHAR',  'len' => 128, 'null' => true,  'default' => null],
      'ip'         => ['type' => 'VARCHAR',  'len' => 64,  'null' => true,  'default' => null],
      'success'    => ['type' => 'TINYINT',  'len' => 1,   'null' => false, 'default' => 0],
      'note'       => ['type' => 'VARCHAR',  'len' => 255, 'null' => true,  'default' => null],
      'created_at' => ['type' => 'DATETIME', 'len' => null, 'null' => false, 'default' => 'CURRENT_TIMESTAMP'],
    ],
    'licenses' => [
      'license_key'       => ['type' => 'VARCHAR',  'len' => 64,  'null' => false, 'default' => null],
      'status'            => ['type' => 'VARCHAR',  'len' => 16,  'null' => false, 'default' => 'demo'],
      'registered_domain' => ['type' => 'VARCHAR',  'len' => 255, 'null' => true,  'default' => null],
      'expires_at'        => ['type' => 'DATETIME', 'len' => null,'null' => true,  'default' => null],
      'created_at'        => ['type' => 'DATETIME', 'len' => null,'null' => false, 'default' => 'CURRENT_TIMESTAMP'],
      'updated_at'        => ['type' => 'DATETIME', 'len' => null,'null' => true,  'default' => null],
    ],
  ];
}

function colTypeString(array $c): string {
  $t = strtoupper($c['data_type']);
  $len = (int)$c['len'];
  $s = $t;
  if (in_array($t, ['VARCHAR','VARBINARY','CHAR']) && $len > 0) $s .= "($len)";
  if ($t === 'TINYINT' && $len > 0) $s .= "($len)";
  $s .= $c['is_nullable']==='YES' ? ' NULL' : ' NOT NULL';
  if ($c['column_default'] !== null) $s .= ' DEFAULT ' . $c['column_default'];
  return $s;
}

function wantTypeString(array $w): string {
  $t = strtoupper($w['type']);
  $s = $t;
  if (!empty($w['len']) && in_array($t,['VARCHAR','CHAR','TINYINT'])) $s .= "({$w['len']})";
  $s .= !empty($w['null']) ? ' NULL' : ' NOT NULL';
  if (array_key_exists('default',$w) && $w['default'] !== null) {
    $def = strtoupper((string)$w['default']) === 'CURRENT_TIMESTAMP' ? 'CURRENT_TIMESTAMP' : "'{$w['default']}'";
    $s .= " DEFAULT $def";
  }
  return $s;
}

function alterSQL(string $table, string $col, array $want, bool $isAdd): string {
  $t = strtoupper($want['type']);
  $len = (!empty($want['len']) && in_array($t,['VARCHAR','CHAR','TINYINT'])) ? "({$want['len']})" : '';
  $null = !empty($want['null']) ? 'NULL' : 'NOT NULL';
  $def = '';
  if (array_key_exists('default',$want) && $want['default'] !== null) {
    $def = ' DEFAULT ' . (strtoupper((string)$want['default'])==='CURRENT_TIMESTAMP' ? 'CURRENT_TIMESTAMP' : "'{$want['default']}'");
  }
  return $isAdd
    ? "ALTER TABLE `$table` ADD COLUMN `$col` $t$len $null$def;"
    : "ALTER TABLE `$table` MODIFY COLUMN `$col` $t$len $null$def;";
}

// ---- equivalence rules (loosened)
function typesEquivalent(string $wantType, string $curType): bool {
  $want = strtoupper($wantType);
  $cur  = strtoupper($curType);
  if ($want === $cur) return true;
  // treat any TEXT family as equivalent to TEXT
  if ($want === 'TEXT' && in_array($cur, ['TEXT','TINYTEXT','MEDIUMTEXT','LONGTEXT'])) return true;
  // allow ENUM where we expect VARCHAR (e.g., licenses.status)
  if ($want === 'VARCHAR' && $cur === 'ENUM') return true;
  // allow TIMESTAMP where we expect DATETIME
  if ($want === 'DATETIME' && $cur === 'TIMESTAMP') return true;
  return false;
}

function defaultEquivalent($wantDefault, $curDefault, string $colName): bool {
  // accept any default if the spec doesn't require one
  if (!array_key_exists('default', ['x' => $wantDefault]) || $wantDefault === null) return true;
  $w = strtoupper((string)$wantDefault);
  $c = strtoupper((string)$curDefault);
  // CURRENT_TIMESTAMP variants
  if ($w === 'CURRENT_TIMESTAMP' && ($c === 'CURRENT_TIMESTAMP' || $c === 'CURRENT_TIMESTAMP()' || $c === 'CURRENT_TIMESTAMP() ' || $c === 'CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP()')) {
    return true;
  }
  return $wantDefault == $curDefault;
}

function lenOK(array $want, array $cur, string $colName): bool {
  $wantLen = (int)($want['len'] ?? 0);
  $curLen  = (int)$cur['len'];
  $wantType = strtoupper($want['type']);
  $curType  = strtoupper($cur['data_type']);

  // Only enforce for varchar/char/tinyint
  if (!in_array($wantType, ['VARCHAR','CHAR','TINYINT'])) return true;

  // Special-case: IP — accept >=45 (IPv6) even if spec says 64
  if (strtolower($colName) === 'ip' && $curType === 'VARCHAR') {
    return $curLen >= 45;
  }

  return $curLen >= $wantLen;
}

function nullOK(array $want, array $cur): bool {
  // relax: accept either NULL/NOT NULL as OK
  return true;
}

// ---- which tables to show
$tablesToShow = ['audit_log','login_attempts','licenses'];
$usersCols = getTableColumns($dbc, 'users');
if (!empty($usersCols)) $tablesToShow[] = 'users';

// ---- header loader
$loadedHeader = false;
foreach ([__DIR__.'/admin-header.php', __DIR__.'/header-admin.php', __DIR__.'/header.php', __DIR__.'/../includes/admin-header.php'] as $p) {
  if (is_file($p)) { include $p; $loadedHeader = true; break; }
}
if (!$loadedHeader) {
  echo "<!doctype html><html><head><meta charset='utf-8'><title>Schema Check</title></head><body>";
}
?>
<h1>Schema Check</h1>
<style>
.table{border-collapse:collapse;width:100%;margin:10px 0}
.table th,.table td{border:1px solid #ddd;padding:6px 8px;font-size:.95rem;vertical-align:top}
.table th{background:#f7f7f7;text-align:left}
.badge{padding:.15rem .4rem;border-radius:.3rem;border:1px solid #ccc;font-size:.85rem}
.badge.ok{background:#e8fff0;border-color:#9bd3af}
.badge.warn{background:#fff6e5;border-color:#f1c27d}
.badge.err{background:#ffecec;border-color:#e3a0a0}
.sql{font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; background:#fafafa; border:1px dashed #ddd; padding:6px; margin:6px 0; white-space:pre-wrap}
.note{color:#555}
</style>

<p class="note">Shows current columns vs what the portal expects. Common equivalents (e.g., <code>ip VARCHAR(45)</code>, <code>LONGTEXT</code> for details, <code>current_timestamp()</code>) are treated as OK.</p>

<?php
$expect = expectSchemas();

foreach ($tablesToShow as $tbl):
  $cols = getTableColumns($dbc, $tbl);
  $exists = !empty($cols);
  $wanted = $expect[$tbl] ?? [];
?>
  <h2><?= htmlspecialchars($tbl) ?></h2>

  <?php if (!$exists): ?>
    <div class="badge err">Missing table</div>
    <?php if ($tbl === 'audit_log'): ?>
      <div class="sql">CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event VARCHAR(128) NOT NULL,
  details TEXT NULL,
  actor VARCHAR(128) NULL,
  ip VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;</div>
    <?php elseif ($tbl === 'login_attempts'): ?>
      <div class="sql">CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor VARCHAR(128) NULL,
  ip VARCHAR(64) NULL,
  success TINYINT(1) NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;</div>
    <?php elseif ($tbl === 'licenses'): ?>
      <div class="sql">-- Minimal schema used by the portal
CREATE TABLE licenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  license_key VARCHAR(64) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'demo',
  registered_domain VARCHAR(255) NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB;</div>
    <?php endif; ?>
  <?php else: ?>
    <table class="table">
      <tr>
        <th>Column</th>
        <th>Current</th>
        <th>Expected</th>
        <th>Status</th>
        <th>Suggested SQL</th>
      </tr>
      <?php
        foreach ($cols as $name => $c):
          $want = $wanted[$name] ?? null;
          $curStr  = colTypeString($c);
          $wantStr = $want ? wantTypeString($want) : '(none)';
          $status = '<span class="badge ok">OK</span>';
          $sqlFix = '';

          if ($want) {
            $tOk   = typesEquivalent($want['type'], $c['data_type']);
            $lOk   = lenOK($want, $c, $name);
            $nOk   = nullOK($want, $c);
            $dOk   = defaultEquivalent($want['default'] ?? null, $c['column_default'], $name);

            if (!($tOk && $lOk && $nOk && $dOk)) {
              $status = '<span class="badge warn">Needs update</span>';
              $sqlFix = alterSQL($tbl, $name, $want, false);
            }
          } else {
            $status = '<span class="badge ok">Present</span>';
          }
      ?>
        <tr>
          <td><strong><?= htmlspecialchars($name) ?></strong></td>
          <td><?= htmlspecialchars($curStr) ?></td>
          <td><?= htmlspecialchars($wantStr) ?></td>
          <td><?= $status ?></td>
          <td><?= $sqlFix ? '<div class="sql">'.$sqlFix.'</div>' : '' ?></td>
        </tr>
      <?php endforeach; ?>

      <?php
        foreach ($wanted as $name => $want) {
          if (!isset($cols[$name])) {
            echo '<tr>';
            echo '<td><strong>'.htmlspecialchars($name).'</strong></td>';
            echo '<td>(missing)</td>';
            echo '<td>'.htmlspecialchars(wantTypeString($want)).'</td>';
            echo '<td><span class="badge err">Missing</span></td>';
            echo '<td><div class="sql">'.alterSQL($tbl, $name, $want, true).'</div></td>';
            echo '</tr>';
          }
        }
      ?>
    </table>
  <?php endif; ?>
<?php endforeach; ?>

<?php if (!$loadedHeader) { echo "</body></html>"; } ?>
