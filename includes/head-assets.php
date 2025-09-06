<?php
declare(strict_types=1);

// Prevent double emission
if (defined('UHA_HEAD_ASSETS_EMITTED')) return;
define('UHA_HEAD_ASSETS_EMITTED', true);

// Pull helpers (asset(), h(), etc.)
require_once __DIR__ . '/functions.php';
?>
<link rel="stylesheet" href="<?= h(asset('assets/css/global.css')) ?>" data-uha="global">
<link rel="stylesheet" href="<?= h(asset('assets/css/nav.css')) ?>" data-uha="nav">
<script>
  // give auto-logos.js a reliable base for images
  window.UHA = window.UHA || {};
  window.UHA.LOGO_BASE = "<?= h(asset('assets/img/logos/')) ?>";
</script>
<script defer src="<?= h(asset('assets/js/auto-logos.js')) ?>" data-uha="logos"></script>
