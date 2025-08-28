<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=1280, initial-scale=1" />
  <title>United Hockey Association - NHL Home</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <link rel="stylesheet" href="assets/css/home.css">
</head>
<body>
  <div class="site">

    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <!-- SCORE TICKER (kept) -->
    <div class="score-ticker" aria-label="Live Scores Ticker">
      <div class="ticker-viewport">
        <div class="ticker-track" id="ticker-track"></div>
      </div>
    </div>

    <!-- MAIN CANVAS (unchanged) -->
    <div class="canvas">
      <!-- LEFT: Stacked Stats Sections -->
      <aside>
        <div class="sidebar-title">Statistics</div>
        <div class="leadersStack" id="leadersStack"></div>
      </aside>

      <!-- CENTER -->
      <main class="main">
        <!-- Feature -->
        <section class="feature">
          <div class="story-head">
            <div><span id="feature-team-abbr">TL</span>&nbsp; <strong id="feature-team-name">Team Name for Main Story</strong></div>
            <div>Image</div>
          </div>
          <div class="image">IMAGE</div>
          <div class="overlay">
            <h2 id="feature-headline">HEADLINE TITLE THAT IS OVERLAID OVER THE IMAGE</h2>
            <p id="feature-dek">This is where the first paragraph of the story will go, and it is overlaid above the image, just like the headline title.</p>
            <small id="feature-time">xx ago</small>
          </div>
        </section>

        <!-- Top headlines -->
        <section class="top-headlines">
          <div class="section-title">Top Headlines</div>
          <div class="th-grid">
            <div class="th-item">HEADLINE CARD 1</div>
            <div class="th-item">HEADLINE CARD 2</div>
            <div class="th-item">HEADLINE CARD 3</div>
          </div>
          <div class="th-caption">
            <div><strong>Headline 1</strong><small>xx ago</small></div>
            <div><strong>Headline 2</strong><small>xx ago</small></div>
            <div><strong>Headline 3</strong><small>xx ago</small></div>
          </div>
        </section>
      </main>

      <!-- RIGHT -->
      <aside class="sidebar-right">
        <div class="box" id="scoresCard">
  <div class="title">Pro League Scores</div>

  <!-- date ribbon (5-day window) -->
  <div class="scores-dates">
    <button type="button" class="nav prev" aria-label="Previous day">◀</button>
    <div id="scoresDates" class="dates-strip"></div>
    <button type="button" class="nav next" aria-label="Next day">▶</button>
  </div>

  <!-- scope + filter (right-aligned, small) -->
  <div class="scores-controls slim">
    <div class="fill"></div>
    <select id="scoresScope" class="scores-scope">
      <option value="pro">Pro</option>
      <option value="farm">Farm</option>
      <option value="echl">ECHL</option>
      <option value="juniors">Juniors</option>
    </select>
    <select id="scoresFilter" class="scores-filter">
      <option value="all">All</option>
      <option value="live">Live</option>
      <option value="final">Final</option>
      <option value="upcoming">Upcoming</option>
    </select>
  </div>

  <div id="proScores" class="scores-list"></div>
</div>

        <div class="box" style="min-height:420px;"><div class="title">Pro League Standings</div></div>
      </aside>
    </div>

    <footer class="footer">Placeholder footer • (c) Your League</footer>
  </div>

  <!-- CONFIG (pretend DB later) -->
  <script>
  // Minimal config (PHP can echo these later)
  window.UHA = {
    title: "Your League Name",
    context: "league-uha",
    ticker: [
      "PRO: NYR 5 — NJD 4 (F/OT)",
      "PRO: TOR 3 — MTL 2 (F)",
      "PRO: VAN 1 — CGY 1 (OT)",
      "FARM: ABB 4 — BEL 2 (3rd)"
    ],
    feature: {
      teamName:"Team Name for Main Story",
      teamAbbr:"TL",
      headline:"HEADLINE TITLE THAT IS OVERLAID OVER THE IMAGE",
      dek:"THIS IS WHERE THE FIRST PARAGRAPH OF THE STORY WILL GO, AND IT IS OVERLAID ABOVE THE IMAGE, JUST LIKE THE HEADLINE TITLE.",
      timeAgo:"XX AGO"
    },
    // same dummy data you had before:
    statsData: {
      skaters: {
        'PTS':[{name:'Player One',team:'TOR',val:86},{name:'Player Two',team:'NYR',val:83},{name:'Player Three',team:'EDM',val:79},{name:'Player Four',team:'COL',val:76},{name:'Player Five',team:'VAN',val:74}],
        'G':  [{name:'Sniper A',team:'EDM',val:44},{name:'Sniper B',team:'BOS',val:41},{name:'Sniper C',team:'COL',val:38},{name:'Sniper D',team:'DAL',val:37},{name:'Sniper E',team:'TOR',val:36}],
        'A':  [{name:'Dime A',team:'EDM',val:55},{name:'Dime B',team:'NYR',val:52},{name:'Dime C',team:'TBL',val:49},{name:'Dime D',team:'VAN',val:47},{name:'Dime E',team:'LAK',val:45}],
        '+/-':[{name:'Plus A',team:'BOS',val:+24},{name:'Plus B',team:'WPG',val:+21},{name:'Plus C',team:'MIN',val:+19},{name:'Plus D',team:'VGK',val:+17},{name:'Plus E',team:'NYR',val:+15}],
        'PIM':[{name:'Grit A',team:'PHI',val:82},{name:'Grit B',team:'ANA',val:79},{name:'Grit C',team:'OTT',val:75},{name:'Grit D',team:'MTL',val:72},{name:'Grit E',team:'SJS',val:70}]
      },
      goalies: {
        'SV%':[ {name:'Wall Master',team:'WPG',val:0.928},{name:'Blue Line',team:'STL',val:0.925},{name:'Net Minder',team:'BOS',val:0.923},{name:'Pad Stack',team:'VGK',val:0.921},{name:'Glove Save',team:'OTT',val:0.919} ],
        'GAA':[ {name:'Brick',team:'NSH',val:2.05},{name:'Clamp',team:'CAR',val:2.10},{name:'Anchor',team:'NYR',val:2.16},{name:'Moat',team:'DAL',val:2.20},{name:'Plug',team:'COL',val:2.22} ],
        'W':  [ {name:'Winner 1',team:'COL',val:36},{name:'Winner 2',team:'CAR',val:35},{name:'Winner 3',team:'VGK',val:33},{name:'Winner 4',team:'NJD',val:32},{name:'Winner 5',team:'TBL',val:31} ],
        'SO': [ {name:'Shut 1',team:'CGY',val:6},{name:'Shut 2',team:'BOS',val:5},{name:'Shut 3',team:'LAK',val:5},{name:'Shut 4',team:'SEA',val:4},{name:'Shut 5',team:'OTT',val:4} ]
      },
      rookies: {
        'PTS':[ {name:'Rook A',team:'DET',val:52},{name:'Rook B',team:'ARI',val:48},{name:'Rook C',team:'MTL',val:46},{name:'Rook D',team:'CHI',val:44},{name:'Rook E',team:'SJS',val:41} ],
        'G':  [ {name:'R Sniper',team:'DET',val:24},{name:'R Marksman',team:'ARI',val:22},{name:'R Finish',team:'MTL',val:20},{name:'R Clutch',team:'CHI',val:18},{name:'R Laser',team:'SJS',val:17} ],
        'A':  [ {name:'R Dime',team:'DET',val:33},{name:'R Feed',team:'ARI',val:31},{name:'R Help',team:'MTL',val:30},{name:'R Setup',team:'CHI',val:27},{name:'R Thread',team:'SJS',val:26} ],
        '+/-':[ {name:'R TwoWay',team:'DET',val:+12},{name:'R 200ft',team:'ARI',val:+10},{name:'R Calm',team:'MTL',val:+8},{name:'R Balance',team:'CHI',val:+7},{name:'R Calm',team:'SJS',val:+6} ]
      }
    }
    scores: {
  pro: {
    "2025-03-25": [
      {
        id: "OTT-BUF-20250325",
        away: { abbr:"OTT", name:"Senators", logo:"assets/img/teams/OTT.png", sog:32 },
        home: { abbr:"BUF", name:"Sabres",   logo:"assets/img/teams/BUF.png", sog:24 },
        aScore: 2, hScore: 3, status: "Final",
        goals: [
          { scorer:{name:"Tage Thompson", goals:35, headshot:"assets/img/players/thompson.png"},
            assists:["Z. Benson (14)","R. Dahlin (42)"],
            line:"OTT 2 – BUF 3 (3rd – 01:23)" }
        ],
        box: "boxscore.php?id=OTT-BUF-20250325",
        log: "gamelog.php?id=OTT-BUF-20250325"
      },
      {
        id: "PHI-TOR-20250325",
        away: { abbr:"PHI", name:"Flyers", logo:"assets/img/teams/PHI.png", sog:19 },
        home: { abbr:"TOR", name:"Maple Leafs", logo:"assets/img/teams/TOR.png", sog:30 },
        aScore: 2, hScore: 7, status: "Final",
        goals: [
          { scorer:{name:"Ryan Poehling", goals:8, headshot:"assets/img/players/poehling.png"},
            assists:["J. Pelletier (10)","R. Abols (2)"],
            line:"PHI 1 – TOR 0 (1st – 07:59)" }
        ]
      }
    ]
  }
}

  };
    UHA.leadersLinkBase = 'player-stats.php';    
</script>
<?php
// JSON MERGE (server-side, synchronous — no layout/ID changes)
$__uha_path = __DIR__ . '/data/uploads/home-data.json';
$__uha_data = [];
if (is_readable($__uha_path)) {
  $raw = file_get_contents($__uha_path);
  $tmp = json_decode($raw, true);
  if (is_array($tmp)) { $__uha_data = $tmp; }
}

$__uha_ticker_path = __DIR__ . '/data/uploads/ticker-current.json';
if (is_readable($__uha_ticker_path)) {
  $t_raw = file_get_contents($__uha_ticker_path);
  $t_json = json_decode($t_raw, true);
  if (is_array($t_json) && !empty($t_json['ticker']) && is_array($t_json['ticker'])) {
    // Prefer RSS ticker when present
    $__uha_data['ticker'] = $t_json['ticker'];
  }
}
?>
<script>
(function () {
  var data = <?php echo json_encode($__uha_data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
  if (!data || typeof data !== 'object') return;

  // Merge onto existing demo object so your JS keeps working
  if (typeof window.UHA !== 'object' || window.UHA === null) window.UHA = {};
  Object.assign(window.UHA, data);

  // ---- Leaders aliasing so existing tabs populate ----
  function aliasStatKeys(bucket, mapping) {
    if (!bucket) return;
    Object.keys(mapping).forEach(function(pretty){
      var alts = mapping[pretty];
      if (bucket[pretty]) return;           // already has the pretty key
      for (var i=0;i<alts.length;i++){
        var k = alts[i];
        if (bucket[k]) { bucket[pretty] = bucket[k]; break; }
      }
    });
  }
  function normalizeLeaders(stats) {
    if (!stats) return;
    aliasStatKeys(stats.skaters, {
      'Points':  ['PTS','points','pt'],
      'Goals':   ['G','goals'],
      'Assists': ['A','assists']
    });
    aliasStatKeys(stats.defense, {
      'Points':  ['PTS','points','pt'],
      'Goals':   ['G','goals'],
      'Assists': ['A','assists']
    });
    // Goalies pods usually read these exact labels already,
    // but make sure arrays exist so the UI doesn't choke.
    stats.goalies = stats.goalies || {};
    ['GAA','SV%','SO','W'].forEach(function(k){
      if (!stats.goalies[k]) stats.goalies[k] = stats.goalies[k] || [];
    });
    // Ensure values are numeric
    ['skaters','defense','goalies','rookies'].forEach(function(group){
      var g = stats[group]; if (!g) return;
      Object.keys(g).forEach(function(stat){
        var arr = g[stat]; if (!Array.isArray(arr)) return;
        g[stat] = arr.map(function(x){
          if (x && typeof x.val !== 'undefined') {
            var n = +x.val; if (!isNaN(n)) x.val = n;
          }
          return x;
        });
      });
    });
  }
  if (window.UHA.statsData) normalizeLeaders(window.UHA.statsData);

  // Compat: some scripts expect statsDataByScope
  if (!window.UHA.statsDataByScope && window.UHA.statsData) {
    window.UHA.statsDataByScope = { pro: window.UHA.statsData };
  }

  // Make sure default scope is set (scores rail)
  if (!window.UHA.defaultScoresScope) window.UHA.defaultScoresScope = 'pro';
})();
</script>


<script src="assets/js/nav.js"></script>
<script src="assets/js/home.js"></script>
<script src="assets/js/scores.js"></script>

</body>
</html>
