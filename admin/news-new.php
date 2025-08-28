<?php
// admin/news-new.php — create/edit story (simple WYSIWYG baseline)
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session-guard.php';
require_admin();

$dbc = null;
if (isset($db) && $db instanceof mysqli)             { $dbc = $db; }
elseif (isset($conn) && $conn instanceof mysqli)     { $dbc = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbc = $mysqli; }
if (!$dbc) { die('Database not available'); }

$TEAM_CODES = [
  ''   => '— None / League —',
  'ANA'=> 'Ducks',     'ARI'=> 'Coyotes',   'BOS'=> 'Bruins',   'BUF'=> 'Sabres',
  'CGY'=> 'Flames',    'CAR'=> 'Hurricanes','CHI'=> 'Blackhawks','COL'=> 'Avalanche',
  'CBJ'=> 'Blue Jackets','DAL'=> 'Stars',   'DET'=> 'Red Wings','EDM'=> 'Oilers',
  'FLA'=> 'Panthers',  'LAK'=> 'Kings',    'MIN'=> 'Wild',     'MTL'=> 'Canadiens',
  'NSH'=> 'Predators', 'NJD'=> 'Devils',   'NYI'=> 'Islanders','NYR'=> 'Rangers',
  'OTT'=> 'Senators',  'PHI'=> 'Flyers',   'PIT'=> 'Penguins', 'SEA'=> 'Kraken',
  'SJS'=> 'Sharks',    'STL'=> 'Blues',    'TBL'=> 'Lightning','TOR'=> 'Maple Leafs',
  'VAN'=> 'Canucks',   'VGK'=> 'Golden Knights','WPG'=> 'Jets','WSH'=> 'Capitals',
];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$story = ['title'=>'','summary'=>'','body'=>'','hero_image_url'=>'','team_code'=>'','status'=>'published'];

if ($id>0) {
  $stmt = $dbc->prepare("SELECT id,title,summary,body,hero_image_url,team_code,status FROM stories WHERE id=?");
  $stmt->bind_param('i',$id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows) $story = $res->fetch_assoc();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $title = trim($_POST['title'] ?? '');
  $summary = trim($_POST['summary'] ?? '');
  $body = $_POST['body'] ?? '';
  $hero = trim($_POST['hero_image_url'] ?? '');
  $team = strtoupper(trim($_POST['team_code'] ?? ''));
  $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft':'published';

  if ($title==='')   $errors[]='Title is required';
  if ($summary==='') $errors[]='Summary is required';
  if ($team !== '' && !isset($TEAM_CODES[$team])) $errors[]='Unknown team code';

  if (!$errors) {
    if ($id>0) {
      $stmt = $dbc->prepare("UPDATE stories
                             SET title=?, summary=?, body=?, hero_image_url=?, team_code=?, status=?, updated_at=NOW()
                             WHERE id=?");
      $stmt->bind_param('ssssssi',$title,$summary,$body,$hero,$team,$status,$id);
      $stmt->execute();
    } else {
      $stmt = $dbc->prepare("INSERT INTO stories (title, summary, body, hero_image_url, team_code, status)
                             VALUES (?,?,?,?,?,?)");
      $stmt->bind_param('ssssss',$title,$summary,$body,$hero,$team,$status);
      $stmt->execute();
      $id = $dbc->insert_id;
    }
    header("Location: /admin/news.php");
    exit;
  } else {
    $story = compact('title','summary','body','hero','team');
    $story['hero_image_url']=$hero;
    $story['team_code']=$team;
    $story['status']=$status;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1280, initial-scale=1"><title><?= $id? 'Edit':'New' ?> Article — Admin</title>
  <link rel="stylesheet" href="../../assets/css/nav.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/hotfix-portal.css">
  <style>
    .form{display:grid;gap:8px;max-width:900px}
    label b{display:block;margin-bottom:4px}
    input[type=text], textarea, select{width:100%;padding:8px;border:1px solid #cfd6e4;border-radius:8px}
    textarea{min-height:180px}
    .toolbar{display:flex;gap:8px;margin:6px 0}
    .btn{display:inline-flex;padding:6px 10px;border:1px solid #cfd6e4;border-radius:8px;text-decoration:none;color:#0b1220;background:#fff}
    .wys{border:1px solid #cfd6e4;border-radius:8px;padding:8px;min-height:220px;background:#fff}
    .help{font-size:12px;color:#6c7a93}
  </style>
  <script>
    function cmd(n){ document.execCommand(n,false,null); }
    function initWYS(){
      const area = document.getElementById('body');
      const div = document.createElement('div');
      div.className = 'wys';
      div.contentEditable = 'true';
      div.innerHTML = area.value;
      area.style.display='none';
      area.parentNode.insertBefore(div, area);
      document.getElementById('form').addEventListener('submit', ()=>{ area.value = div.innerHTML; });
      window._wys = div;
    }
    window.addEventListener('DOMContentLoaded', initWYS);
  </script>
</head>
<body>
<div class="site">
  <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
  <main class="content">
    <section class="content-col">
      <div class="section-title"><span><?= $id? 'Edit':'New' ?> Article</span></div>
      <?php if ($errors): ?>
        <div style="background:#ffe3e3;border:1px solid #ffb3b3;border-radius:8px;padding:8px;margin-bottom:8px">
          <b>Fix the following:</b>
          <ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
        </div>
      <?php endif; ?>
      <form id="form" class="form" method="post">
        <label><b>Title *</b>
          <input type="text" name="title" value="<?= htmlspecialchars($story['title'] ?? '') ?>">
        </label>
        <label><b>Summary (teaser) *</b>
          <input type="text" name="summary" value="<?= htmlspecialchars($story['summary'] ?? '') ?>">
        </label>
        <label><b>Hero Image URL</b>
          <input type="text" name="hero_image_url" value="<?= htmlspecialchars($story['hero_image_url'] ?? '') ?>">
          <div class="help">Optional. If empty, we’ll show a team logo placeholder on public pages.</div>
        </label>
        <label><b>Team (for placeholder)</b>
          <select name="team_code">
            <?php foreach ($TEAM_CODES as $code=>$name): ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= ($story['team_code']??'')===$code?'selected':'' ?>>
                <?= htmlspecialchars($name . ($code? " ($code)":"")) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="help">If no image URL is provided, we’ll use /assets/logos/TEAM.png (e.g., TOR.png). Fallback: /assets/img/news-placeholder.png</div>
        </label>
        <div class="toolbar">
          <button type="button" class="btn" onclick="cmd('bold')"><b>B</b></button>
          <button type="button" class="btn" onclick="cmd('italic')"><i>I</i></button>
          <button type="button" class="btn" onclick="cmd('underline')"><u>U</u></button>
          <button type="button" class="btn" onclick="cmd('insertUnorderedList')">• List</button>
          <button type="button" class="btn" onclick="cmd('formatBlock','H2')">H2</button>
        </div>
        <label><b>Body</b>
          <textarea id="body" name="body"><?= $story['body'] ?? '' ?></textarea>
          <div class="help">Basic formatting supported. (We can swap in TinyMCE/TipTap later.)</div>
        </label>
        <label><b>Status</b>
          <select name="status">
            <option value="published" <?= ($story['status'] ?? 'published')==='published'?'selected':'' ?>>Published</option>
            <option value="draft" <?= ($story['status'] ?? '')==='draft'?'selected':'' ?>>Draft</option>
          </select>
        </label>
        <div>
          <button class="btn" type="submit">Save</button>
          <a class="btn" href="/admin/news.php">Back</a>
        </div>
      </form>
    </section>
  </main>
</div>
</body>
</html>
