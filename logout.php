<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/user-auth.php';
user_logout();
header('Location: ' . u('login.php'));
exit;