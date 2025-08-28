<?php
// admin/alt-radar-digest.php — Alt Radar Daily Digest
// Includes: recipients preview, clickable links back to Admin drilldowns, focus= highlight jump, web & CLI friendly.

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/util-mail.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_once __DIR__ . '/../includes/config.php';

$IS_CLI = (PHP_SAPI === 'cli');
if (!$IS_CLI) { require_admin(); }

// ---- Easy knobs ----
$DAYS          = isset($_GET['days']) ? max(7, (int)$_GET['days']) : 30; // lookback window
$INCLUDE_LOCAL = false;  // include localhost IPs?
$MIN_USERS     = 2;      // min distinct users per IP for "Shared IPs"
$MIN_SHAREDIP  = 2;      // min shared IPs for "Account Pairs"
$MED_MIN       = 4;      // include medium (4–5)
$HI_MIN        = 6;      // high risk 6+

// ---- DB handle ----
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
elseif (function_exists('get_db'))                   { $dbc = get_db(); }
if (!$dbc) { http_response_code(500); exit('No DB handle.'); }

// ---- Helpers ----
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function recency_bonus(?string $lastSeen): int {
    if (!$lastSeen) return 0;
    $ts = strtotime($lastSeen);
    if ($ts === false) return 0;
    $days = (time() - $ts) / 86400.0;
    if ($days <= 7) return 2;
    if ($days <= 30) return 1;
    return 0;
}
// Best-effort absolute admin base URL for email links
function admin_base_url(): string {
    $base = '';
    if (defined('SITE_URL')) {
        $u = (string)SITE_URL;
        if (preg_match('~/(index\.php)?$~i', $u)) { $u = preg_replace('~/index\.php$~i', '/', $u); }
        if (substr($u, -1) !== '/') $u .= '/';
        $base = $u . 'admin/';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path   = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $path   = preg_replace('~/admin/.*$~', '/', $path);
        if (substr($path, -1) !== '/') $path .= '/';
        $base = $scheme . '://' . $host . $path . 'admin/';
    }
    return $base;
}
$ADMIN_BASE = admin_base_url();

// ---- Ensure tables used by Alt Radar features exist ----
$dbc->query("CREATE TABLE IF NOT EXISTS alt_radar_watchlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identity VARCHAR(190) NOT NULL,
  note VARCHAR(255) NULL,
  created_by VARCHAR(128) NULL,
  created_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_identity (identity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$dbc->query("CREATE TABLE IF NOT EXISTS alt_radar_ignores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('ip','identity') NOT NULL,
  value VARCHAR(190) NOT NULL,
  note VARCHAR(255) NULL,
  created_by VARCHAR(128) NULL,
  created_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_kind_value (kind, value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_GENERAL_CI");

// ---- Query fragments ----
$notLocal = $INCLUDE_LOCAL ? "" : " AND la.ip NOT IN ('127.0.0.1','::1','::ffff:127.0.0.1') ";
$lookback = " la.attempted_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ";
$notIgnoredIP  = " AND NOT EXISTS (SELECT 1 FROM alt_radar_ignores ign WHERE ign.kind='ip' AND ign.value = la.ip) ";
$notIgnoredWho = " AND NOT EXISTS (SELECT 1 FROM alt_radar_ignores ign WHERE ign.kind='identity' AND ign.value = COALESCE(la.username, la.actor, la.identity)) ";

// ---- Build datasets ----

// Watchlist stats
$watch = []; // rows with score >= MED_MIN
if ($res = $dbc->query("SELECT identity, note FROM alt_radar_watchlist ORDER BY identity")) {
  while ($w = $res->fetch_assoc()) {
    $ident = (string)$w['identity'];
    $sql = "SELECT MAX(attempted_at) AS last_seen,
                   COUNT(DISTINCT ip) AS ips
            FROM login_attempts la
            WHERE (COALESCE(la.username, la.actor, la.identity) = ?)
              AND la.ip IS NOT NULL AND la.ip <> ''
              AND la.success = 1
              AND $lookback " . $notLocal;
    if ($st = $dbc->prepare($sql)) {
      $st->bind_param('si', $ident, $DAYS);
      $st->execute();
      $r = $st->get_result()->fetch_assoc();
      $st->close();
      $last = $r && $r['last_seen'] ? (string)$r['last_seen'] : null;
      $ips  = $r ? (int)$r['ips'] : 0;
      $score = $ips + recency_bonus($last);
      if ($score >= $MED_MIN) {
        $watch[] = ['identity'=>$ident,'note'=>$w['note'] ?? '','last_seen'=>$last,'ips'=>$ips,'score'=>$score];
      }
    }
  }
}
usort($watch, function($a,$b){ $d = ($b['score'] <=> $a['score']); return $d ?: strcasecmp($a['identity'],$b['identity']); });

// Shared IPs
$shared = [];
$sql = "SELECT la.ip,
               COUNT(DISTINCT COALESCE(la.username, la.actor, la.identity)) AS user_count,
               GROUP_CONCAT(DISTINCT COALESCE(la.username, la.actor, la.identity)
                            ORDER BY COALESCE(la.username, la.actor, la.identity) SEPARATOR ', ') AS users,
               MAX(la.attempted_at) AS last_seen
        FROM login_attempts la
        WHERE la.success=1
          AND la.ip IS NOT NULL AND la.ip <> ''
          AND $lookback
          $notLocal
          $notIgnoredIP
          $notIgnoredWho
        GROUP BY la.ip
        HAVING user_count >= ?
        ORDER BY user_count DESC, last_seen DESC
        LIMIT 200";
if ($st = $dbc->prepare($sql)) {
  $st->bind_param('ii', $DAYS, $MIN_USERS);
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) {
    $r['score'] = (int)$r['user_count'] + recency_bonus($r['last_seen']);
    if ($r['score'] >= $MED_MIN) $shared[] = $r;
  }
  $st->close();
}
usort($shared, function($a,$b){ $d=($b['score']<=>$a['score']); return $d ?: strcmp((string)$b['last_seen'], (string)$a['last_seen']); });

// Account pairs
$pairs = [];
$sql = "
  SELECT
    LEAST(t1.identity, t2.identity) AS a,
    GREATEST(t1.identity, t2.identity) AS b,
    COUNT(DISTINCT t1.ip) AS shared_ips,
    MAX(GREATEST(t1.last_seen, t2.last_seen)) AS last_seen
  FROM (
    SELECT la.ip, COALESCE(la.username, la.actor, la.identity) AS identity, MAX(la.attempted_at) AS last_seen
    FROM login_attempts la
    WHERE la.success=1
      AND la.ip IS NOT NULL AND la.ip <> ''
      AND $lookback
      $notLocal
      $notIgnoredIP
      $notIgnoredWho
    GROUP BY la.ip, identity
  ) AS t1
  JOIN (
    SELECT la.ip, COALESCE(la.username, la.actor, la.identity) AS identity, MAX(la.attempted_at) AS last_seen
    FROM login_attempts la
    WHERE la.success=1
      AND la.ip IS NOT NULL AND la.ip <> ''
      AND $lookback
      $notLocal
      $notIgnoredIP
      $notIgnoredWho
    GROUP BY la.ip, identity
  ) AS t2
    ON t1.ip = t2.ip AND t1.identity < t2.identity
  GROUP BY a, b
  HAVING shared_ips >= ?
  ORDER BY shared_ips DESC, last_seen DESC
  LIMIT 200";
if ($st = $dbc->prepare($sql)) {
  $st->bind_param('iii', $DAYS, $DAYS, $MIN_SHAREDIP);
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) {
    $r['score'] = (int)$r['shared_ips'] + recency_bonus($r['last_seen']);
    if ($r['score'] >= $MED_MIN) $pairs[] = $r;
  }
  $st->close();
}
usort($pairs, function($a,$b){ $d=($b['score']<=>$a['score']); return $d ?: strcmp((string)$b['last_seen'], (string)$a['last_seen']); });

// ---- Recipients (shared for preview and send) ----
function admin_recipients(mysqli $dbc): array {
  $recips = [];
  if ($rs = $dbc->query(
        "SELECT email FROM users
         WHERE active=1 AND email <> '' AND (
           role LIKE '%admin%' OR role LIKE '%owner%' OR role LIKE '%commission%' OR role LIKE '%commish%' OR role LIKE '%gm%'
         )")) {
    while ($r = $rs->fetch_row()) { $recips[] = (string)$r[0]; }
  }
  $recips = array_values(array_unique($recips));
  if (!$recips && !empty($_SESSION['user']['email'])) { $recips = [ (string)$_SESSION['user']['email'] ]; }
  return $recips;
}
$recips_preview = admin_recipients($dbc);

// ---- HTML body (for preview + email) ----
ob_start();
?>
<!doctype html>
<html>
  <head><meta charset="utf-8"><title>Alt Radar Daily Digest</title></head>
  <body style="font:14px/1.35 -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#111;">
    <div style="max-width:880px;margin:0 auto;padding:12px;">
      <h1 style="margin:.2rem 0 1rem;">Alt Radar Daily Digest</h1>
      <div style="color:#555;margin-bottom:10px;">Lookback: <?= (int)$DAYS ?> days • Includes scores <?= (int)$MED_MIN ?>+ (medium & high)</div>

      <h2 style="margin:.8rem 0 .4rem;">Watchlist Highlights</h2>
      <?php if (!$watch): ?>
        <div style="color:#666;">No watchlist entries scored <?= (int)$MED_MIN ?>+ in this window.</div>
      <?php else: ?>
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #eee;">
          <thead><tr style="background:#faf6d8;border-bottom:1px solid #eee;">
            <th align="left">Identity</th><th align="left">Last Seen</th><th align="left">Distinct IPs</th><th align="left">Score</th><th align="left">Note</th>
          </tr></thead>
          <tbody>
            <?php foreach ($watch as $w): $u = (string)$w['identity']; $url = $ADMIN_BASE . 'alt-radar.php?focus=' . rawurlencode($u) . '#watchlist'; ?>
              <tr>
                <td><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($u) ?></a></td>
                <td><?= e((string)($w['last_seen'] ?? '—')) ?></td>
                <td><?= (int)($w['ips'] ?? 0) ?></td>
                <td><strong><?= (int)($w['score'] ?? 0) ?></strong></td>
                <td><?= e((string)($w['note'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <h2 style="margin:1rem 0 .4rem;">Top Shared IPs (Score ≥ <?= (int)$MED_MIN ?>)</h2>
      <?php if (!$shared): ?>
        <div style="color:#666;">No shared IPs reached the threshold.</div>
      <?php else: ?>
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #eee;">
          <thead><tr style="background:#faf6d8;border-bottom:1px solid #eee;">
            <th align="left">IP</th><th align="left">Distinct Users</th><th align="left">Last Seen</th><th align="left">Score</th><th align="left">Users</th>
          </tr></thead>
          <tbody>
            <?php foreach ($shared as $r): $ip = (string)$r['ip']; $link = $ADMIN_BASE . 'alt-radar.php?view=ips&ip=' . rawurlencode($ip) . '&days=' . (int)$DAYS . '&min=' . (int)$MIN_USERS . '&local=' . ($INCLUDE_LOCAL?1:0); ?>
              <tr>
                <td><a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e($ip) ?></a></td>
                <td><?= (int)$r['user_count'] ?></td>
                <td><?= e((string)$r['last_seen']) ?></td>
                <td><strong><?= (int)$r['score'] ?></strong></td>
                <td>
                  <?php
                    $users = array_filter(array_map('trim', explode(', ', (string)$r['users'])));
                    $out = [];
                    foreach ($users as $u) {
                      $out[] = '<a href="'.e($ADMIN_BASE . 'alt-radar.php?focus=' . rawurlencode($u) . '#watchlist').'" target="_blank" rel="noopener">'.e($u).'</a>';
                    }
                    echo implode(' · ', $out);
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <h2 style="margin:1rem 0 .4rem;">Top Account Pairs (Score ≥ <?= (int)$MED_MIN ?>)</h2>
      <?php if (!$pairs): ?>
        <div style="color:#666;">No account pairs reached the threshold.</div>
      <?php else: ?>
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #eee;">
          <thead><tr style="background:#faf6d8;border-bottom:1px solid #eee;">
            <th align="left">Identity A</th><th align="left">Identity B</th><th align="left">Shared IPs</th><th align="left">Last Seen</th><th align="left">Score</th>
          </tr></thead>
          <tbody>
            <?php foreach ($pairs as $p):
              $a = (string)$p['a']; $b = (string)$p['b'];
              $plink = $ADMIN_BASE . 'alt-radar.php?view=pairs&a=' . rawurlencode($a) . '&b=' . rawurlencode($b) . '&days=' . (int)$DAYS . '&min_ips=' . (int)$MIN_SHAREDIP . '&local=' . ($INCLUDE_LOCAL?1:0);
            ?>
              <tr>
                <td><a href="<?= e($plink) ?>" target="_blank" rel="noopener"><?= e($a) ?></a></td>
                <td><a href="<?= e($plink) ?>" target="_blank" rel="noopener"><?= e($b) ?></a></td>
                <td><?= (int)$p['shared_ips'] ?></td>
                <td><?= e((string)$p['last_seen']) ?></td>
                <td><strong><?= (int)$p['score'] ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div style="color:#888;margin-top:1rem;">Generated at <?= e(date('Y-m-d H:i:s')) ?>.</div>
    </div>
  </body>
</html>
<?php
$html = ob_get_clean();

// ---- CLI mode support ----
if ($IS_CLI) {
  $argvStr = isset($argv) ? implode(' ', $argv) : '';
  $doPreview = (strpos($argvStr, '--preview') !== false);
  if ($doPreview) { echo $html; exit; }
}

// Web preview
if (isset($_GET['preview'])) { header('Content-Type: text/html; charset=UTF-8'); echo $html; exit; }

// ---- Send mode (web or CLI) ----
$send = ($IS_CLI || (isset($_GET['send']) && $_GET['send'] === '1')); $debug = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($send) {
  $recips = []; $fallback_email = $_SESSION['user']['email'] ?? '';
  if ($rs = $dbc->query(
        "SELECT email FROM users
         WHERE active=1 AND email <> '' AND (
           role LIKE '%admin%' OR role LIKE '%owner%' OR role LIKE '%commission%' OR role LIKE '%commish%' OR role LIKE '%gm%'
         )")) {
    while ($r = $rs->fetch_row()) { $recips[] = (string)$r[0]; }
  }
  $recips = array_values(array_unique($recips));
  if (!$recips && $fallback_email) { $recips = [$fallback_email]; }

  $okAll = true;
  $results = [];
  foreach ($recips as $to) {
    $ok = send_mail($to, "Alt Radar Daily Digest", $html);
    $err = '';
    if (!$ok) {
      if (function_exists('mail_last_error')) { $err = (string)mail_last_error(); }
      elseif (isset($GLOBALS['MAIL_LAST_ERROR'])) { $err = (string)$GLOBALS['MAIL_LAST_ERROR']; }
    }
    $results[] = ['to'=>$to, 'ok'=>$ok, 'err'=>$err];
    if (!$ok) $okAll = false;
  }

  if ($IS_CLI) { echo $okAll ? ("Sent to " . count($recips) . " recipient(s).") : "Some emails failed to send."; exit; }

  require_once __DIR__ . '/admin-header.php';
  $msg = $okAll ? ("Sent to " . count($recips) . " recipient(s).") : "Some emails failed to send.";
  ?>
  <div class="wrap">
    <h1>Alt Radar Daily Digest</h1>
    <p><?= e($msg) ?></p>
<?php if (!empty($debug) && isset($results)): ?>
<table class="mono" style="border-collapse:collapse;border:1px solid #eee;margin:.5rem 0;width:100%"><thead><tr style="background:#fafafa"><th align="left">Recipient</th><th align="left">Status</th><th align="left">Error</th></tr></thead><tbody><?php foreach ($results as $r): ?><tr><td style="padding:.3rem;border-bottom:1px solid #eee;"><?= htmlspecialchars((string)$r['to']) ?></td><td style="padding:.3rem;border-bottom:1px solid #eee;"><?= $r['ok'] ? '✅ sent' : '❌ failed' ?></td><td style="padding:.3rem;border-bottom:1px solid #eee;white-space:pre-line;"><?= htmlspecialchars((string)$r['err']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <div style="display:flex;gap:.5rem;align-items:center;margin:.5rem 0 1rem;">
      <a class="btn" href="index.php">← Admin Home</a>
      <a class="btn" href="alt-radar-digest.php">Back to Digest</a>
      <a class="btn" href="alt-radar-digest.php?preview=1" target="_blank">Preview Again</a>
    </div>
    <div class="muted">Recipients:</div>
    <ul>
      <?php foreach ($recips as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php
  exit;
}

// Default control page (web) — includes recipients preview
require_once __DIR__ . '/admin-header.php';
$recips_preview = admin_recipients($dbc);
?>
<div class="wrap">
  <h1>Alt Radar Daily Digest</h1>
  <p class="muted">Preview below. Send now to all active admins. Lookback <?= (int)$DAYS ?> days.</p>
  <div style="display:flex;gap:.5rem;align-items:center;margin:.5rem 0 1rem;">
    <a class="btn" href="index.php">← Admin Home</a>
    <a class="btn" href="alt-radar-digest.php?preview=1" target="_blank">Preview in Browser</a>
    <a class="btn" href="alt-radar-digest.php?send=1" onclick="return confirm('Send digest to admins?')">✉️ Send Now</a>
  </div>
  <div class="muted" style="margin: .25rem 0 .6rem;">Recipients (if sent now): 
    <?php if (!$recips_preview): ?>
      <em>None found — it will send to your account as a fallback.</em>
    <?php else: ?>
      <?= e(implode(', ', $recips_preview)) ?>
    <?php endif; ?>
  </div>
  <iframe src="alt-radar-digest.php?preview=1" style="width:100%;height:70vh;border:1px solid #eee;border-radius:8px;"></iframe>
</div>
