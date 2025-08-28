<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/user-auth.php';
require_once __DIR__ . '/../../includes/acl.php';
header('Content-Type: application/json');

if (!user_logged_in()) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

$teamId = (int)($_POST['team_id'] ?? $_GET['team_id'] ?? 0);
$ok = $teamId ? set_active_team($teamId) : false;
echo json_encode(['ok' => $ok, 'active_team_id' => user_active_team_id()]);
