<?php
// includes/toast-center.php — centered success/error toast (auto-fades)
// Safe to include multiple times (guarded).
if (!defined('TOAST_CENTER_LOADED')) {
  define('TOAST_CENTER_LOADED', true);
  $msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
  $err = isset($_GET['err']) ? (string)$_GET['err'] : '';
  ?>
  <style>
    .toast {
      position: fixed; left: 50%; top: 12px; transform: translateX(-50%);
      z-index: 9999; padding: .6rem .8rem; border-radius: .6rem; border:1px solid;
      box-shadow: 0 6px 18px rgba(0,0,0,.08);
      display:flex; align-items:center; gap:.5rem;
      transition: opacity .3s ease, transform .3s ease;
    }
    .toast-success { background:#e9f8ef; border-color:#bfe6bf; color:#0a7d2f; }
    .toast-error   { background:#ffecec; border-color:#f3b4b4; color:#7a1212; }
    .toast-hide    { opacity:0; transform: translate(-50%, -6px); }
    .toast .toast-close { background:transparent; border:0; font-size:1rem; line-height:1;
      cursor:pointer; color:inherit; padding:.1rem .25rem; }
    .toast svg { width:16px; height:16px; flex:0 0 auto; }
  </style>
  <svg xmlns="http://www.w3.org/2000/svg" style="display:none;">
    <symbol id="tc-check" viewBox="0 0 24 24"><path fill="currentColor" d="M9 16.2l-3.5-3.5L4 14.2l5 5L20 8.2 18.6 7z"/></symbol>
    <symbol id="tc-x" viewBox="0 0 24 24"><path fill="currentColor" d="M18.3 5.7L12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3l6.3 6.3 6.3-6.3z"/></symbol>
  </svg>
  <?php if ($msg !== ''): ?>
    <div class="toast toast-success" id="tc-toast">
      <svg><use href="#tc-check"/></svg>
      <div><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
      <button class="toast-close" onclick="(function(){var t=document.getElementById('tc-toast'); if(!t) return; t.classList.add('toast-hide'); setTimeout(function(){t.remove();}, 250); })()">×</button>
    </div>
    <script>setTimeout(function(){var t=document.getElementById('tc-toast'); if(!t) return; t.classList.add('toast-hide'); setTimeout(function(){t.remove();}, 250);}, 2000);</script>
  <?php endif; ?>
  <?php if ($err !== ''): ?>
    <div class="toast toast-error" id="tc-toast-err">
      <svg><use href="#tc-x"/></svg>
      <div><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
      <button class="toast-close" onclick="(function(){var t=document.getElementById('tc-toast-err'); if(!t) return; t.classList.add('toast-hide'); setTimeout(function(){t.remove();}, 250); })()">×</button>
    </div>
    <script>setTimeout(function(){var t=document.getElementById('tc-toast-err'); if(!t) return; t.classList.add('toast-hide'); setTimeout(function(){t.remove();}, 250);}, 4000);</script>
  <?php endif; ?>
  <?php
}
