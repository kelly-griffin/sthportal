<?php
// /sthportal/tools/build-home-json.php
// Build /data/uploads/home-data.json consumed by home.php.
// JSON is fallback for ticker/feature only; everything else is rebuilt from CSV.
// Changes in this version:
//  - Map Day/Game# -> Date via season start (configurable via /data/season.json)
//  - Limit to ONE date per scope (Pro/Farm)
//  - Map STHS/V3 headers: visitorteam/hometeam, visitorteamscore/hometeamscore, *abbre
//  - Generate ticker from today's slate
//  - Provide statsData AND statsDataByScope, add Defense pod by position

declare(strict_types=1);

$root = dirname(__DIR__);
@require_once $root . '/includes/bootstrap.php'; // optional
header('Content-Type: text/plain; charset=utf-8');

$uploadsDir = $root . '/data/uploads';
$outFile    = $uploadsDir . '/home-data.json';

/* -------- Season start config (Day# -> Date) -------- */
$seasonCfgFile = $root . '/data/season.json';
$seasonStartPro  = '2025-10-07';
$seasonStartFarm = '2025-10-07';
if (is_file($seasonCfgFile) && is_readable($seasonCfgFile)) {
  $cfg = @json_decode((string)file_get_contents($seasonCfgFile), true);
  if (is_array($cfg)) {
    if (!empty($cfg['season_start']))      $seasonStartPro = $seasonStartFarm = $cfg['season_start'];
    if (!empty($cfg['season_start_pro']))  $seasonStartPro  = $cfg['season_start_pro'];
    if (!empty($cfg['season_start_farm'])) $seasonStartFarm = $cfg['season_start_farm'];
  }
}

$log = [];
function logln(string $s){ global $log; $log[]=$s; }
function show(){ global $log; echo implode("\n",$log),"\n"; }

if (!is_dir($uploadsDir)) {
  @mkdir($uploadsDir, 0775, true);
  if (!is_dir($uploadsDir)) { http_response_code(500); echo "ERROR: Missing $uploadsDir\n"; exit; }
}

logln("Portal home-data builder");
logln("Uploads directory: $uploadsDir");
logln("Season start (PRO):  $seasonStartPro");
logln("Season start (FARM): $seasonStartFarm");
logln(str_repeat('-',60));

/* ------------------------------- utils ---------------------------------- */
function safe_glob(string $dir, array $patterns): array {
  $out=[]; foreach($patterns as $p){ foreach(glob($dir.'/'.$p, GLOB_NOSORT)?:[] as $f){ if(is_file($f)) $out[]=$f; } }
  usort($out, fn($a,$b)=>filemtime($b)<=>filemtime($a)); // newest first
  return array_values(array_unique($out));
}
function normalize_header(string $s): string {
  if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) $s = substr($s, 3); // strip BOM
  $s = strtolower(trim($s));
  $s = str_replace(['  ','-','__'],[' ',' ','_'],$s);
  return $s;
}
function parse_csv_assoc(string $file): array {
  $rows=[]; if(!is_readable($file)) return $rows;
  if(($fh=fopen($file,'r'))===false) return $rows;
  $hdr=fgetcsv($fh,0,',','"',"\\"); if(!$hdr){ fclose($fh); return $rows; }
  $norm=[]; foreach($hdr as $h){ $norm[] = normalize_header((string)$h); }
  while(($cols=fgetcsv($fh,0,',','"',"\\"))!==false){
    if(count($cols)!==count($norm)){ $alt=str_getcsv(implode(',',$cols),';'); if(count($alt)===count($norm)) $cols=$alt; }
    $row=[]; foreach($norm as $i=>$k){ $row[$k]=$cols[$i]??null; } $rows[]=$row;
  }
  fclose($fh); return $rows;
}
function coalesce(array $row, array $keys, $default=null){
  foreach($keys as $k){ if(array_key_exists($k,$row) && $row[$k]!=='' && $row[$k]!==null) return $row[$k]; }
  return $default;
}
function as_int($v,$default=null){ if($v===null||$v==='') return $default; if(is_numeric($v)) return (int)$v; return $default; }
function as_float($v,$default=null){
  if($v===null||$v==='') return $default;
  $v=str_replace(['%',' '],['',''],(string)$v);
  $v=str_replace(',','.', $v);
  if(is_numeric($v)) return (float)$v;
  return $default;
}
function guess_date($v):?string{
  if(!$v) return null; $v=trim((string)$v);
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) return $v;
  if(preg_match('/^(\d{4})(\d{2})(\d{2})$/',$v,$m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
  if(preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/',$v,$m)){ $m1=(int)$m[1]; $m2=(int)$m[2]; $y=(int)$m[3]; if($m1>12){ [$m1,$m2]=[$m2,$m1]; } return sprintf('%04d-%02d-%02d',$y,$m1,$m2); }
  return null;
}
function date_from_day(string $startIso, ?int $dayNum): ?string {
  if ($dayNum===null || $dayNum<1) return null;
  $dt = DateTime::createFromFormat('Y-m-d', $startIso);
  if (!$dt) return null;
  if ($dayNum>1) $dt->modify('+' . ($dayNum-1) . ' days');
  return $dt->format('Y-m-d');
}

/* ---------------------- JSON fallback (ticker/feature) ------------------- */
$merged=[];
$existingJson=safe_glob($uploadsDir,['*.json']);
if($existingJson){
  logln("Found JSON candidates:");
  foreach($existingJson as $j) logln("  - ".basename($j));
  $prio=[]; foreach($existingJson as $f){
    $bn=strtolower(basename($f)); $score=0;
    if(str_contains($bn,'home-data'))$score+=5;
    if(str_contains($bn,'current'))  $score+=3;
    $prio[]=[$score,$f];
  }
  usort($prio,fn($a,$b)=>$b[0]<=>$a[0]);
  foreach($prio as[, $f]){
    $raw=@file_get_contents($f); $j=@json_decode((string)$raw,true);
    if(is_array($j)){
      foreach(['ticker','feature'] as $k){ if(isset($j[$k]) && !isset($merged[$k])) $merged[$k]=$j[$k]; }
    }
  }
  logln("Fallback keys from JSON (if any): ".implode(', ', array_keys($merged) ?: ['(none)']));
}else{
  logln("No JSON files found in data/uploads/ (fallback not needed).");
}

/* ------------------------------ CSV list -------------------------------- */
$csvs=safe_glob($uploadsDir,['*.csv','*.CSV','*.tsv','*.TSV']);
logln(str_repeat('-',60));
logln("Scanning CSV exports…");
if(!$csvs){ logln("No CSV files found."); } else { foreach($csvs as $f){ logln("  - ".basename($f)); } }

/* -------------------------------- SCORES -------------------------------- */
// Build EXACTLY ONE date per scope. Prefer TodayGame; else schedule with Day# -> Date.
{
  $files = [
    'pro_today'  => array_values(array_filter($csvs, fn($f)=>preg_match('/todaygame/i', basename($f)) && !preg_match('/farm/i', basename($f)))),
    'farm_today' => array_values(array_filter($csvs, fn($f)=>preg_match('/todaygame/i', basename($f)) &&  preg_match('/farm/i', basename($f)))),
    'pro_sched'  => array_values(array_filter($csvs, fn($f)=>preg_match('/(pro)?schedule/i', basename($f)) && !preg_match('/farm/i', basename($f)))),
    'farm_sched' => array_values(array_filter($csvs, fn($f)=>preg_match('/(farm)schedule/i', basename($f)))),
  ];

  $dumpedHeaders=false;
  $extractGame = function(array $r){
    $homeName = coalesce($r, ['hometeam','home team','home','home name']);
    $awayName = coalesce($r, ['visitorteam','visitor team','away','away team','visitor']);
    $homeAbbr = coalesce($r, ['hometeamabbre','home abbreviation','home abbr','homeabbr','home_abbr']);
    $awayAbbr = coalesce($r, ['visitorteamabbre','visitor abbreviation','visitor abbr','awayabbr','away_abbr']);
    if(!$homeName && $homeAbbr) $homeName = $homeAbbr;
    if(!$awayName && $awayAbbr) $awayName = $awayAbbr;
    if(!$homeName || !$awayName) return null;

    $hSc = as_int(coalesce($r, ['hometeamscore','home score','scorehome','hscore','home goals']));
    $aSc = as_int(coalesce($r, ['visitorteamscore','away score','scoreaway','ascore','away goals','visitor goals']));

    // OT/SO not present in these headers usually; infer from text if any
    $ot  = strtolower((string)coalesce($r, ['overtime','ot'],''));
    $so  = strtolower((string)coalesce($r, ['shootout','so'],''));

    $status='Scheduled';
    if($hSc!==null && $aSc!==null){
      $status='Final';
      if($so==='1'||$so==='true'||$so==='y'||str_contains($so,'shoot')) $status='Final/SO';
      elseif($ot==='1'||$ot==='true'||$ot==='y'||str_contains($ot,'ot')||str_contains($ot,'overtime')) $status='Final/OT';
    }

    $id = sprintf('%s-%s-%s',
      preg_replace('/\s+/','', strtoupper((string)($awayAbbr ?: $awayName))),
      preg_replace('/\s+/','', strtoupper((string)($homeAbbr ?: $homeName))),
      date('Ymd')
    );

    return [
      'id'    => $id,
      'away'  => ['abbr'=>$awayAbbr ?: $awayName, 'name'=>$awayName, 'sog'=>null],
      'home'  => ['abbr'=>$homeAbbr ?: $homeName, 'name'=>$homeName, 'sog'=>null],
      'aScore'=> $aSc, 'hScore'=> $hSc,
      'status'=> $status,
      'goals' => [],
      'box'   => 'boxscore.php?id='.rawurlencode($id),
      'log'   => 'gamelog.php?id='.rawurlencode($id),
    ];
  };

  $dateFromRow = function(array $r, string $seasonStartIso): ?string {
    $explicit = guess_date(coalesce($r, ['date','game date','gamedate','playedon','game_day','day','game day']));
    if ($explicit) return $explicit;
    $dayNum = as_int(coalesce($r, ['gamenumber','game number','day','#','daynum']));
    if(!$dayNum){
      $link = coalesce($r, ['link','url']);
      if ($link && preg_match('/(\d{1,4})(?!.*\d)/', (string)$link, $m)) $dayNum = (int)$m[1];
    }
    return date_from_day($seasonStartIso, $dayNum);
  };

  $oneDayFrom = function(array $files, string $seasonStartIso, ?string $scopeName) use ($extractGame,$dateFromRow,&$dumpedHeaders){
    $byDate=[];
    foreach($files as $f){
      $rows=parse_csv_assoc($f);
      if(!$rows) continue;
      if(!$dumpedHeaders){ $dumpedHeaders=true; logln("Schedule headers seen: ".implode(', ', array_keys($rows[0]))); }
      foreach($rows as $r){
        $d = $dateFromRow($r, $seasonStartIso);
        if(!$d) continue;
        $g = $extractGame($r); if(!$g) continue;
        $byDate[$d] = $byDate[$d] ?? [];
        $byDate[$d][] = $g;
      }
    }
    if(!$byDate) return null;
    ksort($byDate);
    // EXACTLY ONE date: the latest date with at least one game
    return array_slice($byDate, -1, 1, true);
  };

  // Prefer TodayGame if present (still compute date from Day#)
  $oneDayFromToday = function(array $files, string $seasonStartIso) use ($extractGame,$dateFromRow,&$dumpedHeaders){
    $rowsAll=[];
    foreach($files as $f){ $rowsAll = array_merge($rowsAll, parse_csv_assoc($f)); }
    if(!$rowsAll) return null;
    if(!$dumpedHeaders){ $dumpedHeaders=true; logln("TodayGame headers seen: ".implode(', ', array_keys($rowsAll[0]))); }
    $byDate=[];
    foreach($rowsAll as $r){
      $d = $dateFromRow($r, $seasonStartIso);
      if(!$d) continue;
      $g = $extractGame($r); if(!$g) continue;
      $byDate[$d] = $byDate[$d] ?? [];
      $byDate[$d][] = $g;
    }
    if(!$byDate) return null;
    ksort($byDate);
    return array_slice($byDate, -1, 1, true);
  };

  $scores = [];

  // PRO
  $pro = $oneDayFromToday($files['pro_today'], $seasonStartPro);
  if(!$pro) $pro = $oneDayFrom($files['pro_sched'], $seasonStartPro, 'pro');
  if($pro) $scores['pro'] = $pro;

  // FARM
  $farm = $oneDayFromToday($files['farm_today'], $seasonStartFarm);
  if(!$farm) $farm = $oneDayFrom($files['farm_sched'], $seasonStartFarm, 'farm');
  if($farm) $scores['farm'] = $farm;

  if($scores){
    $merged['scores'] = $scores; // override
    logln("Built scores (ONE day per scope): ".implode(', ', array_keys($scores)));
    foreach($scores as $scope=>$days){ logln("  $scope date: ".implode(', ', array_keys($days))); }

    // Build ticker from both scopes' one-day slate
    $ticker=[];
    foreach($scores as $scope=>$days){
      foreach($days as $date=>$games){
        foreach($games as $g){
          $line = strtoupper($scope).": {$g['away']['abbr']} ";
          if($g['aScore']!==null && $g['hScore']!==null){
            $line .= "{$g['aScore']} — {$g['home']['abbr']} {$g['hScore']} ({$g['status']})";
          } else {
            $line .= "at {$g['home']['abbr']} ({$g['status']})";
          }
          $ticker[] = $line;
        }
      }
    }
    if($ticker && (empty($merged['ticker']) || !is_array($merged['ticker']))) {
      $merged['ticker'] = $ticker; // keep schedule-built ticker only if no JSON ticker exists
    } else if (!empty($merged['ticker'])) {
      logln("Ticker present from JSON (RSS); keeping existing ticker.");
    }
  } else {
    logln("No recognizable games in schedule/today files.");
  }
}

/* ------------------------------ STANDINGS ------------------------------- */
{
  $teamFiles = [
    'pro'  => array_values(array_filter($csvs, fn($f)=>preg_match('/(v3)?proteam/i', basename($f)) && !preg_match('/farm/i', basename($f)))),
    'farm' => array_values(array_filter($csvs, fn($f)=>preg_match('/(v3)?farmteam/i', basename($f)))),
  ];
  $buildFromTeams = function(array $files){
    $divsE=[]; $divsW=[];
    foreach($files as $f){
      $rows=parse_csv_assoc($f); if(!$rows) continue;
      static $dumped=false; if(!$dumped){ $dumped=true; logln("Team headers (sample from ".basename($f)."): ".implode(', ', array_keys($rows[0]))); }
      foreach($rows as $r){
        $team = coalesce($r,['name','team','franchise','club','nickname','city name','team name','abbre','abbr','code']);
        $conf = coalesce($r,['conference','conf']);
        $div  = coalesce($r,['division','div']);
        $pts  = as_int(coalesce($r,['points','pts','point','p']), null);
        if(!$team || $pts===null) continue;
        $rec=['team'=>$team,'pts'=>$pts];
        if($conf && preg_match('/east/i',$conf)){
          $divsE[$div ?: 'Division'] = $divsE[$div ?: 'Division'] ?? [];
          $divsE[$div ?: 'Division'][] = $rec;
        } elseif($conf && preg_match('/west/i',$conf)){
          $divsW[$div ?: 'Division'] = $divsW[$div ?: 'Division'] ?? [];
          $divsW[$div ?: 'Division'][] = $rec;
        }
      }
    }
    $takeTop3=function(array $rows){
      usort($rows, fn($a,$b)=>$b['pts']<=>$a['pts']);
      return array_slice(array_map(fn($r)=>['nick'=>$r['team'],'full'=>$r['team'],'pts'=>$r['pts']], $rows),0,3);
    };
    $buildWC=function(array $divs) use($takeTop3){
      $used=[]; $all=[];
      foreach($divs as $rows){ $top=$takeTop3($rows); foreach($top as $t) $used[$t['nick']]=true; foreach($rows as $r) $all[]=$r; }
      usort($all, fn($a,$b)=>$b['pts']<=>$a['pts']);
      $wc=[]; foreach($all as $r){ if(isset($used[$r['team']])) continue; $wc[]=['nick'=>$r['team'],'full'=>$r['team'],'pts'=>$r['pts']]; if(count($wc)>=5) break; }
      return $wc;
    };
    if(!$divsE && !$divsW) return null;
    return [
      'east' => ['divisions'=>array_map($takeTop3, $divsE), 'wildcard'=>$buildWC($divsE)],
      'west' => ['divisions'=>array_map($takeTop3, $divsW), 'wildcard'=>$buildWC($divsW)],
    ];
  };

  $proStand = $buildFromTeams($teamFiles['pro']);
  if($proStand){
    $merged['standings']['pro'] = $proStand; // override
    logln("Built standings from ProTeam CSV (override).");
  } else {
    logln("No usable team files for standings.");
  }
}

/* -------------------------------- LEADERS ------------------------------- */
{
  $leadFiles=array_values(array_filter($csvs, fn($f)=>preg_match('/(v3)?(players|goalies)/i', basename($f))));
  $stats=['skaters'=>[],'defense'=>[],'goalies'=>[],'rookies'=>[]];

  $pushTop=function(array &$bucket, string $stat, array $rows, callable $map, bool $asc=false, int $limit=5){
    $out=[];
    foreach($rows as $r){ $m=$map($r); if(!empty($m['name'])) $out[]=$m; }
    usort($out, function($a,$b) use($asc){ return $asc ? ($a['val'] <=> $b['val']) : ($b['val'] <=> $a['val']); });
    $bucket[$stat]=array_slice($out,0,$limit);
  };

  $playerRows=[]; $goalieRows=[];
  foreach($leadFiles as $lf){
    $rows=parse_csv_assoc($lf); if(!$rows) continue;
    $bn=strtolower(basename($lf));
    if(preg_match('/goalies?/', $bn)) $goalieRows=array_merge($goalieRows,$rows);
    else                              $playerRows=array_merge($playerRows,$rows);
  }

  // Split defense by position
  $isDefense = function(array $r): bool {
    $pos = strtolower((string)coalesce($r,['position','pos','role','p']));
    return (bool)preg_match('/^d|def/', $pos);
  };
  $skaterRows = array_values(array_filter($playerRows, fn($r)=>!$isDefense($r)));
  $defRows    = array_values(array_filter($playerRows, fn($r)=> $isDefense($r)));

  if($playerRows){
    // Skaters
    if($skaterRows){
      $pushTop($stats['skaters'],'PTS',$skaterRows,function($r){ $g=as_int(coalesce($r,['g','goals']),0); $a=as_int(coalesce($r,['a','assists']),0); $pts=as_int(coalesce($r,['pts','points']), $g+$a); return ['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>$pts]; });
      $pushTop($stats['skaters'],'G',$skaterRows,fn($r)=>['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>as_int(coalesce($r,['g','goals']),0)]);
      $pushTop($stats['skaters'],'A',$skaterRows,fn($r)=>['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>as_int(coalesce($r,['a','assists']),0)]);
    }
    // Defense
    if($defRows){
      $pushTop($stats['defense'],'PTS',$defRows,function($r){ $g=as_int(coalesce($r,['g','goals']),0); $a=as_int(coalesce($r,['a','assists']),0); $pts=as_int(coalesce($r,['pts','points']), $g+$a); return ['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>$pts]; });
      $pushTop($stats['defense'],'G',$defRows,fn($r)=>['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>as_int(coalesce($r,['g','goals']),0)]);
      $pushTop($stats['defense'],'A',$defRows,fn($r)=>['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>as_int(coalesce($r,['a','assists']),0)]);
    }
  }

  if($goalieRows){
    // Goalies
    $pushTop($stats['goalies'],'SV%',$goalieRows,fn($r)=>['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>as_float(coalesce($r,['sv%','svpct','save%','save pct']),0.0)]);
    $pushTop($stats['goalies'],'GAA',$goalieRows,fn($r)=>['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>as_float(coalesce($r,['gaa','g.a.a']),99.0)], true);
    $pushTop($stats['goalies'],'SO' ,$goalieRows,fn($r)=>['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>as_int(coalesce($r,['so','shutout','shutouts']),0)]);
    $pushTop($stats['goalies'],'W'  ,$goalieRows,fn($r)=>['name'=>coalesce($r,['player','name']),'team'=>coalesce($r,['team','abbre','abbr']),'val'=>as_int(coalesce($r,['w','wins']),0)]);
  }

  if(!empty($stats['skaters']) || !empty($stats['defense']) || !empty($stats['goalies']) || !empty($stats['rookies'])){
    $merged['statsData']=$stats; // override
    // Mirror into statsDataByScope if your JS expects that shape
    $merged['statsDataByScope']=['pro'=>$stats]; 
    $merged['leadersLinkBase']='player-stats.php';
    logln("Built leaders pods (with Defense) (override).");
  } else {
    logln("No players/goalies CSVs found for leaders.");
  }
}

/* ------------------------- Transactions mini-list ----------------------- */
{
  $txFiles=array_values(array_filter($csvs, fn($f)=>preg_match('/(gm)?transaction/i', basename($f))));
  $mini=[];
  foreach($txFiles as $tf){
    $rows=parse_csv_assoc($tf);
    static $dumped=false; if(!$dumped && $rows){ $dumped=true; logln("Transactions headers: ".implode(', ', array_keys($rows[0]))); }
    foreach($rows as $r){
      $team=coalesce($r,['team','franchise','abbre','abbr','code','team name']);
      $type=coalesce($r,['type','action','category','movement','transaction type']);
      $plyr=coalesce($r,['player','name','player name']);
      $when=coalesce($r,['time','date','timestamp','created','created_at']);
      if($team && $type && $plyr){
        $mini[]=['team'=>$team,'type'=>$type,'player'=>$plyr,'timeAgo'=>$when? (string)$when : '','link'=>'activity.php?tab=transactions'];
      }
    }
  }
  if($mini){
    $merged['transactionsToday']=array_slice($mini,0,7); // override
    logln("Assembled transactions (override).");
  } else {
    logln("No transactions CSV found.");
  }
}

/* ----------------------------- Fallbacks -------------------------------- */
$merged['ticker']  = $merged['ticker']  ?? ["PRO: —","FARM: —"];
$merged['feature'] = $merged['feature'] ?? ['teamAbbr'=>'UHA','teamName'=>'Your League','headline'=>'Welcome to the Portal','dek'=>'Live data will appear as soon as uploads/DB are wired.','timeAgo'=>'just now'];

/* -------------------------------- Save ---------------------------------- */
$json=json_encode($merged, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
if($json===false){ logln("ERROR: JSON encode: ".(json_last_error_msg()?:'unknown')); show(); exit; }
$ok=@file_put_contents($outFile,$json)!==false;
logln(str_repeat('-',60));
if($ok){
  logln("Wrote: ".$outFile);
  logln("Keys: ".implode(', ', array_keys($merged)));
  logln("Done.");
}else{
  logln("ERROR: Could not write ".$outFile);
}
show();
