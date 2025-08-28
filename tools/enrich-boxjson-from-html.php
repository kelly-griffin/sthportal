<?php
// tools/enrich-boxjson-from-html.php
// Update ONLY gwg and winningGoalie in boxscores/UHA-*.json by parsing data/uploads/UHA-*.html.
// Safe: makes per-file .bak before writing. Skips Farm pages.
// Run from CLI: php tools/enrich-boxjson-from-html.php
// Or in browser: http://localhost/sthportal/tools/enrich-boxjson-from-html.php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$root    = dirname(__DIR__);
$htmlDir = $root . '/data/uploads';
$jsonDir = $root . '/data/uploads/boxscores';
$report  = $root . '/data/uploads/enrich-boxjson-report.csv';

function h2t(string $html): string {
  $html = preg_replace('~<script\b[^>]*>.*?</script>~is',' ', $html);
  $html = preg_replace('~<style\b[^>]*>.*?</style>~is',' ', $html);
  $t = strip_tags($html);
  $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  // normalize whitespace
  $t = preg_replace('/[ \t\x0B\p{Zs}]+/u', ' ', $t);
  $t = preg_replace('/\R+/u', ' ', $t);
  return trim($t);
}

/**
 * Strict extraction:
 *  - GWG: looks for "GWG: Player (TEAM)" style
 *  - Winning Goalie: requires team abbr in parentheses AND goalie tokens near the W decision (SV|Saves|SA|GA).
 *    Prevents matching skaters (“W: Max Domi”) which some pages contain in star summaries.
 */
function extract_gwg_and_w(string $text): array {
  $gwg = '';
  $w   = '';

  // GWG: "GWG: Player Name (TEAM)" or "GWG - Player Name"
  if (preg_match('~\bGWG\b[:\-\s]+([A-Za-z.\'\- ]{2,60})(?:\s*\(([A-Z]{2,3})\))?~u', $text, $m)) {
    $gwg = trim($m[1]) . (isset($m[2]) ? " ({$m[2]})" : '');
  }

  // Winning Goalie patterns (require team abbr and goalie-ish stats near W)
  $pats = [
    // "Sergei Bobrovsky (FLA) ... W, 31 SV"
    '~([A-Z][A-Za-z.\'\- ]{1,60})\s*\(([A-Z]{2,3})\)[^\.]{0,120}\bW\b[^\.]{0,120}\b(SV|Saves|SA|GA)\b~u',
    // "Winning Goalie: Name (TEAM)"
    '~\bWinning\s*Goalie\b[:\-\s]+([A-Z][A-Za-z.\'\- ]{1,60})\s*\(([A-Z]{2,3})\)~u',
    // "W: Name (TEAM) ... 28 Saves"
    '~\bW[:]\s*([A-Z][A-Za-z.\'\- ]{1,60})\s*\(([A-Z]{2,3})\)[^\.]{0,120}\b(SV|Saves|SA|GA)\b~u',
  ];
  foreach ($pats as $re) {
    if (preg_match($re, $text, $m)) {
      $w = trim($m[1]) . " ({$m[2]})";
      break;
    }
  }

  return [$gwg, $w];
}

function write_json_safe(string $path, array $data): bool {
  $dir = dirname($path);
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  // backup
  if (is_file($path)) {
    @copy($path, $path . '.bak.' . date('Ymd_His'));
  }
  return (bool)file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

$rows = [];
$updated = 0; $skipped = 0;

foreach (glob($htmlDir . '/UHA-*.html') as $htmlPath) {
  $base = basename($htmlPath, '.html');
  if (stripos($base, 'Farm') !== false) { $rows[] = [$base, 'SKIP_FARM', '', '']; continue; }

  $raw = @file_get_contents($htmlPath);
  if ($raw === false) { $rows[] = [$base, 'HTML_READ_FAIL', '', '']; $skipped++; continue; }

  $text = h2t($raw);
  [$gwg, $winner] = extract_gwg_and_w($text);

  $jsonPath = $jsonDir . '/' . $base . '.json';
  $j = [];
  if (is_file($jsonPath)) {
    $buf = @file_get_contents($jsonPath);
    if ($buf !== false) {
      // tolerate gzip/BOM/UTF-16
      if (strncmp($buf, "\x1F\x8B", 2) === 0 && function_exists('gzdecode')) {
        $tmp=@gzdecode($buf); if ($tmp !== false && $tmp !== null) $buf = $tmp;
      }
      if (strncmp($buf, "\xEF\xBB\xBF", 3) === 0) $buf = substr($buf,3);
      if (strpos(substr($buf,0,64), "\x00") !== false) $buf = @mb_convert_encoding($buf,'UTF-8','UTF-16,UTF-16LE,UTF-16BE');
      $dec = json_decode((string)$buf, true);
      if (is_array($dec)) $j = $dec;
    }
  }

  // Update only gwg / winningGoalie
  $beforeGWG = trim((string)($j['gwg'] ?? ''));
  $beforeW   = trim((string)($j['winningGoalie'] ?? ''));

  if ($gwg !== '')  $j['gwg'] = $gwg;
  if ($winner !== '') $j['winningGoalie'] = $winner;

  // ensure gameNumber at least
  if (!isset($j['gameNumber'])) {
    $j['gameNumber'] = (int)preg_replace('~\D~','', $base);
  }
  if (!isset($j['visitor'])) $j['visitor'] = ['name'=>'','score'=>0];
  if (!isset($j['home']))    $j['home']    = ['name'=>'','score'=>0];

  // Only write if something changed
  $afterGWG = trim((string)($j['gwg'] ?? ''));
  $afterW   = trim((string)($j['winningGoalie'] ?? ''));

  if ($afterGWG !== $beforeGWG || $afterW !== $beforeW) {
    if (write_json_safe($jsonPath, $j)) {
      $rows[] = [$base, 'UPDATED', $afterGWG, $afterW];
      $updated++;
    } else {
      $rows[] = [$base, 'WRITE_FAIL', $afterGWG, $afterW];
      $skipped++;
    }
  } else {
    $rows[] = [$base, 'UNCHANGED', $afterGWG, $afterW];
    $skipped++;
  }
}

// write CSV report
$fp = @fopen($report,'w');
if ($fp) {
  fputcsv($fp, ['file','status','gwg','winningGoalie']);
  foreach ($rows as $r) fputcsv($fp, $r);
  fclose($fp);
}

$summary = "Updated: {$updated}, Skipped: {$skipped}. Report: {$report}";
if (PHP_SAPI === 'cli') {
  echo $summary . PHP_EOL;
} else {
  echo nl2br(htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'));
  echo "<hr><table border='1' cellpadding='6' cellspacing='0'><tr><th>File</th><th>Status</th><th>GWG</th><th>W</th></tr>";
  foreach ($rows as $r) {
    echo "<tr><td>".htmlspecialchars($r[0])."</td><td>".htmlspecialchars($r[1])."</td><td>".htmlspecialchars($r[2])."</td><td>".htmlspecialchars($r[3])."</td></tr>";
  }
  echo "</table>";
}
