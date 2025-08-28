<?php
// Legacy route → permanent redirect to the new page (preserves ?token=…)
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
http_response_code(301);
header('Location: reset-password.php' . $qs);
exit;
