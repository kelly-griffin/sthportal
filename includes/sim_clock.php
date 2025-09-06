<?php
// includes/sim_clock.php
declare(strict_types=1);

define('SIM_CLOCK_PATH', __DIR__ . '/../assets/json/sim_clock.json');

function sim_clock_read(): array {
  if (!is_file(SIM_CLOCK_PATH)) {
    return [
      'season'    => date('Y') . '-' . (date('Y')+1),
      'sim_date'  => date('Y-m-d'),
      'game_day'  => null,
      'updated_at'=> gmdate('c'),
    ];
  }
  $j = json_decode((string)file_get_contents(SIM_CLOCK_PATH), true) ?: [];
  if (empty($j['sim_date'])) $j['sim_date'] = date('Y-m-d');
  return $j;
}
function sim_clock_write(array $data): void {
  $data['updated_at'] = gmdate('c');
  $dir = dirname(SIM_CLOCK_PATH);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  file_put_contents(SIM_CLOCK_PATH, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
function sim_clock_now(): string { return sim_clock_read()['sim_date']; }
function sim_clock_set(string $date, ?int $game_day=null, ?string $season=null): array {
  $j = sim_clock_read();
  $j['sim_date'] = date('Y-m-d', strtotime($date));
  if ($game_day !== null) $j['game_day'] = $game_day;
  if ($season   !== null) $j['season']   = $season;
  sim_clock_write($j);
  return $j;
}
