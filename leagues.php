<?php
require_once __DIR__ . '/includes/bootstrap.php';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Leagues in the UHA</title>
  <link rel="stylesheet" href="assets/css/nav.css">
  <link rel="stylesheet" href="assets/css/leagues.css">
  <style>
    .badge-warn{
      display:inline-block; padding:2px 6px; font-size:11px; line-height:1;
      border-radius:4px; margin-left:6px;
      background:#5e2d2d; color:#ffd6d6; border:1px solid #7a4040;
      vertical-align:middle;
    }
  </style>
  <script src="assets/js/leagues.js" defer></script>
</head>
<body>
  <div class="site">

    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <?php include __DIR__ . '/includes/leaguebar.php'; ?>

    <div class="canvas">
      <div class="leagues-container">
        <div class="leagues-card">
          <h1>Leagues in the UHA</h1>
        </div>
       </div>
    </div>    