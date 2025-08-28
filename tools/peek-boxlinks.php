<?php
// tools/peek-boxlinks.php
// Purpose: Show (and optionally export CSV) of schedule games vs the boxscore HTML pointed to by `link`.
// Flags mismatches where UHA-#.html contains different teams than the schedule expects.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root = dirname(__DIR__);
$schedPath = $root . '/data/uploads/schedule-current.json';
$teamsPath = $root . '/data/uploads/teams.json';
$boxDir    = $root . '/data/uploads';

if (!file_exists($schedPath)) { http_response_code(500); echo "Missing $schedPath\n"; exit; }

$sched = json_decode((string) file_get_contents($schedPath), true);
$teams = file_exists($teamsPath) ? json_decode((string) file_get_contents($teamsPath), true) : ['teams'=>[]];

function abbrOfName(string $name, array $teams): string {
  $n = mb_strtolower(trim($name));
  foreach (($teams['teams'] ?? []) as $t) {
    if (mb_strtolower(trim((string)($t['name'] ?? ''))) === $n) return (string)($t['abbr'] ?? '');
  }
  return '';
}
function abbrOfId(string $id, array $teams): string {
  foreach (($teams['teams'] ?? []) as $t) {
    if ((string)($t['id'] ?? '') === (string)$id) return (string)($t['abbr'] ?? '');
  }
  return '';
}
function clean(string $s): string {
  $s = strip_tags($s);
  $s = html_entity_decode($s, ENT_QUOTES|ENT_HTML5, 'UTF-8');
  return trim(preg_replace('/\s+/', ' ', $s));
}
function parseHeaderScores(string $html): array {
  $out = ['home'=>['name'=>'','score'=>0], 'visitor'=>['name'=>'','score'=>0]];
  if (preg_match_all('/<td[^>]*class="STHSGame_GoalsTeamName"[^>]*>(.*?)<\/td>.*?<td[^>]*class="STHSGame_GoalsTotal"[^>]*>(\d+)<\/td>/si', $html, $m, PREG_SET_ORDER)) {
    if (isset($m[0])) { $out['visitor']['name'] = clean($m[0][1]); $out['visitor']['score'] = intval($m[0][2]); }
    if (isset($m[1])) { $out['home']['name']    = clean($m[1][1]); $out['home']['score']    = intval($m[1][2]); }
  }
  return $out;
}

$rows = [];
foreach (($sched['games'] ?? []) as $g) {
  $date = (string)($g['date'] ?? '');
  $link = basename((string)($g['link'] ?? ''));
  if ($link === '') continue;

  $schedVabbr = ($g['visitorTeamId'] ?? '') !== '' ? abbrOfId((string)$g['visitorTeamId'], $teams) : abbrOfName((string)($g['visitorTeam'] ?? ''), $teams);
  $schedHabbr = ($g['homeTeamId'] ?? '') !== ''    ? abbrOfId((string)$g['homeTeamId'], $teams)    : abbrOfName((string)($g['homeTeam'] ?? ''), $teams);

  $htmlPath = $boxDir . '/' . $link;
  $status = 'ok';
  $boxVabbr = $boxHabbr = '';
  if (!is_file($htmlPath)) {
    $status = 'missing_html';
  } else {
    $html = (string) file_get_contents($htmlPath);
    $hdr = parseHeaderScores($html);
    $boxVabbr = abbrOfName($hdr['visitor']['name'] ?? '', $teams);
    $boxHabbr = abbrOfName($hdr['home']['name'] ?? '', $teams);
    if ($boxVabbr === '' || $boxHabbr === '') {
      $status = 'parse_fail';
    } elseif (!($schedVabbr === $boxVabbr && $schedHabbr === $boxHabbr)) {
      $status = 'MISMATCH';
    }
  }

  $rows[] = [
    'date' => $date,
    'link' => $link,
    'sched' => "{$schedVabbr}@{$schedHabbr}",
    'box' => ($boxVabbr!==''||$boxHabbr!=='') ? "{$boxVabbr}@{$boxHabbr}" : '',
    'status' => $status
  ];
}

// allow ?csv=1 to download a CSV
if (isset($_GET['csv']) && $_GET['csv'] === '1') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=peek-boxlinks.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, array_keys($rows[0] ?? ['date','link','sched','box','status']));
  foreach ($rows as $r) fputcsv($out, $r);
  fclose($out);
  exit;
}

// simple HTML table
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Peek Box Links</title>
<style>
body { font-family: system-ui, Arial, sans-serif; padding: 16px; background: #0e0f13; color: #e9edf1; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px 10px; border-bottom: 1px solid #2a2e36; font-size: 14px; }
tr:nth-child(even){ background: #151821; }
.bad { color: #ff7b7b; font-weight: 700; }
.warn { color: #ffd166; font-weight: 700; }
small { color: #8aa0b6; }
a.btn { display: inline-block; padding: 6px 10px; margin-bottom: 12px; border: 1px solid #2a2e36; border-radius: 6px; color: #e9edf1; text-decoration: none; }
a.btn:hover{ background: #1b1f29; }
</style>
</head>
<body>
  <h1>Peek: schedule links vs boxscore HTML</h1>
  <p><a class="btn" href="?csv=1">Download CSV</a> <small>Place this in <code>/tools/</code> and visit it in a browser, or run via PHP’s built-in server.</small></p>
  <table>
    <thead><tr><th>Date</th><th>Link</th><th>Schedule</th><th>Box HTML</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): 
        $cls = ($r['status']==='MISMATCH') ? 'bad' : (($r['status']!=='ok') ? 'warn' : '');
      ?>
      <tr>
        <td><?= htmlspecialchars($r['date']) ?></td>
        <td><?= htmlspecialchars($r['link']) ?></td>
        <td><?= htmlspecialchars($r['sched']) ?></td>
        <td><?= htmlspecialchars($r['box']) ?></td>
        <td class="<?= $cls ?>"><?= htmlspecialchars($r['status']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
