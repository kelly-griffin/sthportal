<?php
// /sthportal/team-stats.php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

// URL params
$scope = strtolower((string)($_GET['scope'] ?? 'pro'));      // pro|farm|echl|juniors
$split = strtolower((string)($_GET['split'] ?? 'season'));   // season|playoffs
$cat   = strtolower((string)($_GET['cat']   ?? 'standings')); // standings|special|rates|shootingsave|discipline

$allowedScopes = ['pro','farm','echl','juniors'];
$allowedSplits = ['season','playoffs'];
$allowedCats   = ['standings','special','rates','shootingsave','discipline'];

if (!in_array($scope, $allowedScopes, true)) $scope = 'pro';
if (!in_array($split, $allowedSplits, true)) $split = 'season';
if (!in_array($cat,   $allowedCats,   true)) $cat   = 'standings';

$title = "Team Statistics — " . ucfirst($cat);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="assets/css/nav.css">
</head>
<body>
  <div class="site">
    <!-- Filters & Table -->
    <main class="stats-main">
      <div class="filters">
        <!-- Category tabs -->
        <nav class="tabs" aria-label="Category">
          <?php
          $cats = [
            'standings'   => 'Standings',
            'special'     => 'Special Teams',
            'rates'       => 'Per-Game Rates',
            'shootingsave'=> 'Shooting & Save',
            'discipline'  => 'Discipline'
          ];
          foreach ($cats as $k => $label):
            $cls = ($k === $cat) ? 'tab active' : 'tab';
            $href = "team-stats.php?cat={$k}&scope={$scope}&split={$split}";
          ?>
            <a class="<?= $cls ?>" href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
          <?php endforeach; ?>
        </nav>

        <!-- Scope/Split -->
        <div class="selects">
          <form method="get" class="inline">
            <input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>">
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
            <input id="search" class="search" type="search" placeholder="Search team…">
            <button type="button" id="exportCsv" class="btn small">Export CSV</button>
          </div>
        </div>
      </div>

      <section class="table-card">
        <header class="table-head">
          <h2 id="tableTitle">Team Statistics</h2>
          <small class="muted" id="tableSub">Sorted by Points</small>
        </header>

        <div class="table-wrap">
          <table id="teamTable">
            <thead>
              <!-- headers injected by JS to match category -->
            </thead>
            <tbody><!-- rows injected by JS --></tbody>
          </table>
        </div>
      </section>
    </main>

    <footer class="footer">© Your League • Team Statistics</footer>
  </div>

  <!-- Dummy data (swap with DB output later) -->
  <script>
    window.UHA = window.UHA || {};
    UHA.title = "Your League Name";
    UHA.context = "league-uha";

    // Data shape: teamStats[scope][split] = array of team rows
    // Row fields we’ll use: team, gp, w, l, ot, pts, gf, ga, ppPct, pkPct, shPct, svPct, pimG
    UHA.teamStats = {
      pro: {
        season: [
          { team:"TOR", gp:82, w:52, l:22, ot:8,  pts:112, gf:298, ga:237, ppPct:25.3, pkPct:82.0, shPct:10.8, svPct:0.909, pimG:7.2 },
          { team:"NYR", gp:82, w:50, l:24, ot:8,  pts:108, gf:281, ga:232, ppPct:24.1, pkPct:82.9, shPct:10.3, svPct:0.912, pimG:6.8 },
          { team:"WPG", gp:82, w:49, l:23, ot:10, pts:108, gf:260, ga:210, ppPct:21.2, pkPct:83.7, shPct:9.9,  svPct:0.922, pimG:6.1 }
        ],
        playoffs: []
      },
      farm: { season: [], playoffs: [] }
    };
  </script>

  <script src="assets/js/nav.js"></script>
  <script>
  // build table by category; derive GD, GF/G, GA/G, PDO, etc.
  (function(){
    const $ = s => document.querySelector(s);
    const scope = "<?= $scope ?>";
    const split = "<?= $split ?>";
    const cat   = "<?= $cat ?>";

    document.getElementById('portal-title').textContent = UHA.title || 'Your League Name';

    const HEADERS = {
      standings: [
        {key:'team', label:'Team'},
        {key:'gp',   label:'GP', num:true},
        {key:'w',    label:'W',  num:true},
        {key:'l',    label:'L',  num:true},
        {key:'ot',   label:'OT', num:true},
        {key:'pts',  label:'PTS',num:true, default:true, desc:true},
        {key:'gf',   label:'GF', num:true},
        {key:'ga',   label:'GA', num:true},
        {key:'gd',   label:'GD', num:true, derived:(r)=>r.gf-r.ga}
      ],
      special: [
        {key:'team', label:'Team'},
        {key:'ppPct',label:'PP%', num:true, fmt:v=>v.toFixed(1), default:true, desc:true},
        {key:'pkPct',label:'PK%', num:true, fmt:v=>v.toFixed(1)},
      ],
      rates: [
        {key:'team', label:'Team'},
        {key:'gfpg', label:'GF/G', num:true, fmt:v=>v.toFixed(2), derived:(r)=>r.gp? r.gf/r.gp:0, default:true, desc:true},
        {key:'gapg', label:'GA/G', num:true, fmt:v=>v.toFixed(2), derived:(r)=>r.gp? r.ga/r.gp:0},
      ],
      shootingsave: [
        {key:'team', label:'Team'},
        {key:'shPct',label:'Sh%', num:true, fmt:v=>v.toFixed(1), default:true, desc:true},
        {key:'svPct',label:'Sv%', num:true, fmt:v=>v.toFixed(3)},
        {key:'pdo',  label:'PDO', num:true, fmt:v=>v.toFixed(3), derived:(r)=> (r.shPct/100) + (r.svPct||0) }
      ],
      discipline: [
        {key:'team', label:'Team'},
        {key:'pimG', label:'PIM/G', num:true, fmt:v=>v.toFixed(1), default:true} // asc by default
      ]
    };

    const titleByCat = {
      standings:'Standings',
      special:'Special Teams',
      rates:'Per-Game Rates',
      shootingsave:'Shooting & Save',
      discipline:'Discipline'
    };

    $('#tableTitle').textContent = titleByCat[cat] || 'Team Statistics';

    function getRows(){
      const arr = (((UHA.teamStats||{})[scope]||{})[split]||[]).map(r=>({...r}));
      // fill derived fields:
      const cols = HEADERS[cat];
      arr.forEach(r=>{
        cols.forEach(c=>{
          if (c.derived && typeof r[c.key] === 'undefined') r[c.key] = c.derived(r);
        });
      });
      return arr;
    }

    function render(){
      const thead = $('#teamTable thead');
      const tbody = $('#teamTable tbody');
      const cols = HEADERS[cat];

      // build header
      thead.innerHTML = '<tr>' + cols.map(c =>
        `<th data-key="${c.key}" class="${c.num?'num':''}">${c.label}</th>`
      ).join('') + '</tr>';

      // sort defaults
      let sortKey = (cols.find(c=>c.default) || cols.find(c=>c.num) || cols[0]).key;
      let sortAsc = !(cols.find(c=>c.default)?.desc); // desc true => start desc

      // title note
      $('#tableSub').textContent = `Sorted by ${cols.find(c=>c.key===sortKey).label} ${sortAsc?'(low→high)':'(high→low)'}`;

      function sortRows(rows){
        return rows.sort((a,b)=>{
          const A=a[sortKey], B=b[sortKey];
          if (A===B) return 0;
          if (typeof A==='number' && typeof B==='number') return sortAsc ? A-B : B-A;
          return (sortAsc?1:-1) * String(A).localeCompare(String(B));
        });
      }

      function paint(rows){
        tbody.innerHTML = rows.map(r => {
          return '<tr>' + cols.map(c => {
            let v = r[c.key];
            if (typeof v === 'number') {
              v = c.fmt ? c.fmt(v) : v;
            }
            return `<td class="${c.num?'num':''}">${v ?? ''}</td>`;
          }).join('') + '</tr>';
        }).join('');
      }

      // initial
      let base = getRows();
      paint(sortRows(base));

      // header click sorting
      thead.querySelectorAll('th').forEach(th=>{
        th.addEventListener('click', ()=>{
          const key = th.dataset.key;
          if (!key) return;
          if (key === sortKey) sortAsc = !sortAsc; else { sortKey = key; sortAsc = false; }
          $('#tableSub').textContent = `Sorted by ${cols.find(c=>c.key===sortKey).label} ${sortAsc?'(low→high)':'(high→low)'}`;
          paint(sortRows([...base]));
        });
      });

      // search filter
      const qEl = $('#search');
      if (qEl) qEl.addEventListener('input', ()=>{
        const q = qEl.value.trim().toLowerCase();
        const filt = base.filter(r => (r.team||'').toLowerCase().includes(q));
        paint(sortRows(filt));
      });

      // CSV export
      const exportBtn = document.getElementById('exportCsv');
      if (exportBtn) exportBtn.addEventListener('click', ()=>{
        const hdr = cols.map(c=>c.label);
        const rows = [...document.querySelectorAll('#teamTable tbody tr')].map(tr =>
          [...tr.querySelectorAll('td')].map(td => td.innerText)
        );
        const csv = [hdr, ...rows].map(r => r.map(x => `"${(x||'').replace(/"/g,'""')}"`).join(',')).join('\r\n');
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `team-stats_${cat}_${scope}_${split}.csv`;
        a.click();
      });
    }

    render();
    document.title = `Team Statistics — ${titleByCat[cat]}`;
  })();
  </script>
</body>
</html>
