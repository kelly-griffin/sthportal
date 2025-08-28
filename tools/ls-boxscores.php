<?php
// tools/ls-boxscores.php — lists any UHA-*.json present and their sizes
$boxDir = dirname(__DIR__) . '/data/uploads/boxscores';
$files = glob($boxDir.'/UHA-*.json') ?: [];
echo "<h3>Boxscores dir:</h3><p>$boxDir</p>";
if (!$files) { echo "<p><b>No UHA-*.json files found.</b></p>"; exit; }
echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>File</th><th>Size (bytes)</th></tr>";
foreach ($files as $f) echo "<tr><td>".htmlspecialchars(basename($f))."</td><td>".filesize($f)."</td></tr>";
echo "</table>";
