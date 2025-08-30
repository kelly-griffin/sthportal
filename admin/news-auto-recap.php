<?php
// admin/news-auto-recap.php — paste STHS play-by-play → generate + save auto recap
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
if (!$dbc) { die('Database not available'); }

$TEAM_CODES = [
  'ANA'=>'Ducks','ARI'=>'Coyotes','BOS'=>'Bruins','BUF'=>'Sabres','CGY'=>'Flames','CAR'=>'Hurricanes',
  'CHI'=>'Blackhawks','COL'=>'Avalanche','CBJ'=>'Blue Jackets','DAL'=>'Stars','DET'=>'Red Wings','EDM'=>'Oilers',
  'FLA'=>'Panthers','LAK'=>'Kings','MIN'=>'Wild','MTL'=>'Canadiens','NSH'=>'Predators','NJD'=>'Devils',
  'NYI'=>'Islanders','NYR'=>'Rangers','OTT'=>'Senators','PHI'=>'Flyers','PIT'=>'Penguins','SEA'=>'Kraken',
  'SJS'=>'Sharks','STL'=>'Blues','TBL'=>'Lightning','TOR'=>'Maple Leafs','VAN'=>'Canucks','VGK'=>'Golden Knights',
  'WPG'=>'Jets','WSH'=>'Capitals'
];

$ALIASES = [
  'TOR'=>'Toronto Maple Leafs','TBL'=>'Tampa Bay Lightning','VGK'=>'Vegas Golden Knights','MTL'=>'Montreal Canadiens',
  'BOS'=>'Boston Bruins','NYR'=>'New York Rangers','NYI'=>'New York Islanders','WSH'=>'Washington Capitals','OTT'=>'Ottawa Senators',
  'PHI'=>'Philadelphia Flyers','PIT'=>'Pittsburgh Penguins','BUF'=>'Buffalo Sabres','DET'=>'Detroit Red Wings',
  'CBJ'=>'Columbus Blue Jackets','CHI'=>'Chicago Blackhawks','NSH'=>'Nashville Predators','CAR'=>'Carolina Hurricanes',
  'FLA'=>'Florida Panthers','MIN'=>'Minnesota Wild','WPG'=>'Winnipeg Jets','DAL'=>'Dallas Stars',
  'COL'=>'Colorado Avalanche','EDM'=>'Edmonton Oilers','CGY'=>'Calgary Flames','VAN'=>'Vancouver Canucks',
  'SEA'=>'Seattle Kraken','LAK'=>'Los Angeles Kings','SJS'=>'San Jose Sharks','ARI'=>'Arizona Coyotes','ANA'=>'Anaheim Ducks',
];

function detectTeamCode(?string $text, array $ALIASES): ?string {
    if ($text === null || $text === '') return null;
  $t = mb_strtolower($text);
  foreach ($ALIASES as $code => $long) {
    $needle = mb_strtolower($long);
    if (strpos($t, $needle) !== false) return $code;
  }
  if (preg_match('/\b([A-Z]{3})\b/', strtoupper($text), $m)) return $m[1];
  return null;
}

function parse_pbp(string $pbp, array $ALIASES): array {
  $plain = preg_replace('/<[^>]+>/', ' ', $pbp);
  $plain = preg_replace('/\s+/', ' ', $plain);
  $out = [
    'homeName'=>null,'awayName'=>null,'homeScore'=>null,'awayScore'=>null,
    'winner'=>null,'loser'=>null,'ot'=>false,'so'=>false,
    'star1'=>null,'star2'=>null,'star3'=>null,
  ];
  if (preg_match('/\bOT\b|\bovertime\b/i', $plain)) $out['ot'] = true;
  if (preg_match('/\bSO\b|\bshootout\b/i', $plain)) $out['so'] = true;
  if (preg_match('/Final[^:]*:\s*([A-Za-z .\-]+?)\s+(\d+)\s*[-–]\s*(\d+)\s+([A-Za-z .\-]+?)(?:\.|$)/i', $plain, $m)) {
    $out['homeName'] = trim($m[1]);
    $out['homeScore'] = (int)$m[2];
    $out['awayScore'] = (int)$m[3];
    $out['awayName'] = trim($m[4]);
  } else if (preg_match('/([A-Za-z .\-]+?)\s+(\d+)\s+(?:defeat|defeats|def\.?|beat|edge|edges|tops|blank|rout|over)\s+([A-Za-z .\-]+?)\s+(\d+)/i', $plain, $m)) {
    $a = trim($m[1]); $as = (int)$m[2]; $b = trim($m[3]); $bs = (int)$m[4];
    $out['homeName'] = $a; $out['homeScore'] = $as;
    $out['awayName'] = $b; $out['awayScore'] = $bs;
  } else if (preg_match('/([A-Za-z .\-]+?)\s+(\d+)\s*[-–]\s*(\d+)\s+([A-Za-z .\-]+?)(?:\.|$)/', $plain, $m)) {
    $out['homeName'] = trim($m[1]);
    $out['homeScore'] = (int)$m[2];
    $out['awayScore'] = (int)$m[3];
    $out['awayName'] = trim($m[4]);
  }
  if (preg_match_all("/(?:1st|First|1)\\s*Star[:\-]?\\s*([A-Za-z' .\-]+)(?:\\s*\\(([A-Z]{3})\\))?/i", $pbp, $s1) && !empty($s1[1])) {
    $out['star1'] = trim($s1[1][0]);
  }
  if (preg_match_all("/(?:2nd|Second|2)\\s*Star[:\-]?\\s*([A-Za-z' .\-]+)(?:\\s*\\(([A-Z]{3})\\))?/i", $pbp, $s2) && !empty($s2[1])) {
    $out['star2'] = trim($s2[1][0]);
  }
  if (preg_match_all("/(?:3rd|Third|3)\\s*Star[:\-]?\\s*([A-Za-z' .\-]+)(?:\\s*\\(([A-Z]{3})\\))?/i", $pbp, $s3) && !empty($s3[1])) {
    $out['star3'] = trim($s3[1][0]);
  }
  if (!$out['star1'] && preg_match('/Three\s+Stars.*?(?:\r?\n|\r)(.*?)(?:\r?\n|\r)(.*?)(?:\r?\n|\r)(.*?)(?:\r?\n|\r)/is', $pbp, $ts)) {
    $strip = function($s){ return trim(preg_replace('/^\s*\d+\s*[-.:]\s*/','',$s)); };
    $out['star1'] = $strip($ts[1]); $out['star2'] = $strip($ts[2]); $out['star3'] = $strip($ts[3]);
  }
  if ($out['homeName'] && $out['awayName'] && is_numeric($out['homeScore']) && is_numeric($out['awayScore'])) {
    if ($out['homeScore'] > $out['awayScore']) { $out['winner'] = $out['homeName']; $out['loser'] = $out['awayName']; }
    elseif ($out['awayScore'] > $out['homeScore']) { $out['winner'] = $out['awayName']; $out['loser'] = $out['homeName']; }
  }
  $out['homeCode'] = (isset($out['homeName']) && is_string($out['homeName']) && $out['homeName']!=='') ? detectTeamCode($out['homeName'], $ALIASES) : null;
  $out['awayCode'] = (isset($out['awayName']) && is_string($out['awayName']) && $out['awayName']!=='') ? detectTeamCode($out['awayName'], $ALIASES) : null;
  $out['winnerCode'] = (isset($out['winner']) && is_string($out['winner']) && $out['winner']!=='') ? detectTeamCode($out['winner'], $ALIASES) : null;
  return $out;
}

function verb_for_margin(int $diff): string {
  if ($diff >= 4) return 'rout';
  if ($diff === 3) return 'roll past';
  if ($diff === 2) return 'top';
  if ($diff === 1) return 'edge';
  return 'defeat';
}

function make_title(array $p): string {
  if (!$p['winner'] || $p['homeScore']===null || $p['awayScore']===null) return 'Game Recap';
  $a = (int)$p['homeScore']; $b = (int)$p['awayScore'];
  $w = $p['winner']; $l = $p['loser'] ?? '';
  $hi = max($a,$b); $lo = min($a,$b);
  $dash = "–"; // U+2013 en dash
  if ($p['so']) return $w . ' beat ' . $l . ' ' . $hi . $dash . $lo . ' in a shootout';
  if ($p['ot']) return $w . ' edge ' . $l . ' ' . $hi . $dash . $lo . ' in OT';
  $verb = verb_for_margin($hi-$lo);
  return $w . ' ' . $verb . ' ' . $l . ' ' . $hi . $dash . $lo;
}

function make_summary(array $p): string {
  if (!$p['winner']) return 'Automated recap.';
  $bits = [];
  $bits[] = $p['so'] ? 'Shootout win' : ($p['ot'] ? 'Overtime win' : 'Regulation win');
  if ($p['star1']) $bits[] = "1st Star: {$p['star1']}";
  if ($p['star2']) $bits[] = "2nd: {$p['star2']}";
  if ($p['star3']) $bits[] = "3rd: {$p['star3']}";
  return implode(' • ', $bits);
}

function make_body_html(array $p): string {
  $lines = [];
  if ($p['winner']) {
    $titleLine = htmlspecialchars(make_title($p), ENT_QUOTES, 'UTF-8');
    $lines[] = "<p><strong>$titleLine</strong></p>";
  }
  if ($p['star1'] || $p['star2'] || $p['star3']) {
    $lines[] = "<p><em>Three Stars:</em> " .
      htmlspecialchars($p['star1'] ?? '—', ENT_QUOTES, 'UTF-8') . " / " .
      htmlspecialchars($p['star2'] ?? '—', ENT_QUOTES, 'UTF-8') . " / " .
      htmlspecialchars($p['star3'] ?? '—', ENT_QUOTES, 'UTF-8') . "</p>";
  }
  return implode("\n", $lines);
}

$mode = $_POST['mode'] ?? 'form';
$pbp  = trim($_POST['pbp'] ?? '');
$title = '';
$summary = '';
$body = '';
$team_code = '';
$parsed = [];

if ($mode === 'preview' && $pbp !== '') {
  $parsed = parse_pbp($pbp, $ALIASES);
  $title   = make_title($parsed);
  $summary = make_summary($parsed);
  $body    = make_body_html($parsed);
  $team_code = $parsed['winnerCode'] ?? ($parsed['homeCode'] ?? ($parsed['awayCode'] ?? ''));
}

if ($mode === 'save') {
  $title   = trim($_POST['title'] ?? '');
  $summary = trim($_POST['summary'] ?? '');
  $body    = $_POST['body'] ?? '';
  $team_code = strtoupper(trim($_POST['team_code'] ?? ''));
  if ($title === '' || $summary === '') {
    $mode = 'form';
    $err = 'Title and Summary are required.';
  } else {
    $stmt = $dbc->prepare("INSERT INTO stories
      (title, summary, body, hero_image_url, team_code, status, is_auto, source_type, source_id, published_at)
      VALUES (?,?,?,?,?,'published',1,'game',NULL,NOW())");
    $hero = '';
    $stmt->bind_param('sssss', $title, $summary, $body, $hero, $team_code);
    $stmt->execute();
    header("Location: /admin/news.php");
    exit;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1280, initial-scale=1"><title>Auto Recap — Admin</title>
  <link rel="stylesheet" href="../../assets/css/nav.css">
  <style>
    .form{display:grid;gap:10px;max-width:1000px}
    textarea{width:100%;min-height:220px;padding:8px;border:1px solid #cfd6e4;border-radius:8px}
    input[type=text], select{width:100%;padding:8px;border:1px solid #cfd6e4;border-radius:8px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .btn{display:inline-flex;padding:6px 10px;border:1px solid #cfd6e4;border-radius:8px;text-decoration:none;color:#0b1220;background:#fff}
    .preview{background:#fff;border:1px solid #cfd6e4;border-radius:10px;padding:12px}
    .help{color:#6c7a93;font-size:12px}
  </style>
</head>
<body>
<div class="site">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
  <main class="content">
    <section class="content-col">
      <div class="section-title"><span>Auto Game Recap (Paste STHS PBP)</span></div>

      <?php if (!empty($err)): ?>
        <div style="background:#ffe3e3;border:1px solid #ffb3b3;border-radius:8px;padding:8px;margin-bottom:8px">
          <?= htmlspecialchars($err) ?>
        </div>
      <?php endif; ?>

      <form class="form" method="post">
        <label><b>Paste STHS Play-by-Play or Game Summary *</b>
          <textarea name="pbp" placeholder="Paste the PBP or game summary here..."><?= htmlspecialchars($pbp) ?></textarea>
          <div class="help">We’ll parse the winner, score, OT/SO, and Three Stars if present. You can still edit the text below.</div>
        </label>
        <div>
          <button class="btn" name="mode" value="preview">Preview Recap</button>
          <a class="btn" href="/admin/news.php" style="margin-left:6px">Back</a>
        </div>

        <?php if ($mode === 'preview'): ?>
          <div class="section-title" style="margin-top:10px"><span>Preview</span></div>
          <div class="preview">
            <div class="row">
              <label><b>Title *</b>
                <input type="text" name="title" value="<?= htmlspecialchars($title) ?>">
              </label>
              <label><b>Team (for placeholder image)</b>
                <select name="team_code">
                  <option value="">— None / League —</option>
                  <?php foreach ($TEAM_CODES as $code=>$name): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= ($team_code===$code?'selected':'') ?>>
                      <?= htmlspecialchars($name) ?> (<?= htmlspecialchars($code) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <label><b>Summary *</b>
              <input type="text" name="summary" value="<?= htmlspecialchars($summary) ?>">
            </label>
            <label><b>Body</b>
              <textarea name="body"><?= $body ?></textarea>
            </label>
            <div>
              <button class="btn" name="mode" value="save">Save Article</button>
            </div>
          </div>
        <?php endif; ?>
      </form>
    </section>
  </main>
</div>
</body>
</html
