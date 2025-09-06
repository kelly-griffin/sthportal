<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$db = get_db();
echo "OK mysqli connected\n";
