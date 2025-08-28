<?php
// admin/alt-radar.php — CB Alt Radar
// Phase 4: Watchlist + Layout tweaks (left-aligned filters, wider right column)
// Also includes: Ignores, Score badges, Legend, Sorting by score

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_perm('manage_users');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/** @var mysqli $dbc */
$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
else { http_response_code(500); exit('No DB handle.'); }

// ---------- Helpers ----------
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function int_param(string $k, int $d): int { return isset($_GET[$k]) ? (int)$_GET[$k] : $d; }
function str_param(string $k, string $d=''): string { return isset($_GET[$k]) ? (string)$_GET[$k] : $d; }
function bool_param(string $k): bool { return isset($_GET[$k]) && $_GET[$k] === '1'; }
function post($k,$d=''){ return $_POST[$k] ?? $d; }

function h_select(string $name, array $opts, $current): string {
    $out = '<select name="'.e($name).'" onchange="this.form && this.form.submit && this.form.submit()">';
    foreach ($opts as $val => $label) {
        $sel = ((string)$val === (string)$current) ? ' selected' : '';
        $out .= '<option value="'.e((string)$val).'"'.$sel.'>'.e($label).'</option>';
    }
    $out .= '</select>';
    return $out;
}
function send_csv(string $filename, array $rows, array $headers): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}
// Score helpers
function score_class(int $score): string {
    if ($score >= 6) return 'score-hi';     // red
    if ($score >= 4) return 'score-med';    // orange
    return 'score-low';                     // green-ish
}
function recency_bonus(?string $lastSeen): int {
    if (!$lastSeen) return 0;
    $ts = strtotime($lastSeen);
    if ($ts === false) return 0;
    $days = (time() - $ts) / 86400.0;
    if ($days <= 7) return 2;
    if ($days <= 30) return 1;
    return 0;
}

// ---------- Bootstrap tables ----------
function alt_ign_ensure(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS alt_radar_ignores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kind ENUM('ip','identity') NOT NULL,
        value VARCHAR(190) NOT NULL,
        note VARCHAR(255) NULL,
        created_by VARCHAR(128) NULL,
        created_ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_kind_value (kind, value)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}
function alt_watch_ensure(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS alt_radar_watchlist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identity VARCHAR(190) NOT NULL,
        note VARCHAR(255) NULL,
        created_by VARCHAR(128) NULL,
        created_ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_identity (identity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}
alt_ign_ensure($dbc);
alt_watch_ensure($dbc);

// ---------- Inputs / filters ----------
$view        = str_param('view','ips'); // ips | pairs
$days        = max(1, int_param('days', 60));
$includeLocal= bool_param('local'); // include 127.0.0.1 / ::1
$minUsers    = max(2, int_param('min', 2)); // ips: min distinct users
$minSharedIP = max(1, int_param('min_ips', 2)); // pairs: min shared IPs
$ipView      = str_param('ip', ''); // drilldown for an IP (only in ips view)
$pairA       = str_param('a', '');  // drilldown pair a (identity)
$pairB       = str_param('b', '');  // drilldown pair b (identity)
$export      = (str_param('export','') === 'csv');

// ---------- Actions (Ignore + Watchlist) ----------
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($CSRF, (string)post('csrf',''))) { http_response_code(400); exit('Bad CSRF'); }
    $action = (string)post('action','');

    if ($action === 'add_ignore') {
        $kind = ((string)post('kind','ip') === 'identity') ? 'identity' : 'ip';
        $value = trim((string)post('value',''));
        $note = trim((string)post('note',''));
        if ($value !== '') {
            $who = $_SESSION['user']['email'] ?? 'admin';
            $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
            $sql = "INSERT INTO alt_radar_ignores (kind,value,note,created_by,created_ip)
                    VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE note=VALUES(note), created_by=VALUES(created_by), created_ip=VALUES(created_ip)";
            if ($stmt = $dbc->prepare($sql)) {
                $stmt->bind_param('sssss', $kind, $value, $note, $who, $ip);
                $stmt->execute(); $stmt->close();
                $flash = 'Ignore saved.';
            }
        }
    } elseif ($action === 'del_ignore') {
        $id = (int)post('id', 0);
        if ($id > 0) {
            $dbc->query("DELETE FROM alt_radar_ignores WHERE id = ".(int)$id);
            $flash = 'Ignore removed.';
        }
    } elseif ($action === 'add_watch') {
        $ident = trim((string)post('identity',''));
        $note  = trim((string)post('note',''));
        if ($ident !== '') {
            $who = $_SESSION['user']['email'] ?? 'admin';
            $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
            $sql = "INSERT INTO alt_radar_watchlist (identity, note, created_by, created_ip)
                    VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE note=VALUES(note)";
            if ($stmt = $dbc->prepare($sql)) {
                $stmt->bind_param('ssss', $ident, $note, $who, $ip);
                $stmt->execute(); $stmt->close();
                $flash = 'Added to watchlist.';
            }
        }
    } elseif ($action === 'del_watch') {
        $ident = trim((string)post('identity',''));
        if ($ident !== '') {
            $sql = "DELETE FROM alt_radar_watchlist WHERE identity = ?";
            if ($stmt = $dbc->prepare($sql)) {
                $stmt->bind_param('s', $ident);
                $stmt->execute(); $stmt->close();
                $flash = 'Removed from watchlist.';
            }
        }
    }
}

// ---------- Common WHERE components ----------
$notLocal = $includeLocal ? "" : " AND la.ip NOT IN ('127.0.0.1','::1','::ffff:127.0.0.1') ";
$lookback = " la.attempted_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ";
$notIgnoredIP  = " AND NOT EXISTS (SELECT 1 FROM alt_radar_ignores ign WHERE ign.kind='ip' AND ign.value = la.ip) ";
$notIgnoredWho = " AND NOT EXISTS (SELECT 1 FROM alt_radar_ignores ign WHERE ign.kind='identity' AND ign.value = COALESCE(la.username, la.actor)) ";

// ---------- Pull watchlist for star toggles ----------
$watchRows = [];
$watchMap = [];
if ($res = $dbc->query("SELECT id, identity, note, created_by, created_ip, created_at FROM alt_radar_watchlist ORDER BY identity")) {
    while ($row = $res->fetch_assoc()) {
        $watchRows[] = $row;
        $watchMap[$row['identity']] = true;
    }
}

// ---------- View: Shared IPs ----------
$sharedRows = [];
if ($view === 'ips' && $ipView === '') {
    $sql = "SELECT la.ip,
                   COUNT(DISTINCT COALESCE(la.username, la.actor)) AS user_count,
                   GROUP_CONCAT(DISTINCT COALESCE(la.username, la.actor)
                                ORDER BY COALESCE(la.username, la.actor) SEPARATOR ', ') AS users,
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
            LIMIT 1000";
    if ($stmt = $dbc->prepare($sql)) {
        $stmt->bind_param('ii', $days, $minUsers);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['score'] = (int)$row['user_count'] + recency_bonus($row['last_seen']);
            $sharedRows[] = $row;
        }
        $stmt->close();
    }
    // Sort by score desc, then last_seen desc
    usort($sharedRows, function($a,$b){
        $cmp = ($b['score'] <=> $a['score']);
        if ($cmp !== 0) return $cmp;
        return strcmp((string)$b['last_seen'], (string)$a['last_seen']);
    });
    if ($export) {
        $csv = [];
        foreach ($sharedRows as $r) $csv[] = [$r['ip'], $r['user_count'], $r['last_seen'], $r['score'], $r['users']];
        send_csv('alt-radar-shared-ips.csv', $csv, ['IP','Distinct Users','Last Seen','Score','Users']);
    }
}

// Drilldown: one IP
$drillRows = []; $recentRows = [];
$hasUA = false;
if ($view === 'ips' && $ipView !== '') {
    $hasUA = function_exists('__log_has_column') ? __log_has_column($dbc, 'login_attempts', 'user_agent') : false;

    $sql1 = "SELECT COALESCE(username, actor) AS identity,
                    COUNT(*) AS attempts,
                    SUM(success) AS successes,
                    MAX(attempted_at) AS last_seen
             FROM login_attempts
             WHERE ip = ?
               AND NOT EXISTS (SELECT 1 FROM alt_radar_ignores ign WHERE ign.kind='identity' AND ign.value = COALESCE(username, actor))
             GROUP BY identity
             ORDER BY last_seen DESC";
    if ($stmt = $dbc->prepare($sql1)) {
        $stmt->bind_param('s', $ipView);
        $stmt->execute();
        $res = $stmt->get_result();
        $drillRows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $selCols = "attempted_at, COALESCE(username, actor) AS identity, success, note";
    if ($hasUA) $selCols .= ", user_agent";
    $sql2 = "SELECT $selCols
             FROM login_attempts
             WHERE ip = ?
             ORDER BY attempted_at DESC
             LIMIT 200";
    if ($stmt = $dbc->prepare($sql2)) {
        $stmt->bind_param('s', $ipView);
        $stmt->execute();
        $res = $stmt->get_result();
        $recentRows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

// ---------- View: Pairs (account-to-account) ----------
$pairs = [];
if ($view === 'pairs' && $pairA === '' && $pairB === '') {
    $sql = "
        SELECT
          LEAST(t1.identity, t2.identity) AS a,
          GREATEST(t1.identity, t2.identity) AS b,
          COUNT(DISTINCT t1.ip) AS shared_ips,
          MAX(GREATEST(t1.last_seen, t2.last_seen)) AS last_seen
        FROM (
          SELECT la.ip, COALESCE(la.username, la.actor) AS identity, MAX(la.attempted_at) AS last_seen
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
          SELECT la.ip, COALESCE(la.username, la.actor) AS identity, MAX(la.attempted_at) AS last_seen
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
        LIMIT 1000
    ";
    if ($stmt = $dbc->prepare($sql)) {
        $stmt->bind_param('iii', $days, $days, $minSharedIP);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['score'] = (int)$row['shared_ips'] + recency_bonus($row['last_seen']);
            $pairs[] = $row;
        }
        $stmt->close();
    }
    // Sort by score desc, then last_seen desc
    usort($pairs, function($a,$b){
        $cmp = ($b['score'] <=> $a['score']);
        if ($cmp !== 0) return $cmp;
        return strcmp((string)$b['last_seen'], (string)$a['last_seen']);
    });
    if ($export) {
        $csv = [];
        foreach ($pairs as $p) $csv[] = [$p['a'], $p['b'], $p['shared_ips'], $p['last_seen'], $p['score']];
        send_csv('alt-radar-pairs.csv', $csv, ['Identity A','Identity B','Shared IPs','Last Seen','Score']);
    }
}

// Drilldown: a specific pair (list the shared IPs & when each was used)
$pairDetails = [];
if ($view === 'pairs' && $pairA !== '' && $pairB !== '') {
    $sql = "
      SELECT ip,
             MAX(CASE WHEN ident = ? THEN last_seen END) AS last_seen_a,
             MAX(CASE WHEN ident = ? THEN last_seen END) AS last_seen_b
      FROM (
        SELECT la.ip, COALESCE(la.username, la.actor) AS ident, MAX(la.attempted_at) AS last_seen
        FROM login_attempts la
        WHERE la.success=1
          AND la.ip IS NOT NULL AND la.ip <> ''
          AND $lookback
          $notLocal
          $notIgnoredIP
          $notIgnoredWho
          AND COALESCE(la.username, la.actor) IN (?,?)
        GROUP BY la.ip, ident
      ) AS t
      GROUP BY ip
      HAVING last_seen_a IS NOT NULL AND last_seen_b IS NOT NULL
      ORDER BY GREATEST(last_seen_a, last_seen_b) DESC
    ";
    if ($stmt = $dbc->prepare($sql)) {
        // Correct placeholder order: pairA, pairB, days, pairA, pairB
        $stmt->bind_param('ssssi', $pairA, $pairB, $days, $pairA, $pairB);
        $stmt->execute();
        $res = $stmt->get_result();
        $pairDetails = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

// ---------- Stats for Watchlist identities (last $days, include/exclude localhost like main view) ----------
$watchStats = []; // key: identity => ['last_seen'=>..., 'ips'=>N, 'score'=>M]
if (!empty($watchRows)) {
    foreach ($watchRows as $wr) {
        $ident = (string)$wr['identity'];
        $sql = "SELECT MAX(attempted_at) AS last_seen,
                       COUNT(DISTINCT ip) AS ips
                FROM login_attempts la
                WHERE (COALESCE(la.username, la.actor) = ?)
                  AND la.ip IS NOT NULL AND la.ip <> ''
                  AND la.success = 1
                  AND $lookback " . $notLocal;
        if ($stmt = $dbc->prepare($sql)) {
            $stmt->bind_param('si', $ident, $days);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            $last = $row && $row['last_seen'] ? (string)$row['last_seen'] : null;
            $ips  = $row ? (int)$row['ips'] : 0;
            $score = $ips + recency_bonus($last);
            $watchStats[$ident] = ['last_seen'=>$last, 'ips'=>$ips, 'score'=>$score];
        }
    }
    // Sort watchRows by score desc, then name
    usort($watchRows, function($a,$b) use ($watchStats){
        $sa = $watchStats[$a['identity']]['score'] ?? 0;
        $sb = $watchStats[$b['identity']]['score'] ?? 0;
        $cmp = ($sb <=> $sa);
        if ($cmp !== 0) return $cmp;
        return strcasecmp($a['identity'], $b['identity']);
    });
}

// ---------- Load ignores for UI ----------
$ignores = [];
if ($res = $dbc->query("SELECT id, kind, value, note, created_by, created_ip, created_at FROM alt_radar_ignores ORDER BY created_at DESC, kind, value")) {
    while ($row = $res->fetch_assoc()) $ignores[] = $row;
}

// ---------- Render ----------
require_once __DIR__ . '/admin-header.php';
?>
<style>
  .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; justify-content:space-between; margin:.5rem 0; }
  /* left-align the filter bar */
  .toolbar-left{ justify-content:flex-start; }
  .btn{ display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .6rem; border:1px solid var(--btn-border,#e6cf66); border-radius:var(--btn-radius,.4rem); background:var(--btn-bg,#fff3cd); text-decoration:none; color:#2b2300; }
  .btn:hover{ background:var(--btn-bg-hover,#fff1a8); border-color:#e0c143; }
  .tabbar a{ padding:.25rem .5rem; border:1px solid #ddd; border-radius:.35rem; margin-right:.35rem; text-decoration:none; color:#222; }
  .tabbar a.active{ background:#faf6d8; border-color:#e0c143; font-weight:bold; }
  table.list{ width:100%; border-collapse:collapse; margin:.5rem 0 1rem; }
  table.list th, table.list td{ border:1px solid #ddd; padding:.4rem .5rem; text-align:left; vertical-align:top; }
  table.list th{ background:#faf6d8; }
  .muted{ color:#667; }
  .pill{ display:inline-block; padding:.1rem .35rem; border:1px solid #ddd; border-radius:.35rem; margin:0 .2rem .2rem 0; background:#fff; }
  .nowrap{ white-space:nowrap; }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }

  /* Two-column grid: 700/500-ish split, full width */
  .stack{
    display:grid;
    grid-template-columns: minmax(740px, 7fr) minmax(500px, 5fr);
    gap: 1.25rem;
    align-items:start;
    width: 100%;
    margin: 0;
  }
  @media (max-width: 1200px){
    .stack{ grid-template-columns: 1fr; }
  }

  /* Score badges */
  .score-badge{ display:inline-block; padding:.05rem .45rem; border-radius:.5rem; border:1px solid; font-weight:700; font-size:.9rem; }
  .score-hi{ background:#ffe3e3; border-color:#ff8a8a; color:#8a0000; }
  .score-med{ background:#fff1c9; border-color:#f1c232; color:#6b4b00; }
  .score-low{ background:#eef7d9; border-color:#a8d08d; color:#2d5e00; }

  /* Tiny star button */
  .star{ background:#fffbea; border:1px solid #e6cf66; border-radius:.4rem; padding:.05rem .35rem; cursor:pointer; }
</style>

<div class="toolbar">
  <div>
    <h1 style="margin:.2rem 0;">CB Alt Radar</h1>
    <div class="muted">Shared IPs, account pairs & quick drilldowns (read-only)</div>
    <?php if ($flash): ?><div class="pill" style="border-color:#e0c143;background:#fff9d6;margin-top:.35rem;"><?= e($flash) ?></div><?php endif; ?>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <a class="btn" href="#watchlist">⭐ Watchlist</a>
    <a class="btn" href="#ignores">🗂 Ignores</a>
    <a class="btn" href="index.php">← Admin Home</a>
  
    <a class="btn" href="alt-radar-digest.php">✉️ Digest</a></div>
</div>

<form method="get" class="toolbar toolbar-left" style="gap:.6rem;">
  <div class="tabbar">
    <a href="?view=ips&days=<?= (int)$days ?>&min=<?= (int)$minUsers ?>&local=<?= $includeLocal?1:0 ?>" class="<?= $view==='ips' && $ipView==='' ? 'active':'' ?>">📡 Shared IPs</a>
    <a href="?view=pairs&days=<?= (int)$days ?>&min_ips=<?= (int)$minSharedIP ?>&local=<?= $includeLocal?1:0 ?>" class="<?= $view==='pairs' && $pairA==='' && $pairB==='' ? 'active':'' ?>">🔗 Pairs</a>
  </div>

  <div>
    Lookback:
    <?= h_select('days', [7=>'7d',14=>'14d',30=>'30d',60=>'60d',90=>'90d',180=>'180d'], $days) ?>
    &nbsp;
    <?php if ($view === 'ips' && $ipView===''): ?>
      Min distinct users/IP: <?= h_select('min', [2=>'2',3=>'3',4=>'4',5=>'5'], $minUsers) ?>
    <?php elseif ($view === 'pairs' && $pairA==='' && $pairB===''): ?>
      Min shared IPs/pair: <?= h_select('min_ips', [1=>'1',2=>'2',3=>'3',4=>'4',5=>'5'], $minSharedIP) ?>
    <?php endif; ?>
    &nbsp;
    <label style="display:inline-flex;align-items:center;gap:.35rem;">
      <input type="checkbox" name="local" value="1" <?= $includeLocal?'checked':''; ?> onchange="this.form.submit()"> include localhost
    </label>
    &nbsp;
    <button class="btn" type="submit">Apply</button>
    <?php if ($view === 'ips' && $ipView===''): ?>
      <a class="btn" href="?view=ips&days=<?= (int)$days ?>&min=<?= (int)$minUsers ?>&local=<?= $includeLocal?1:0 ?>&export=csv">Export CSV</a>
    <?php elseif ($view === 'pairs' && $pairA==='' && $pairB===''): ?>
      <a class="btn" href="?view=pairs&days=<?= (int)$days ?>&min_ips=<?= (int)$minSharedIP ?>&local=<?= $includeLocal?1:0 ?>&export=csv">Export CSV</a>
    <?php endif; ?>
    <?php if ($ipView !== ''): ?>
      <a class="btn" href="alt-radar.php?view=ips&days=<?= (int)$days ?>&min=<?= (int)$minUsers ?>&local=<?= $includeLocal?1:0 ?>">↺ Clear IP drilldown</a>
    <?php endif; ?>
    <?php if ($pairA !== '' && $pairB !== ''): ?>
      <a class="btn" href="alt-radar.php?view=pairs&days=<?= (int)$days ?>&min_ips=<?= (int)$minSharedIP ?>&local=<?= $includeLocal?1:0 ?>">↺ Back to pairs</a>
    <?php endif; ?>
  </div>
</form>

<!-- Score legend -->
<div class="muted" style="margin:.25rem 0 .5rem;">
  <span class="score-badge score-hi">6+</span> high risk &nbsp;•&nbsp;
  <span class="score-badge score-med">4–5</span> medium &nbsp;•&nbsp;
  <span class="score-badge score-low">≤3</span> low
</div>

<div class="stack">
  <div>
    <?php if ($view === 'ips' && $ipView===''): ?>
      <h2 style="margin-top:.4rem;">Shared IPs (last <?= (int)$days; ?> days)</h2>
      <table class="list">
        <thead>
          <tr>
            <th>IP</th>
            <th>Distinct Users</th>
            <th class="nowrap">Last Seen</th>
            <th>Score</th>
            <th>Users</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$sharedRows): ?>
            <tr><td colspan="5" class="muted">No shared IPs found with these filters.</td></tr>
          <?php else: foreach ($sharedRows as $r): ?>
            <?php $sc = (int)$r['score']; $cls = score_class($sc); ?>
            <tr>
              <td class="nowrap">
                <a href="?view=ips&ip=<?= e($r['ip']) ?>&days=<?= (int)$days ?>&min=<?= (int)$minUsers ?>&local=<?= $includeLocal?1:0 ?>"><?= e($r['ip']) ?></a>
                <form method="post" style="display:inline;margin-left:.5rem">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="action" value="add_ignore">
                  <input type="hidden" name="kind" value="ip">
                  <input type="hidden" name="value" value="<?= e($r['ip']) ?>">
                  <button class="btn" title="Ignore this IP">Ignore</button>
                </form>
              </td>
              <td><?= (int)$r['user_count']; ?></td>
              <td class="nowrap"><?= e((string)$r['last_seen']); ?></td>
              <td><span class="score-badge <?= $cls ?>"><?= $sc ?></span></td>
              <td>
                <?php
                  $users = array_filter(array_map('trim', explode(',', (string)$r['users'])));
                  sort($users, SORT_NATURAL | SORT_FLAG_CASE);
                  foreach ($users as $u) {
                    $watched = isset($watchMap[$u]);
                    echo '<span class="pill">'. e($u) .'</span> ';
                    if ($watched) {
                      echo '<form method="post" style="display:inline;margin-left:.25rem">
                              <input type="hidden" name="csrf" value="'.e($CSRF).'">
                              <input type="hidden" name="action" value="del_watch">
                              <input type="hidden" name="identity" value="'.e($u).'">
                              <button class="star" title="Unwatch">★</button>
                            </form> ';
                    } else {
                      echo '<form method="post" style="display:inline;margin-left:.25rem">
                              <input type="hidden" name="csrf" value="'.e($CSRF).'">
                              <input type="hidden" name="action" value="add_watch">
                              <input type="hidden" name="identity" value="'.e($u).'">
                              <button class="star" title="Add to watchlist">☆</button>
                            </form> ';
                    }
                  }
                ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

    <?php elseif ($view === 'ips' && $ipView!==''): ?>
      <h2 style="margin-top:.4rem;">IP Drilldown — <?= e($ipView); ?></h2>
      <div class="muted" style="margin-bottom:.25rem;">Accounts that logged in from this IP</div>
      <table class="list">
        <thead><tr><th>Identity</th><th>Successes</th><th>Total Attempts</th><th>Last Seen</th><th>Watch</th></tr></thead>
        <tbody>
          <?php if (empty($drillRows)): ?>
            <tr><td colspan="5" class="muted">No successful logins recorded for this IP.</td></tr>
          <?php else: foreach ($drillRows as $r): $u=(string)$r['identity']; $watched=isset($watchMap[$u]); ?>
            <tr>
              <td><?= e($u); ?></td>
              <td><?= (int)$r['successes']; ?></td>
              <td><?= (int)$r['attempts']; ?></td>
              <td class="nowrap"><?= e((string)$r['last_seen']); ?></td>
              <td>
                <?php if ($watched): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                    <input type="hidden" name="action" value="del_watch">
                    <input type="hidden" name="identity" value="<?= e($u) ?>">
                    <button class="star" title="Unwatch">★</button>
                  </form>
                <?php else: ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                    <input type="hidden" name="action" value="add_watch">
                    <input type="hidden" name="identity" value="<?= e($u) ?>">
                    <button class="star" title="Add to watchlist">☆</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <h3 style="margin-top:1rem;">Recent Attempts from this IP (latest 200)</h3>
      <table class="list">
        <thead>
          <tr>
            <th class="nowrap">When</th>
            <th>Identity</th>
            <th>Success</th>
            <th>Note</th>
            <?php if ($hasUA): ?><th>User-Agent</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentRows)): ?>
            <tr><td colspan="<?= $hasUA?5:4 ?>" class="muted">No attempts recorded.</td></tr>
          <?php else: foreach ($recentRows as $r): ?>
            <tr>
              <td class="nowrap"><?= e((string)$r['attempted_at']); ?></td>
              <td><?= e((string)$r['identity']); ?></td>
              <td><?= ((int)$r['success']) ? '✓' : '—'; ?></td>
              <td><?= e((string)($r['note'] ?? '')); ?></td>
              <?php if ($hasUA): ?><td class="mono"><?= e((string)($r['user_agent'] ?? '')); ?></td><?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

    <?php elseif ($view === 'pairs' && $pairA==='' && $pairB===''): ?>
      <h2 style="margin-top:.4rem;">Account Pairs (last <?= (int)$days; ?> days)</h2>
      <table class="list">
        <thead>
          <tr>
            <th>Identity A</th>
            <th>Identity B</th>
            <th>Shared IPs</th>
            <th>Last Seen</th>
            <th>Score</th>
            <th>Watch</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$pairs): ?>
            <tr><td colspan="7" class="muted">No pairs found with these filters.</td></tr>
          <?php else: foreach ($pairs as $p): ?>
            <?php $sc = (int)$p['score']; $cls = score_class($sc); $a=$p['a']; $b=$p['b']; ?>
            <tr>
              <td><?= e($a); ?></td>
              <td><?= e($b); ?></td>
              <td><?= (int)$p['shared_ips']; ?></td>
              <td class="nowrap"><?= e((string)$p['last_seen']); ?></td>
              <td><span class="score-badge <?= $cls ?>"><?= $sc ?></span></td>
              <td class="nowrap">
                <?php foreach ([$a,$b] as $u): $watched = isset($watchMap[$u]); ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                    <input type="hidden" name="identity" value="<?= e($u) ?>">
                    <input type="hidden" name="action" value="<?= $watched ? 'del_watch' : 'add_watch' ?>">
                    <button class="star" title="<?= $watched ? 'Unwatch' : 'Add to watchlist' ?>"><?= $watched ? '★' : '☆' ?></button>
                  </form>
                <?php endforeach; ?>
              </td>
              <td><a class="btn" href="?view=pairs&a=<?= e(urlencode($a)) ?>&b=<?= e(urlencode($b)) ?>&days=<?= (int)$days ?>&min_ips=<?= (int)$minSharedIP ?>&local=<?= $includeLocal?1:0 ?>">Drilldown</a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

    <?php elseif ($view === 'pairs' && $pairA!=='' && $pairB!==''): ?>
      <h2 style="margin-top:.4rem;">Pair Drilldown — <?= e($pairA) ?> ↔ <?= e($pairB) ?></h2>
      <div class="muted" style="margin-bottom:.25rem;">Shared IPs these two identities both used (last <?= (int)$days; ?> days)</div>
      <table class="list">
        <thead><tr><th>IP</th><th>Last Seen (<?= e($pairA) ?>)</th><th>Last Seen (<?= e($pairB) ?>)</th></tr></thead>
        <tbody>
          <?php if (empty($pairDetails)): ?>
            <tr><td colspan="3" class="muted">No shared IPs for this pair in the selected window.</td></tr>
          <?php else: foreach ($pairDetails as $d): ?>
            <tr>
              <td class="nowrap"><?= e((string)$d['ip']); ?></td>
              <td class="nowrap"><?= e((string)$d['last_seen_a']); ?></td>
              <td class="nowrap"><?= e((string)$d['last_seen_b']); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Right column: Ignore manager + Watchlist -->
  <div>
    <h2 id="ignores" style="margin-top:.6rem;">Ignore List</h2>
    <form method="post" style="display:flex;gap:.6rem;align-items:flex-end;margin:.35rem 0 .6rem;flex-wrap:wrap;">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="add_ignore">
      <div>
        <label style="display:block;font-weight:bold;margin-bottom:.2rem;">Kind</label>
        <select name="kind">
          <option value="ip">IP</option>
          <option value="identity">Identity</option>
        </select>
      </div>
      <div style="flex:1;min-width:220px;">
        <label style="display:block;font-weight:bold;margin-bottom:.2rem;">Value</label>
        <input name="value" required style="width:100%;padding:.35rem;border:1px solid #ddd;border-radius:.35rem;">
      </div>
      <div style="flex:1;min-width:220px;">
        <label style="display:block;font-weight:bold;margin-bottom:.2rem;">Note (optional)</label>
        <input name="note" style="width:100%;padding:.35rem;border:1px solid #ddd;border-radius:.35rem;">
      </div>
      <div><button class="btn" type="submit">Add / Update</button></div>
    </form>

    <table class="list">
      <thead><tr><th>Kind</th><th>Value</th><th>Note</th><th>By</th><th>IP</th><th>When</th><th></th></tr></thead>
      <tbody>
        <?php if (!$ignores): ?>
          <tr><td colspan="7" class="muted">No ignores yet.</td></tr>
        <?php else: foreach ($ignores as $g): ?>
          <tr>
            <td><?= e((string)$g['kind']) ?></td>
            <td class="mono"><?= e((string)$g['value']) ?></td>
            <td><?= e((string)($g['note'] ?? '')) ?></td>
            <td><?= e((string)($g['created_by'] ?? '')) ?></td>
            <td class="mono"><?= e((string)($g['created_ip'] ?? '')) ?></td>
            <td class="nowrap"><?= e((string)($g['created_at'] ?? '')) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Remove this ignore?')" style="display:inline;">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                <input type="hidden" name="action" value="del_ignore">
                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                <button class="btn" type="submit">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <h2 id="watchlist" style="margin-top:1.2rem;">Watchlist</h2>
    <form method="post" style="display:flex;gap:.6rem;align-items:flex-end;margin:.35rem 0 .6rem;flex-wrap:wrap;">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="add_watch">
      <div style="flex:1;min-width:220px;">
        <label style="display:block;font-weight:bold;margin-bottom:.2rem;">Identity</label>
        <input name="identity" required placeholder="username or actor" style="width:100%;padding:.35rem;border:1px solid #ddd;border-radius:.35rem;">
      </div>
      <div style="flex:1;min-width:220px;">
        <label style="display:block;font-weight:bold;margin-bottom:.2rem;">Note (optional)</label>
        <input name="note" style="width:100%;padding:.35rem;border:1px solid #ddd;border-radius:.35rem;">
      </div>
      <div><button class="btn" type="submit">Add / Update</button></div>
    </form>

    <table class="list">
      <thead><tr><th>Identity</th><th class="nowrap">Last Seen (<?= (int)$days ?>d)</th><th>Distinct IPs</th><th>Score</th><th>Note</th><th></th></tr></thead>
      <tbody>
        <?php if (!$watchRows): ?>
          <tr><td colspan="6" class="muted">No one on watch.</td></tr>
        <?php else: foreach ($watchRows as $w): $idn=(string)$w['identity']; $ws=$watchStats[$idn] ?? ['last_seen'=>null,'ips'=>0,'score'=>0]; $cls=score_class((int)$ws['score']); ?>
          <tr>
            <td class="nowrap"><?= e($idn) ?></td>
            <td class="nowrap"><?= e((string)($ws['last_seen'] ?? '—')) ?></td>
            <td><?= (int)($ws['ips'] ?? 0) ?></td>
            <td><span class="score-badge <?= $cls ?>"><?= (int)($ws['score'] ?? 0) ?></span></td>
            <td><?= e((string)($w['note'] ?? '')) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Remove from watchlist?')" style="display:inline;">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                <input type="hidden" name="action" value="del_watch">
                <input type="hidden" name="identity" value="<?= e($idn) ?>">
                <button class="btn" type="submit">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<style>
  .focus-hit {
    outline: 2px solid #ff8a8a;
    background: #fff7f7;
    transition: background 0.6s ease-in-out;
    animation: focus-pop 1.2s ease-out 1;
  }
  @keyframes focus-pop {
    0%   { box-shadow: 0 0 0 0 rgba(255,0,0,.25); }
    50%  { box-shadow: 0 0 0 12px rgba(255,0,0,.08); }
    100% { box-shadow: 0 0 0 0 rgba(255,0,0,0); }
  }
</style>
<script>
(function () {
  const params = new URLSearchParams(location.search);
  const focus = params.get('focus');
  if (!focus) return;

  // Find the Watchlist table (first <table> after the <h2 id="watchlist">)
  const h2 = document.getElementById('watchlist');
  if (!h2) return;

  let el = h2.nextElementSibling;
  let watchTable = null;
  while (el) {
    if (el.tagName === 'TABLE' && el.classList.contains('list')) { watchTable = el; break; }
    el = el.nextElementSibling;
  }
  if (!watchTable) return;

  // Find the row whose first cell matches the identity (case-insensitive)
  const rows = watchTable.querySelectorAll('tbody tr');
  const needle = focus.trim().toLowerCase();
  let hit = null;
  rows.forEach(tr => {
    const cell = tr.querySelector('td');
    if (!cell) return;
    if (cell.textContent.trim().toLowerCase() === needle) hit = tr;
  });

  if (hit) {
    hit.classList.add('focus-hit');
    // Smooth scroll into view
    hit.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Add a tiny "Clear highlight" pill next to the Watchlist header
    const pill = document.createElement('a');
    pill.textContent = 'Clear highlight';
    pill.href = location.pathname + location.search.replace(/[?&]focus=[^&]*/i, '').replace(/([?&])$/, '');
    pill.className = 'btn';
    pill.style.marginLeft = '.5rem';
    const header = document.getElementById('watchlist');
    if (header) header.insertAdjacentElement('afterend', pill);

    // Fade the highlight after ~10s
    setTimeout(() => hit.classList.remove('focus-hit'), 10000);
  }
})();
</script>
