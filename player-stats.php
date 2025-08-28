<?php
// /sthportal/player-stats.php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

/* ------- Read query params ------- */
$view  = strtolower((string)($_GET['view'] ?? 'skaters'));     // skaters|defense|goalies|rookies
$scope = strtolower((string)($_GET['scope'] ?? 'pro'));        // pro|farm|echl|juniors
$split = strtolower((string)($_GET['split'] ?? 'season'));     // season|playoffs

$allowedViews  = ['skaters','defense','goalies','rookies'];
$allowedScopes = ['pro','farm','echl','juniors'];
$allowedSplits = ['season','playoffs'];

if (!in_array($view, $allowedViews, true))   $view = 'skaters';
if (!in_array($scope, $allowedScopes, true)) $scope = 'pro';
if (!in_array($split, $allowedSplits, true)) $split = 'season';

/* Default stat per view */
$defaultStat = [
  'skaters' => 'PTS',
  'defense' => 'PTS',
  'goalies' => 'GAA',
  'rookies' => 'PTS',
];
$stat = strtoupper((string)($_GET['stat'] ?? $defaultStat[$view]));
$validStats = [
  'skaters' => ['PTS','G','A'],
  'defense' => ['PTS','G','A'],
  'goalies' => ['GAA','SV%','SO'],
  'rookies' => ['PTS','G','A'],
];
if (!in_array($stat, $validStats[$view], true)) $stat = $defaultStat[$view];

/* ------- Page title ------- */
$title = "Player Statistics — " . ucfirst($view) . " — " . strtoupper($stat);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="assets/css/nav.css">
  <link rel="stylesheet" href="assets/css/stats.css">
</head>
<body>
  <div class="site">
    <!-- Portal Header + Nav (same structure as home.php so styling stays consistent) -->
    <header class="portal-header">
      <div class="portal-top">
        <div class="brand">
          <div class="logo" title="Portal Logo"></div>
          <div class="title" id="portal-title">UHA Hockey Portal</div>
        </div>
        <nav class="main-nav nav-wrap" aria-label="Primary">
          <div class="nav-item"><a class="nav-btn" href="home.php">Home</a></div>

          <div class="nav-item">
            <a class="nav-btn" href="#">Teams</a>
            <div class="dropdown">
              <a href="#">My Team Dashboard</a>
              <a href="#">All Teams</a>
              <a href="#">Depth Charts</a>
              <a href="#">Team Stats</a>
              <a href="#">Roster Moves</a>
              <a href="#">Lines Upload</a>
              <a href="#">Team History &amp; Records</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="#">Leagues ▾</a>
            <div class="dropdown">
              <a href="?league=uha">UHA (Main)</a>
              <a href="?league=farm">Farm League (AHL)</a>
              <a href="?league=echl">ECHL (Optional)</a>
              <a href="?league=intl">International (Optional)</a>
              <a href="?league=juniors">Junior Leagues (Optional)</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="#">Players</a>
            <div class="dropdown">
              <a href="player-stats.php?view=skaters&stat=PTS">All Players</a>
              <a href="#">Free Agents</a>
              <a href="#">Waiver Wire</a>
              <a href="player-stats.php?view=rookies&stat=PTS">Prospect / Rookie Leaders</a>
              <a href="player-stats.php?view=goalies&stat=GAA">Goalie Leaders</a>
              <a href="#">Compare Players</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="#">Transactions</a>
            <div class="dropdown">
              <a href="#">Trade Center</a>
              <a href="#">Signing Center</a>
              <a href="#">Waivers</a>
              <a href="#">Buyouts</a>
              <a href="#">All Transactions Log</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="#">Stats ▾</a>
            <div class="dropdown">
              <a href="player-stats.php?view=skaters&stat=PTS">Player Statistics</a>
              <a href="#">Team Statistics</a>
              <a href="#">Milestones</a>
              <a href="#">Special Teams</a>
              <a href="#">Attendance &amp; Financials</a>
              <a href="#">Salary Cap Tracker</a>
            </div>
          </div>

          <div class="nav-item">
            <a class="nav-btn" href="#">Front Office</a>
            <div class="dropdown">
              <a href="#">Roster Management</a>
              <a href="#">Lines &amp; Strategy</a>
              <a href="#">Personnel Changes</a>
              <a href="#">Financial Management</a>
              <a href="#">Scouting Assignments</a>
              <a href="#">Cap Management Tools</a>
            </div>
          </div>

          <div class="nav-item"><a class="nav-btn" href="#">Draft</a></div>
          <div class="nav-item"><a class="nav-btn" href="#">Tournaments</a></div>
          <div class="nav-item"><a class="nav-btn" href="#">Media</a></div>

          <div class="nav-item">
            <a class="nav-btn" href="options.php">Options</a>
            <div class="dropdown">
              <a href="download.php?what=league">Download Latest League File</a>
              <a href="options.php"><strong>Full Options Page</strong></a>
            </div>
          </div>

          <div class="nav-item"><a class="nav-btn" href="#">Admin</a></div>
        </nav>
        <div class="profile"><a class="btn" href="#">Login</a></div>
      </div>
    </header>

    <!-- Context header -->
    <div class="context-header">
      <div class="context-inner">
        <div class="context-logo" aria-hidden="true"></div>
        <div class="context-titles">
          <div class="kicker">UHA League</div>
          <div class="h1">Player Statistics</div>
          <div class="subnav">
            <a class="pill active" href="player-stats.php?view=<?= urlencode($view) ?>&stat=<?= urlencode($stat) ?>&scope=<?= urlencode($scope) ?>&split=<?= urlencode($split) ?>">Overview</a>
            <a class="pill" href="home.php">Back to Home</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters & Table -->
    <main class="stats-main">
      <div class="filters">
        <!-- View switch -->
        <nav class="tabs" aria-label="Stat group">
          <?php
          $views = [
            'skaters' => 'Skaters',
            'defense' => 'Defensemen',
            'goalies' => 'Goalies',
            'rookies' => 'Rookies'
          ];
          foreach ($views as $k => $label):
            $cls = ($k === $view) ? 'tab active' : 'tab';
            $default = $defaultStat[$k];
            $href = "player-stats.php?view={$k}&stat={$default}&scope={$scope}&split={$split}";
          ?>
            <a class="<?= $cls ?>" href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
          <?php endforeach; ?>
        </nav>

        <!-- Stat tabs (change within current view) -->
        <nav class="tabs secondary" aria-label="Stat metric">
          <?php foreach ($validStats[$view] as $s):
            $cls = ($s === $stat) ? 'tab active' : 'tab';
            $href = "player-stats.php?view={$view}&stat={$s}&scope={$scope}&split={$split}";
          ?>
            <a class="<?= $cls ?>" href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($s) ?></a>
          <?php endforeach; ?>
        </nav>

        <!-- Scope/Split selects -->
        <div class="selects">
          <form method="get" class="inline">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <input type="hidden" name="stat" value="<?= htmlspecialchars($stat) ?>">
            <label>Scope
              <select name="scope" onchange="this.form.submit()">
                <?php foreach ($allowedScopes as $sc): ?>
                  <option value="<?= $sc ?>" <?= $scope===$sc?'selected':'' ?>><?= ucfirst($sc) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Split
              <select name="split" onchange="this.form.submit()">
                <?php foreach ($allowedSplits as $sp): ?>
                  <option value="<?= $sp ?>" <?= $split===$sp?'selected':'' ?>><?= ucfirst($sp) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </form>

          <div class="right-actions">
            <input id="search" class="search" type="search" placeholder="Search player/team…">
            <button type="button" id="exportCsv" class="btn small">Export CSV</button>
          </div>
        </div>
      </div>

      <section class="table-card">
        <header class="table-head">
          <h2><?= htmlspecialchars($views[$view]) ?> — <?= htmlspecialchars($stat) ?></h2>
          <small class="muted">Sorted by <?= htmlspecialchars($stat) ?> (<?= $view==='goalies' && $stat==='GAA' ? 'lowest first' : 'highest first' ?>)</small>
        </header>

        <div class="table-wrap">
          <table id="statsTable">
            <thead>
              <tr>
                <th data-key="rank" class="num">#</th>
                <th data-key="name">Player</th>
                <th data-key="team">Team</th>
                <th data-key="val"  class="num"><?= htmlspecialchars($stat) ?></th>
              </tr>
            </thead>
            <tbody><!-- rows injected by JS --></tbody>
          </table>
        </div>
      </section>
    </main>

    <footer class="footer">© Your League • Player Statistics</footer>
  </div>

  <!-- Config + Data (until DB is wired, we feed JS here) -->
  <script>
    // Page context
    window.UHA = window.UHA || {};
    UHA.title = "Your League Name";
    UHA.context = "league-uha";

    // Which group/stat/scope/split (from PHP)
    UHA.ps = {
      view:  "<?= $view ?>",
      stat:  "<?= $stat ?>",
      scope: "<?= $scope ?>",
      split: "<?= $split ?>",
    };

    // Dummy data (replace with DB-backed arrays later). Shapes match home.js.
    // You can remove these when you populate UHA.statsDataByScope from PHP.
    UHA.statsDataByScope = {
      pro: {
        season: {
          skaters: {
            'PTS':[{name:'Nikita Kucherov',team:'TBL',val:121},{name:'Nathan MacKinnon',team:'COL',val:116},{name:'Leon Draisaitl',team:'EDM',val:106},{name:'David Pastrnak',team:'BOS',val:106},{name:'Mitchell Marner',team:'TOR',val:102},{name:'Connor McDavid',team:'EDM',val:100},{name:'Kyle Connor',team:'WPG',val:97},{name:'Jack Eichel',team:'VGK',val:94},{name:'Cale Makar',team:'COL',val:92},{name:'Sidney Crosby',team:'PIT',val:91}],
            'G'  :[{name:'Auston Matthews',team:'TOR',val:69},{name:'Sam Reinhart',team:'FLA',val:57},{name:'Zach Hyman',team:'EDM',val:54}],
            'A'  :[{name:'Connor McDavid',team:'EDM',val:100},{name:'Nikita Kucherov',team:'TBL',val:92},{name:'Nathan MacKinnon',team:'COL',val:80}]
          },
          defense: {
            'PTS':[{name:'Cale Makar',team:'COL',val:90},{name:'Quinn Hughes',team:'VAN',val:88},{name:'Adam Fox',team:'NYR',val:76}],
            'G'  :[{name:'Roman Josi',team:'NSH',val:23},{name:'Evan Bouchard',team:'EDM',val:21},{name:'Brent Burns',team:'CAR',val:19}],
            'A'  :[{name:'Quinn Hughes',team:'VAN',val:75},{name:'Cale Makar',team:'COL',val:67},{name:'Adam Fox',team:'NYR',val:63}]
          },
          goalies: {
            'GAA':[ {name:'Igor Shesterkin',team:'NYR',val:2.05},{name:'Connor Hellebuyck',team:'WPG',val:2.11},{name:'Jake Oettinger',team:'DAL',val:2.18} ],
            'SV%':[ {name:'Connor Hellebuyck',team:'WPG',val:0.928},{name:'Igor Shesterkin',team:'NYR',val:0.926},{name:'Sergei Bobrovsky',team:'FLA',val:0.924} ],
            'SO' :[ {name:'Ilya Sorokin',team:'NYI',val:7},{name:'Thatcher Demko',team:'VAN',val:6},{name:'Juuse Saros',team:'NSH',val:5} ]
          },
          rookies: {
            'PTS':[ {name:'Rook A',team:'DET',val:59},{name:'Rook B',team:'MTL',val:54},{name:'Rook C',team:'CHI',val:51} ],
            'G'  :[ {name:'Rook A',team:'DET',val:28},{name:'Rook D',team:'ARI',val:24},{name:'Rook E',team:'SJS',val:22} ],
            'A'  :[ {name:'Rook B',team:'MTL',val:36},{name:'Rook C',team:'CHI',val:33},{name:'Rook A',team:'DET',val:31} ]
          }
        }
      }
    };
  </script>

  <!-- Behaviors -->
  <script src="assets/js/nav.js"></script>
  <script>
  // Build the statistics table from UHA.ps + UHA.statsDataByScope or UHA.statsData
  (function(){
    const ps = (window.UHA && UHA.ps) || {view:'skaters',stat:'PTS',scope:'pro',split:'season'};

    const DECIMALS = { 'SV%':3, 'GAA':2 };
    const $ = s => document.querySelector(s);
    const tbody = document.querySelector('#statsTable tbody');
    const search = $('#search');
    const exportBtn = $('#exportCsv');

    function getSet(){
      // prefers scope/split structure, else flat statsData
      if (UHA.statsDataByScope) {
        const r = (UHA.statsDataByScope[ps.scope] || {});
        const s = r[ps.split] || r; // allow missing split level
        return (((s[ps.view] || {})[ps.stat]) || []).slice();
      }
      if (UHA.statsData) {
        return (((UHA.statsData[ps.view] || {})[ps.stat]) || []).slice();
      }
      return [];
    }

    function fmt(v){
      if (v == null) return '';
      if (typeof v !== 'number') return v;
      const d = DECIMALS[ps.stat];
      return Number.isInteger(v) || d == null ? String(v) : v.toFixed(d);
    }

    // default order: GAA asc, everything else desc
    function defaultSort(a,b){
      const asc = (ps.view === 'goalies' && ps.stat === 'GAA');
      const va = a.val, vb = b.val;
      if (va === vb) return 0;
      if (asc) return (va < vb) ? -1 : 1;
      return (va > vb) ? -1 : 1;
    }

    // tie-aware ranking
    function computeRanks(rows){
      let prev = null, rank = 0;
      return rows.map((row,i)=>{
        const v = row.val;
        const tie = (prev !== null) && (typeof v === 'number' && typeof prev === 'number'
                     ? Math.abs(v - prev) < 1e-9 : v === prev);
        if (!tie) rank = i+1;
        prev = v;
        return Object.assign({}, row, { _rank: tie ? `T${rank}.` : `${rank}.` });
      });
    }

    function render(rows){
      tbody.innerHTML = '';
      rows.forEach(r=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="num">${r._rank}</td>
          <td>${r.name || ''}</td>
          <td>${r.team || ''}</td>
          <td class="num">${fmt(r.val)}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    let base = getSet().sort(defaultSort);
    let withRanks = computeRanks(base);

    // search filter
    function applyFilter(){
      const q = (search?.value || '').trim().toLowerCase();
      const rows = !q ? withRanks : withRanks.filter(r =>
        (r.name||'').toLowerCase().includes(q) || (r.team||'').toLowerCase().includes(q)
      );
      render(rows);
    }
    if (search) search.addEventListener('input', applyFilter);

    // CSV export
    if (exportBtn) exportBtn.addEventListener('click', () => {
      const rows = [...document.querySelectorAll('#statsTable tbody tr')].map(tr => {
        const tds = tr.querySelectorAll('td');
        return [tds[0].innerText, tds[1].innerText, tds[2].innerText, tds[3].innerText];
      });
      const header = ['Rank','Player','Team', ps.stat];
      const csv = [header, ...rows].map(r => r.map(x => `"${(x||'').replace(/"/g,'""')}"`).join(',')).join('\r\n');
      const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `player-stats_${ps.view}_${ps.stat}_${ps.scope}_${ps.split}.csv`;
      a.click();
    });

    // table header click sorting
    const ths = document.querySelectorAll('#statsTable thead th');
    let sortKey = 'val', sortAsc = (ps.view==='goalies' && ps.stat==='GAA'); // initial
    ths.forEach(th => th.addEventListener('click', () => {
      const key = th.dataset.key;
      if (!key) return;
      if (key === sortKey) sortAsc = !sortAsc; else { sortKey = key; sortAsc = false; }
      const rows = withRanks.slice().sort((a,b)=>{
        const A = a[sortKey], B = b[sortKey];
        if (A === B) return 0;
        if (typeof A === 'number' && typeof B === 'number') return sortAsc ? A-B : B-A;
        return sortAsc ? String(A).localeCompare(String(B)) : String(B).localeCompare(String(A));
      });
      render(rows);
    }));

    // initial paint
    applyFilter();

    // set document title nicely
    document.title = `Player Statistics — ${ps.view.charAt(0).toUpperCase()+ps.view.slice(1)} — ${ps.stat}`;
    document.getElementById('portal-title').textContent = UHA.title || 'Your League Name';
  })();
  </script>
</body>
</html>
