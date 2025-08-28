<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../includes/log.php';

// Never let logging kill logout
try { log_audit('admin_logout', 'Admin logged out', !empty($_SESSION['is_admin']) ? 'admin' : 'guest'); } catch (Throwable $e) {}

session_destroy();
header('Location: login.php');
exit;
