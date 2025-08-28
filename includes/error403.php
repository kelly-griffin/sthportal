<?php
http_response_code(403);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Access Denied</title>
<style>
body { font-family:sans-serif; background:#f9f9f9; padding:2rem; color:#333; }
.card { background:white; padding:2rem; border-radius:8px; box-shadow:0 0 8px rgba(0,0,0,.1); max-width:500px; margin:auto; }
h1 { color:#c00; }
a { color:#06c; text-decoration:none; }
a:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="card">
    <h1>🚫 Access Denied</h1>
    <p>You do not have permission to view this page.</p>
    <p><a href="/sthportal/admin/">← Back to Admin Home</a></p>
</div>
</body>
</html>
