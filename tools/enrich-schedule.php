<?php
// tools/enrich-schedule.php — REPLACEMENT (team-checked, but gameNumber fallback)
// Purpose: Fill Broadcasters for UNPLAYED using rules; Fill GWG/W goalie for PLAYED.
// Behavior:
//   1) Prefer safe mode: only inject GWG/W when UHA-#.json teams match the schedule (by ABBR/Name).
//   2) If teams don't match but the UHA-#.json exists, FALL BACK and still inject (trust the game number / link).
// Logging summarises applied, applied_via_fallback, missing_json, mismatched_skipped (should be 0 due to fallback).

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root           = dirname(__DIR__);
$schedulePath   = $root . '/assets/json/schedule-current.json';
$rulesPath      = $root . '/assets/json/broadcasters-rules.json';
$overridesPath  = $root . '/assets/json/broadcasters-overrides.json';
$teamsPath      = $root . '/assets/json/teams.json';
$boxscoresDir   = $root . '/assets/json/boxscores';

// Toggle to allow global defaults from rules.defaults when no other rule hits.
$USE_DEFAULTS = false;

// ---------------- helpers ----------------
function jload(string $p){ return file_exists($p) ? json_decode((string) file_get_contents($p), true) : null; }
function jsave(string $p, $data){ file_put_contents($p, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }
function norm($s){ return strtolower(trim((string)$s)); }
function isPlayed(array $g): bool {
  if (array_key_exists('Play', $g)) {
    $v = $g['Play'];
    if (is_bool($v)) return $v;
    $s = strtoupper(trim((string)$v));
    if (in_array($s, ['TRUE','T','YES','Y','1'], true)) return true;
    if (in_array($s, ['FALSE','F','NO','N','0',''], true)) return false;
  }
  if (isset($g['visitorScore'], $g['homeScore'])) {
    return ((int)$g['visitorScore'] > 0) || ((int)$g['homeScore'] > 0);
  }
  return false;
}

function abbrFromName(string $name, array $teams): string {
  $ln = norm($name);
  foreach (($teams['teams'] ?? []) as $t) {
    if (norm($t['name'] ?? '') === $ln) return (string)($t['abbr'] ?? '');
  }
  return '';
}
function abbrFromId(string $id, array $teams): string {
  foreach (($teams['teams'] ?? []) as $t) {
    if ((string)($t['id'] ?? '') === (string)$id) return (string)($t['abbr'] ?? '');
  }
  return '';
}

function computeBroadcasters(array $g, array $rules, ?array $ovr, bool $useDefaults): array {
  $hits = [];
  $homeId = (string)($g['homeTeamId'] ?? '');
  $date = (string)($g['date'] ?? '');
  $weekday = (string)($g['weekday'] ?? '');
  $timeArr = $g['time'] ?? [];
  $timeStr = is_array($timeArr) ? ($timeArr[0] ?? '') : (string)$timeArr;

  // explicit override by composite key
  $key = "{$date}_" . ($g['visitorTeamId'] ?? $g['visitorTeam'] ?? '') . "_at_" . ($g['homeTeamId'] ?? $g['homeTeam'] ?? '');
  if (!empty($ovr[$key])) {
    $v = $ovr[$key];
    if (!is_array($v)) $v = array_map('trim', explode(',', (string)$v));
    return $v;
  }

  foreach (($rules['matchups'] ?? []) as $m) {
    if ((string)($m['visitor'] ?? '') === (string)($g['visitorTeamId'] ?? '') &&
        (string)($m['home'] ?? '')    === (string)($g['homeTeamId'] ?? '')) {
      $hits = $m['broadcasters'] ?? [];
      break;
    }
  }

  if (empty($hits) && !empty(($rules['specialDates'] ?? [])[$date])) {
    $hits = $rules['specialDates'][$date];
  }

  if (empty($hits)) {
    foreach (($rules['nationalWindows'] ?? []) as $w) {
      if (($w['weekday'] ?? '') === $weekday) {
        $re = '/' . str_replace('/', '\/', ($w['timeRegex'] ?? '')) . '/i';
        if ($timeStr !== '' && @preg_match($re, $timeStr)) {
          $hits = $w['broadcasters'] ?? [];
          break;
        }
      }
    }
  }

  if (empty($hits) && !empty(($rules['byHomeTeam'] ?? [])[$homeId])) {
    $hits = $rules['byHomeTeam'][$homeId];
  }

  if (empty($hits) && !empty(($rules['byWeekday'] ?? [])[$weekday])) {
    $hits = $rules['byWeekday'][$weekday];
  }

  if (empty($hits) && $useDefaults) {
    $hits = $rules['defaults'] ?? [];
  }

  return array_values(array_unique(array_map('trim', (array)$hits)));
}

function loadBox(string $dir, string $base): ?array {
  $p = $dir . '/' . $base . '.json';
  if (!is_file($p)) return null;
  return json_decode((string) file_get_contents($p), true);
}

function boxMatchesGame(array $g, array $box, array $teams): bool {
  $sv = (string)($g['visitorTeam'] ?? '');
  $sh = (string)($g['homeTeam'] ?? '');
  $svId = (string)($g['visitorTeamId'] ?? '');
  $shId = (string)($g['homeTeamId'] ?? '');

  $schedV = $svId !== '' ? abbrFromId($svId, $teams) : abbrFromName($sv, $teams);
  $schedH = $shId !== '' ? abbrFromId($shId, $teams) : abbrFromName($sh, $teams);

  $boxV = abbrFromName((string)($box['visitor']['name'] ?? ''), $teams);
  $boxH = abbrFromName((string)($box['home']['name'] ?? ''), $teams);

  if ($schedV === '' || $schedH === '' || $boxV === '' || $boxH === '') return false;
  return ($schedV === $boxV && $schedH === $boxH);
}

// ---------------- main ----------------
$schedule = jload($schedulePath);
$rules    = jload($rulesPath) ?? [];
$ovr      = jload($overridesPath) ?? [];
$teams    = jload($teamsPath) ?? ['teams'=>[]];

if (!$schedule) { echo "No schedule found.\n"; exit(0); }

$applied = 0;
$appliedFallback = 0;
$missingJson = 0;
$mismatchSkipped = 0;

foreach ($schedule['games'] as &$g) {
  // UNPLAYED → broadcasters
  if (!isPlayed($g)) {
    $g['broadcasters'] = computeBroadcasters($g, $rules, $ovr, $USE_DEFAULTS);
    continue;
  }

  // PLAYED → GWG/W goalie from boxscore JSON
  $linkBase = basename((string)($g['link'] ?? ''), '.html'); // e.g., UHA-1
  if ($linkBase === '') continue;

  $box = loadBox($boxscoresDir, $linkBase);
  if (!$box) { $missingJson++; continue; }

  $gwg   = trim((string)($box['gwg'] ?? ''));
  $wgoal = trim((string)($box['winningGoalie'] ?? ''));

  if ($gwg === '' && $wgoal === '') continue; // nothing to inject

  $match = boxMatchesGame($g, $box, $teams);
  if ($match) {
    if ($gwg !== '' && ($g['gwg'] ?? '') === '') { $g['gwg'] = $gwg; $applied++; }
    if ($wgoal !== '' && ($g['winningGoalie'] ?? '') === '') { $g['winningGoalie'] = $wgoal; $applied++; }
  } else {
    // FALLBACK: trust the game number/link
    if ($gwg !== '' && ($g['gwg'] ?? '') === '') { $g['gwg'] = $gwg; $appliedFallback++; }
    if ($wgoal !== '' && ($g['winningGoalie'] ?? '') === '') { $g['winningGoalie'] = $wgoal; $appliedFallback++; }
  }
}
unset($g);

jsave($schedulePath, $schedule);
echo "Enriched schedule.\n";
echo "Applied (verified): {$applied}\n";
echo "Applied via fallback: {$appliedFallback}\n";
echo "Boxscore JSON missing: {$missingJson}\n";
echo "Mismatched & skipped: {$mismatchSkipped}\n"; // should be 0 with fallback
