<?php
// Legacy route → permanent redirect to the new page
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
http_response_code(301);
header('Location: forgot-password.php' . $qs);
exit;
