<?php
        // helper: parse CSV to assoc rows with normalized keys
        function parse_csv_assoc($file)
        {
            $rows = [];
            if (!is_readable($file))
                return $rows;
            if (($fh = fopen($file, 'r')) === false)
                return $rows;
            $hdr = fgetcsv($fh);
            if (!$hdr) {
                fclose($fh);
                return $rows;
            }
            $norm = [];
            foreach ($hdr as $h) {
                $norm[] = strtolower(preg_replace('/[^a-z0-9%]+/i', '', (string) $h));
            }
            while (($cols = fgetcsv($fh)) !== false) {
                $row = [];
                foreach ($norm as $i => $k) {
                    $row[$k] = $cols[$i] ?? null;
                }
                $rows[] = $row;
            }
            fclose($fh);
            return $rows;
        }
// Load teams map (id -> {abbr, name, logo})
$teamsMap = [];
if (is_readable($teamsJsonPath)) {
    $tj = json_decode((string) file_get_contents($teamsJsonPath), true);
    foreach (($tj['teams'] ?? []) as $t) {
        $id = (string) ($t['id'] ?? $t['teamId'] ?? '');
        if ($id === '')
            continue;
        $abbr = (string) ($t['abbr'] ?? $t['abbre'] ?? $t['shortName'] ?? $t['name'] ?? '');
        $name = (string) ($t['shortName'] ?? $t['name'] ?? $abbr);
        $teamsMap[$id] = [
            'abbr' => $abbr,
            'name' => $name,
            'logo' => $abbr ? ("assets/img/logos/{$abbr}_light.svg") : null,
        ];
    }
}





























?>