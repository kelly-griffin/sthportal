<?php
// admin/backup-now.php — one-click backup with env check (incl. DB probe), robust DB fallback + clear diagnostics
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_admin();

$rootDir   = realpath(__DIR__ . '/..');
$backupDir = $rootDir . DIRECTORY_SEPARATOR . 'backups';
@mkdir($backupDir, 0775, true);
$htacc = $backupDir . DIRECTORY_SEPARATOR . '.htaccess';
if (!is_file($htacc)) { @file_put_contents($htacc, "Require all denied\nDeny from all\n"); }

function human_size($b){$u=['B','KB','MB','GB','TB'];$i=0;while($b>=1024&&$i<count($u)-1){$b/=1024;$i++;}return sprintf('%.1f %s',$b,$u[$i]);}
function list_backups($dir){$x=[];if(is_dir($dir)){foreach(scandir($dir) as $f){if($f==='.'||$f==='..')continue;$p=$dir.DIRECTORY_SEPARATOR.$f;if(is_file($p))$x[]=['name'=>$f,'size'=>filesize($p),'time'=>filemtime($p)];}}usort($x,fn($a,$b)=>$b['time']<=>$a['time']);return $x;}
function safe_download($dir,$name){$file=basename($name);$path=realpath($dir.DIRECTORY_SEPARATOR.$file);if(!$path||strpos($path,realpath($dir))!==0||!is_file($path)){http_response_code(404);exit('Not found');}header('Content-Type: application/octet-stream');header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.$file.'"');readfile($path);exit;}

function find_mysqldump_paths():array{
  $c=['mysqldump'];
  $win=['C:\xampp\mysql\bin\mysqldump.exe','C:\wamp64\bin\mysql\mysql8.0.31\bin\mysqldump.exe','C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe','C:\Program Files (x86)\MySQL\MySQL Server 8.0\bin\mysqldump.exe'];
  $nix=['/usr/bin/mysqldump','/usr/local/bin/mysqldump','/opt/homebrew/bin/mysqldump'];
  return array_merge($c, DIRECTORY_SEPARATOR==='\\' ? $win : $nix);
}
function detect_mysqldump(): array {
  foreach (find_mysqldump_paths() as $p) {
    if (@is_file($p)) return ['found'=>true,'path'=>$p];
  }
  $out=[]; $code=1;
  if (DIRECTORY_SEPARATOR === '\\') { @exec('where mysqldump 2>nul', $out, $code); }
  else { @exec('command -v mysqldump 2>/dev/null', $out, $code); if(empty($out)) @exec('which mysqldump 2>/dev/null', $out, $code); }
  if (!empty($out[0])) return ['found'=>true,'path'=>trim($out[0])];
  return ['found'=>false,'path'=>null];
}
function try_mysqldump($host,$user,$pass,$db,$out):bool{
  foreach(find_mysqldump_paths() as $bin){
    $cmd = (DIRECTORY_SEPARATOR==='\\')
      ? "\"$bin\" --host=\"$host\" --user=\"$user\" --password=\"$pass\" --routines --triggers --single-transaction --quick \"$db\" > \"$out\""
      : escapeshellcmd($bin).' --host='.escapeshellarg($host).' --user='.escapeshellarg($user).' --password='.escapeshellarg($pass).' --routines --triggers --single-transaction --quick '.escapeshellarg($db).' > '.escapeshellarg($out);
    @exec($cmd,$o,$code);
    if($code===0 && is_file($out) && filesize($out)>0) return true;
    @unlink($out);
  }
  return false;
}

/** PHP fallback dumper with reason output */
function php_dump_db(mysqli $dbc, string $dbName, string $outFile, ?string &$why=null): bool {
  $why = null;
  $fh = @fopen($outFile,'wb');
  if(!$fh){ $why = "Cannot write dump file at $outFile (permissions/path)"; return false; }

  @set_time_limit(0);
  @ini_set('memory_limit','512M');
  @$dbc->set_charset('utf8mb4');

  fwrite($fh, "-- STH Portal PHP dump for `$dbName`\n-- ".date('c')."\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");

  $tables=[]; 
  $res = @$dbc->query("SHOW TABLES");
  if(!$res){ $why="SHOW TABLES failed (insufficient privileges?)"; fclose($fh); @unlink($outFile); return false; }
  while($r=$res->fetch_row()) $tables[]=$r[0];
  $res->close();

  foreach($tables as $t){
    $res = @$dbc->query("SHOW CREATE TABLE `$t`");
    if($res){
      $row=$res->fetch_assoc(); $res->close();
      $create = $row['Create Table'] ?? (array_values($row)[1] ?? '');
      fwrite($fh,"--\n-- Table structure for `$t`\n--\n");
      fwrite($fh,"DROP TABLE IF EXISTS `$t`;\n$create;\n\n");
    } else {
      $why="SHOW CREATE TABLE `$t` failed"; fclose($fh); @unlink($outFile); return false;
    }

    $res = @$dbc->query("SELECT * FROM `$t`", MYSQLI_USE_RESULT);
    if(!$res){ $why="SELECT * FROM `$t` failed"; fclose($fh); @unlink($outFile); return false; }
    $fields = $res->fetch_fields();
    $cols = array_map(fn($f)=>'`'.$f->name.'`',$fields);
    $colList = implode(',', $cols);
    while($row=$res->fetch_assoc()){
      $vals=[];
      foreach($fields as $f){
        $v=$row[$f->name];
        $vals[] = ($v===null) ? "NULL" : "'".$dbc->real_escape_string($v)."'";
      }
      fwrite($fh,"INSERT INTO `$t` ($colList) VALUES (".implode(',',$vals).");\n");
    }
    $res->close();
    fwrite($fh,"\n");
  }
  fwrite($fh,"SET FOREIGN_KEY_CHECKS=1;\n");
  fclose($fh);
  return is_file($outFile) && filesize($outFile)>0;
}

/** Archive codebase: ZipArchive if available, else .tar.gz via PharData; returns (bool,$out,$hint) */
function archive_codebase(string $rootDir,string $backupDir,string $base,?string &$out,string &$hint):bool{
  $out=null; $hint='';
  $skip=[realpath($backupDir),realpath($rootDir.DIRECTORY_SEPARATOR.'.git'),realpath($rootDir.DIRECTORY_SEPARATOR.'node_modules')];
  $shouldSkip=function(string $abs)use($skip){foreach($skip as $s){if($s && strpos($abs,$s)===0)return true;}return false;};

  if(class_exists('ZipArchive')){
    $zipPath=$backupDir.DIRECTORY_SEPARATOR.$base.'.zip';
    $zip=new ZipArchive();
    if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)===true){
      $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
      foreach($it as $p){$abs=$p->getPathname(); if($shouldSkip($abs))continue; $local=ltrim(str_replace($rootDir,'',$abs),DIRECTORY_SEPARATOR); if(is_dir($abs)){$zip->addEmptyDir(str_replace('\\','/',$local));}else{$zip->addFile($abs,str_replace('\\','/',$local));}}
      $zip->close();
      if(is_file($zipPath)&&filesize($zipPath)>0){$out=$zipPath;return true;}
      @unlink($zipPath);
    }
    $hint='ZipArchive present but failed to write.';
  }
  if(class_exists('PharData') && (int)ini_get('phar.readonly')===0){
    $tar=$backupDir.DIRECTORY_SEPARATOR.$base.'.tar';
    try{
      if(is_file($tar))@unlink($tar);
      $phar=new PharData($tar);
      $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
      foreach($it as $p){$abs=$p->getPathname(); if($shouldSkip($abs))continue; $local=ltrim(str_replace($rootDir,'',$abs),DIRECTORY_SEPARATOR); if(!is_dir($abs)){$phar->addFile($abs,str_replace('\\','/',$local));}}
      $phar->compress(Phar::GZ); unset($phar); @unlink($tar);
      $gz=$tar.'.gz'; if(is_file($gz)&&filesize($gz)>0){$out=$gz;return true;}
      @unlink($gz);
    }catch(Throwable $e){$hint='PharData failed (phar.readonly must be 0).';}
  } else { if(!class_exists('PharData')) $hint='PharData not available'; elseif((int)ini_get('phar.readonly')!==0) $hint='phar.readonly=1 (cannot create archives)'; }
  if($hint==='') $hint='No archive engine available';
  return false;
}

/** DB connectivity probe for env check */
function db_probe(string $host,string $user,string $pass,string $name): array {
  $err = null; $via = null; $server = null; $ok = false;
  $dbc = null;

  if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) { $dbc = $GLOBALS['db']; $via = 'live'; }
  elseif (function_exists('get_db')) { $dbc = @get_db(); if ($dbc instanceof mysqli) $via = 'get_db'; }

  $fresh = false;
  if (!$dbc instanceof mysqli) {
    $dbc = @new mysqli($host, $user, $pass, $name);
    if ($dbc && !$dbc->connect_errno) { $via = 'new'; $fresh = true; }
    else { $err = $dbc ? $dbc->connect_error : 'mysqli init failed'; return ['ok'=>false,'via'=>$via,'server'=>null,'error'=>$err]; }
  }

  $res = @$dbc->query('SELECT 1');
  if ($res) { $ok = true; $res->close(); }
  else { $err = $dbc->error ?: 'SELECT 1 failed'; }

  $server = @$dbc->server_info ?: null;
  if ($fresh && $dbc instanceof mysqli) { @$dbc->close(); } // close only if we opened it

  return ['ok'=>$ok,'via'=>$via,'server'=>$server,'error'=>$err];
}

// ---------- environment check (incl. DB probe) ----------
$host=defined('DB_HOST')?DB_HOST:'localhost';
$user=defined('DB_USER')?DB_USER:'root';
$pass=defined('DB_PASS')?DB_PASS:'';
$name=defined('DB_NAME')?DB_NAME:'';

$md  = detect_mysqldump();
$dbp = db_probe($host,$user,$pass,$name);

$env = [
  'php'  => PHP_VERSION,
  'os'   => PHP_OS_FAMILY,
  'mysqldump_found' => $md['found'],
  'mysqldump_path'  => $md['path'],
  'zip'  => class_exists('ZipArchive'),
  'phar' => class_exists('PharData'),
  'phar_readonly' => (int)ini_get('phar.readonly'),
  'db_ok' => $dbp['ok'],
  'db_via'=> $dbp['via'],
  'db_server' => $dbp['server'],
  'db_error'  => $dbp['error'],
  'db_name'   => $name,
];

// ---------- run backup (POST) ----------
if(isset($_GET['download'])){ safe_download($backupDir,$_GET['download']); }

$ran=false; $err=''; $diag=''; $codeNote=''; $codeOut=null; $dbFile=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  @set_time_limit(0);
  $ts=date('Ymd-His');
  $dbFile=$backupDir.DIRECTORY_SEPARATOR."db-{$ts}.sql";

  // 1) Prefer mysqldump
  $dbOK=try_mysqldump($host,$user,$pass,$name,$dbFile);

  // 2) Fallback: live handle or fresh mysqli
  if(!$dbOK){
    $dbc=null;
    if(isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli){ $dbc=$GLOBALS['db']; }
    elseif(function_exists('get_db')){ $dbc=@get_db(); }
    if(!$dbc instanceof mysqli){
      $dbc=@new mysqli($host,$user,$pass,$name);
      if($dbc && $dbc->connect_errno){ $diag="mysqli connect failed ({$dbc->connect_errno}) {$dbc->connect_error}"; $dbc=null; }
    }
    if($dbc instanceof mysqli){
      $why=null;
      $dbOK=php_dump_db($dbc,$name,$dbFile,$why);
      if(!$dbOK && $why){ $diag = $why; }
    } else {
      if($diag==='') $diag='No mysqli handle available for PHP fallback.';
    }
  }

  if(!$dbOK){
    $err='Database dump failed (mysqldump not found and PHP fallback failed).';
    if($diag) $err .= ' Reason: '.$diag;
    if(is_file($dbFile) && filesize($dbFile)===0) @unlink($dbFile);
  }

  // Code archive (best effort)
  $arcOK=archive_codebase($rootDir,$backupDir,"code-{$ts}",$codeOut,$codeNote);
  if(!$arcOK){ $codeNote='Code archive skipped: '.$codeNote.'. Enable PHP zip extension or set phar.readonly=0.'; }

  if(!$err){
    $ran=true;
    $details="db=".basename($dbFile); if($arcOK) $details.="; code=".basename($codeOut);
    log_audit('backup_created',$details,'admin');
  }
}

// ---------- header ----------
$loadedHeader=false;
foreach([__DIR__.'/admin-header.php',__DIR__.'/header-admin.php',__DIR__.'/header.php',__DIR__.'/../includes/admin-header.php'] as $p){if(is_file($p)){include $p;$loadedHeader=true;break;}}
if(!$loadedHeader){echo "<!doctype html><meta charset='utf-8'><title>Backup</title><body>";}
?>
<h1>Backup Now</h1>
<style>
  .box{border:1px solid #ddd;border-radius:8px;padding:12px;background:#fff;max-width:980px}
  .btn{padding:.3rem .6rem;border:1px solid #ccc;border-radius:6px;background:#fff;text-decoration:none;color:#333}
  .btn:hover{background:#f7f7f7}
  .ok{background:#e8fff0;border-color:#9bd3af}
  .bad{background:#ffecec;border-color:#e3a0a0}
  .note{color:#555;font-size:.92rem}
  .env{display:flex;flex-wrap:wrap;gap:.5rem;margin:.4rem 0 .8rem 0}
  .pill{padding:.18rem .5rem;border:1px solid #ddd;border-radius:999px;font-size:.86rem}
  .pill.ok{background:#e8fff0;border-color:#9bd3af}
  .pill.no{background:#ffecec;border-color:#e3a0a0}
  table{border-collapse:collapse;width:100%;margin-top:10px}
  th,td{border:1px solid #e3e3e3;padding:6px 8px;font-size:.95rem;text-align:left}
  th{background:#f7f7f7}
  .mono{font-family:ui-monospace,Menlo,Consolas,monospace}
</style>

<div class="box">
  <!-- Environment Check -->
  <div class="env">
    <span class="pill">PHP <?= htmlspecialchars($env['php']) ?> · <?= htmlspecialchars($env['os']) ?></span>
    <span class="pill <?= $env['mysqldump_found'] ? 'ok' : 'no' ?>">
      mysqldump <?= $env['mysqldump_found'] ? 'found' : 'missing' ?><?= $env['mysqldump_found'] && $env['mysqldump_path'] ? ' — '.htmlspecialchars($env['mysqldump_path']) : '' ?>
    </span>
    <span class="pill <?= $env['zip'] ? 'ok' : 'no' ?>">ZipArchive <?= $env['zip']?'enabled':'disabled' ?></span>
    <span class="pill <?= $env['phar'] && $env['phar_readonly']===0 ? 'ok' : 'no' ?>">
      PharData <?= $env['phar'] ? ('ready='.($env['phar_readonly']===0?'yes':'no (readonly=1)')) : 'unavailable' ?>
    </span>
    <span class="pill <?= $env['db_ok'] ? 'ok' : 'no' ?>">
      DB <?= $env['db_ok'] ? 'ok' : 'fail' ?>
      <?php if ($env['db_ok']): ?>
        — MySQL <?= htmlspecialchars((string)$env['db_server']) ?> @ <?= htmlspecialchars((string)$env['db_name']) ?> (via <?= htmlspecialchars((string)$env['db_via']) ?>)
      <?php elseif (!empty($env['db_error'])): ?>
        — <?= htmlspecialchars((string)$env['db_error']) ?>
      <?php endif; ?>
    </span>
  </div>

  <form method="post" style="margin-bottom:.5rem;">
    <button type="submit" class="btn">📦 Run Backup Now</button>
    <a class="btn" href="index.php">↩️ Admin Home</a>
  </form>

  <?php if($ran && !$err): ?>
    <p class="ok" style="margin-top:.2rem;padding:.4rem .5rem;border-radius:6px;">✅ Backup completed.</p>
    <ul class="note">
      <li>DB file: <span class="mono"><?= htmlspecialchars(basename($dbFile)) ?></span></li>
      <?php if(!empty($codeOut)): ?><li>Code archive: <span class="mono"><?= htmlspecialchars(basename($codeOut)) ?></span></li><?php else: ?><li>Code archive: <em>not created</em> (see note below)</li><?php endif; ?>
    </ul>
    <?php if($codeNote): ?><div class="note" style="margin-top:.4rem;">⚠️ <?= htmlspecialchars($codeNote) ?></div><?php endif; ?>
  <?php elseif($err): ?>
    <p class="bad" style="margin-top:.2rem;padding:.4rem .5rem;border-radius:6px;">❌ <?= htmlspecialchars($err) ?></p>
  <?php endif; ?>

  <h3 style="margin-top:.8rem;">Existing Backups</h3>
  <?php $files=list_backups($backupDir); if(!$files): ?>
    <p>No backups yet.</p>
  <?php else: ?>
    <table>
      <tr><th>File</th><th>Size</th><th>Modified</th><th>Download</th></tr>
      <?php foreach($files as $f): ?>
        <tr>
          <td class="mono"><?= htmlspecialchars($f['name']) ?></td>
          <td><?= human_size((int)$f['size']) ?></td>
          <td><?= date('Y-m-d H:i:s', (int)$f['time']) ?></td>
          <td><a class="btn" href="?download=<?= urlencode($f['name']) ?>">Download</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<?php if(!$loadedHeader){echo "</body>";}
