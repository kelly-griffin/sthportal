<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/user-auth.php';
header('Content-Type: application/json');

if (user_logged_in()) {
  $u = $_SESSION['user'] ?? ['id'=>(int)($_SESSION['user_id'] ?? 0), 'display_name'=>(string)($_SESSION['user_name'] ?? 'User')];
  echo json_encode(['ok'=>true,'user'=>$u], JSON_UNESCAPED_SLASHES); exit;
}

http_response_code(401);
echo json_encode(['ok'=>false,'error'=>'auth'], JSON_UNESCAPED_SLASHES);
