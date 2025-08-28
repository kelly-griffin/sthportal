<?php
// /sthportal/index.php — splash with rink tiles, sprite buttons, fog/specks, audio, keyboard hint
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (session_status() !== PHP_SESSION_ACTIVE)
  @session_start();

$isAdmin = !empty($_SESSION['is_admin'])
  || (!empty($_SESSION['user']) && !empty($_SESSION['user']['is_admin']))
  || (!empty($_SESSION['user']) && (($_SESSION['user']['role'] ?? '') === 'admin'));

$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

/* Start button goes to Home always */
$enterUrl = ($base === '' ? '' : $base) . '/home.php';

function h($s): string
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$SHOW_PLAYERS = true;
$SITE_NAME = defined('SITE_NAME') ? SITE_NAME : 'STH Portal';
?><!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title><?= h($SITE_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#2f46ff">

  <link rel="preload" as="image" href="assets/splash/tiles/bg-4x3-r2-c2.webp">
  <link rel="preload" as="image" href="assets/splash/buttons/start-sprite.png">
  <link rel="preload" as="image" href="assets/splash/player-right.png">

  <style>
    :root {
      --blue: #2f46ff;
      --red: #ff3a3a;
      --shadow: 0 10px 40px rgba(0, 0, 0, .35);
      --s: 1;

      /* 👇 Player placement knobs (stage is 3840×2160) */
      --player-x: 2720px;
      /* left-right */
      --player-y: 1260px;
      /* up-down */
      --player-w: 1100px;
      /* width relative to stage */

      /* 👇 Goalie placement knobs (stage is 3840×2160) */
      --goalie-x: 1000px;
      /* left-right */
      --goalie-y: 1320px;
      /* up-down */
      --goalie-w: 1060px;
      /* width */
    }

    * {
      box-sizing: border-box
    }

    html,
    body {
      height: 100%
    }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      background: #e9eef7;
      overflow: hidden;
    }

    .bgLow {
      position: fixed;
      inset: 0;
      z-index: 0;
      background:
        radial-gradient(1200px 600px at 50% 80%, rgba(255, 255, 255, .6), rgba(255, 255, 255, 0)),
        url('assets/splash/bg-low.webp') center/cover no-repeat,
        linear-gradient(#ecf2fb, #e7eef9);
      filter: saturate(1.04) contrast(1.02);
    }

    .stageWrap {
      position: fixed;
      inset: 0;
      z-index: 1;
      pointer-events: none;
    }

    .stage {
      position: absolute;
      left: 50%;
      top: 50%;
      width: 3840px;
      height: 2160px;
      transform: translate(-50%, -50%) scale(var(--s));
      transform-origin: center;
    }

    .tiles {
      position: absolute;
      inset: 0;
    }

    .tile {
      position: absolute;
      width: 25%;
      height: 33.333%;
      opacity: 0;
      transition: opacity .3s ease;
    }

    .tile.ready {
      opacity: 1;
    }

    .tile.r1 {
      top: 0
    }

    .tile.r2 {
      top: 33.333%
    }

    .tile.r3 {
      top: 66.666%
    }

    .tile.c1 {
      left: 0
    }

    .tile.c2 {
      left: 25%
    }

    .tile.c3 {
      left: 50%
    }

    .tile.c4 {
      left: 75%
    }

    .tile img {
      width: 100%;
      height: 100%;
      display: block;
    }

    /* Frost veil */
    .frost {
      position: absolute;
      inset: -8% -2% -2% -2%;
      z-index: 2;
      background:
        repeating-linear-gradient(145deg, rgba(255, 255, 255, .08) 0 1px, rgba(255, 255, 255, 0) 1px 3px),
        radial-gradient(1200px 600px at 50% 110%, rgba(255, 255, 255, .35), rgba(255, 255, 255, 0));
      mix-blend-mode: screen;
      pointer-events: none;
    }

    /* Hotspots sit above player */
    .hotspots {
      position: absolute;
      inset: 0;
      z-index: 4;
      pointer-events: auto;
    }

    .btnSprite {
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      width: var(--w);
      height: var(--h);
      background-repeat: no-repeat;
      background-size: 100% 100%;
      filter: drop-shadow(0 6px 16px rgba(0, 0, 0, .35));
      outline: none;
      border-radius: 16px;
      transition: transform .08s ease, filter .08s ease;
    }

    .btnSprite.frames1 {
      background-size: 100% 100%;
    }

    .btnSprite.frames1:hover,
    .btnSprite.frames1:focus {
      filter: drop-shadow(0 10px 28px rgba(0, 0, 0, .45)) brightness(1.02);
      transform: translateX(-50%) translateY(-1px) scale(1.02);
    }

    .btnSprite.frames1:active {
      filter: drop-shadow(0 4px 10px rgba(0, 0, 0, .3)) brightness(0.92);
      transform: translateX(-50%) translateY(1px) scale(0.98);
    }

    .btnStart {
      top: 44%;
      animation: breathe 4.5s ease-in-out infinite;
    }

    .btnOpt {
      top: 60%;
    }

    .btnExit {
      top: 76%;
    }

    @keyframes breathe {

      0%,
      100% {
        transform: translateX(-50%) scale(1)
      }

      50% {
        transform: translateX(-50%) scale(1.03)
      }
    }

    .btnSprite:hover,
    .btnSprite:focus,
    .btnSprite:active {
      animation: none !important;
    }

    /* BIG skater on stage (scales with stage) */
    .playerOnStage {
      position: absolute;
      z-index: 3;
      pointer-events: none;
      left: var(--player-x);
      top: var(--player-y);
      transform: translate(-50%, -50%);
      width: var(--player-w);
      filter: drop-shadow(0 10px 18px rgba(0, 0, 0, .28));
      animation: floaty 9s ease-in-out infinite;
      image-rendering: auto;
    }

    @keyframes floaty {

      0%,
      100% {
        transform: translate(-50%, -50%)
      }

      50% {
        transform: translate(-50%, -52%)
      }
    }

    @keyframes floatyX {

      0%,
      100% {
        transform: translate(-50%, -50%) translateX(18px);
      }

      50% {
        transform: translate(-50%, -50%) translateX(-18px);
      }
    }

    /* Left-side goalie (scales with stage) */
    .playerLeft {
      position: absolute;
      z-index: 3;
      pointer-events: none;
      left: var(--goalie-x);
      top: var(--goalie-y);
      transform: translate(-50%, -50%);
      width: var(--goalie-w);
      filter: drop-shadow(0 10px 18px rgba(0, 0, 0, .28));
      animation: floatyX 8s ease-in-out infinite;
      animation-delay: -1.6s;
      /* de-sync from skater */
      image-rendering: auto;
    }

    @media (max-width: 900px) {
      .playerLeft {
        display: none;
      }
    }

    /* Fallback text menu (hidden once sprites are ready) */
    .menu {
      position: relative;
      z-index: 3;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      margin-top: clamp(6px, 3vh, 36px);
    }

    body.hasSprites .menu {
      display: none;
    }

    .btn {
      --bg: #fff8cf;
      --bd: #e1c46a;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 220px;
      height: 56px;
      padding: 0 1.25rem;
      border: 2px solid var(--bd);
      border-radius: 12px;
      background: var(--bg);
      color: #151515;
      font-weight: 800;
      letter-spacing: .04em;
      text-decoration: none;
      text-transform: uppercase;
      box-shadow: var(--shadow);
      transition: transform .08s ease, filter .08s ease, background .2s ease;
    }

    .btn.secondary {
      --bg: #fff;
      --bd: #ddd;
    }

    .btn:hover {
      filter: brightness(1.05);
      transform: translateY(-1px);
    }

    .btn:active {
      transform: translateY(1px) scale(.98);
    }

    .uc {
      position: fixed;
      right: 14px;
      top: 14px;
      z-index: 5;
      background: #ffe588;
      color: #151515;
      border: 1px solid #d7c06e;
      padding: .35rem .55rem;
      border-radius: .5rem;
      font-size: .9rem;
      font-weight: 600;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .15);
    }

    /* Fog corners + specks */
    .fogCorners {
      position: fixed;
      inset: 0;
      z-index: 2;
      pointer-events: none;
    }

    .fogCorners::before,
    .fogCorners::after {
      content: "";
      position: absolute;
      top: -2vh;
      width: 44vw;
      height: 28vh;
      background: radial-gradient(60% 80% at 0% 0%, rgba(255, 255, 255, .45), rgba(255, 255, 255, 0) 70%);
      filter: blur(2px) saturate(1.1);
      mix-blend-mode: screen;
      opacity: .75;
    }

    .fogCorners::after {
      right: 0;
      left: auto;
      background: radial-gradient(60% 80% at 100% 0%, rgba(255, 255, 255, .45), rgba(255, 255, 255, 0) 70%);
    }

    .specks {
      position: fixed;
      inset: -10% 0 0 0;
      z-index: 2;
      pointer-events: none;
      opacity: .09;
    }

    .specks,
    .specks::before,
    .specks::after {
      background-image:
        radial-gradient(2px 2px at 10% 20%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%),
        radial-gradient(1.5px 1.5px at 30% 10%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%),
        radial-gradient(1.2px 1.2px at 55% 30%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%),
        radial-gradient(1px 1px at 75% 15%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%),
        radial-gradient(1.4px 1.4px at 85% 40%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%),
        radial-gradient(1px 1px at 20% 60%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%),
        radial-gradient(1.2px 1.2px at 40% 75%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%),
        radial-gradient(1px 1px at 65% 70%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%),
        radial-gradient(1.2px 1.2px at 90% 80%, rgba(255, 255, 255, .95) 50%, rgba(255, 255, 255, 0) 51%);
      background-repeat: repeat;
      background-size: 240px 240px;
      animation: speckFall 18s linear infinite;
    }

    .specks::before,
    .specks::after {
      content: "";
      position: absolute;
      inset: 0;
    }

    .specks::before {
      transform: translateY(-25%);
      animation-duration: 22s;
      opacity: .9;
    }

    .specks::after {
      transform: translateY(-50%);
      animation-duration: 28s;
      opacity: .8;
    }

    @keyframes speckFall {
      to {
        transform: translateY(35%);
      }
    }

    .audioToggle {
      position: fixed;
      right: 14px;
      bottom: 14px;
      z-index: 10;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, .6);
      background: rgba(20, 24, 32, .35);
      box-shadow: 0 6px 18px rgba(0, 0, 0, .3);
      backdrop-filter: blur(6px) saturate(120%);
      color: #fff;
      cursor: pointer;
      transition: transform .12s ease, background-color .2s ease, border-color .2s ease, opacity .2s ease;
    }

    .audioToggle[aria-pressed="true"] {
      background: rgba(47, 70, 255, .45);
      border-color: rgba(255, 255, 255, .85);
    }

    .audioToggle:hover {
      transform: translateY(-1px);
    }

    .audioToggle:active {
      transform: translateY(1px) scale(.98);
    }

    .audioToggle svg {
      width: 22px;
      height: 22px;
      display: block;
    }

    .kbdHint {
      position: absolute;
      left: 50%;
      top: 52%;
      transform: translateX(-50%);
      z-index: 4;
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
      font-size: clamp(11px, 1.1vw, 14px);
      color: #fff;
      text-shadow: 0 2px 10px rgba(0, 0, 0, .55);
      opacity: 0;
      pointer-events: none;
      animation: kbdShow 4s ease forwards .4s;
      white-space: nowrap;
      user-select: none;
    }

    @keyframes kbdShow {
      0% {
        opacity: 0;
        transform: translateX(-50%) translateY(2px);
      }

      10%,
      70% {
        opacity: .95;
        transform: translateX(-50%) translateY(0);
      }

      100% {
        opacity: 0;
        transform: translateX(-50%) translateY(-2px);
      }
    }

    .brand {
      display: none !important;
    }

    /* Top-left Admin Home pill */
    .adminHomeLink {
      position: fixed;
      left: 14px;
      top: 14px;
      z-index: 10;
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      height: 36px;
      padding: 0 .6rem;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, .6);
      background: rgba(20, 24, 32, .35);
      color: #fff;
      text-decoration: none;
      font-weight: 600;
      font-size: .9rem;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .3);
      backdrop-filter: blur(6px) saturate(120%);
      transition: transform .12s ease, background-color .2s ease, border-color .2s ease, opacity .2s ease;
    }

    .adminHomeLink:hover {
      transform: translateY(-1px);
    }

    .adminHomeLink:active {
      transform: translateY(1px) scale(.98);
    }

    .adminHomeLink svg {
      width: 18px;
      height: 18px;
      display: block;
    }
  </style>
</head>

<body>

  <div class="bgLow" aria-hidden="true"></div>

  <!-- Top-left Admin Home link (always visible; admin guards will handle auth) -->
  <a class="adminHomeLink" href="<?= h(($base === '' ? '' : $base) . '/admin/index.php') ?>" title="Admin Home"
    aria-label="Admin Home">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
      stroke-linejoin="round" aria-hidden="true">
      <path d="M3 11l9-8 9 8" />
      <path d="M5 10v10h14V10" />
    </svg>
    Admin Home
  </a>

  <div class="stageWrap" aria-hidden="true">
    <div id="stage" class="stage">
      <div class="tiles" id="tiles">
        <?php for ($r = 1; $r <= 3; $r++):
          for ($c = 1; $c <= 4; $c++):
            $cls = "tile r{$r} c{$c}";
            $src = "assets/splash/tiles/bg-4x3-r{$r}-c{$c}.webp";
            $loading = ($r === 2 && $c === 2) ? 'eager' : 'lazy';
            ?>
            <div class="<?= $cls ?>"><img loading="<?= $loading ?>" src="<?= $src ?>" alt=""></div>
          <?php endfor; endfor; ?>
      </div>

      <!-- Sprite button hotspots -->
      <div class="hotspots">
        <a class="btnSprite btnStart frames1" href="<?= h($enterUrl) ?>"
          style="--w:790px; --h:260px; background-image:url('assets/splash/buttons/start-sprite.png');"
          aria-label="Start"></a>

<!-- Options sprite -->
<a class="btnSprite btnOpt frames1" href="options-hub.php"
   style="--w:790px; --h:260px; background-image:url('assets/splash/buttons/options-sprite.png');"
   aria-label="Options"></a>

<!-- Exit sprite -->
<a class="btnSprite btnExit frames1" href="https://www.google.ca" target="_blank" rel="noopener"
   style="--w:790px; --h:260px; background-image:url('assets/splash/buttons/exit-sprite.png');"
   aria-label="Exit"></a>


        <div class="kbdHint">↑ ↓ to select • Enter to start</div>
      </div>

      <!-- Goalie on left side -->
      <img class="playerLeft" src="assets/splash/player-left.png" alt="Goalie">

      <?php if ($SHOW_PLAYERS): ?>
        <!-- BIG skater art placed on stage coordinates -->
        <img class="playerOnStage" src="assets/splash/player-right.png" alt="Skater">
      <?php endif; ?>

      <div class="frost"></div>
    </div>
  </div>

  <div class="specks" aria-hidden="true"></div>
  <div class="fogCorners" aria-hidden="true"></div>

  <div class="uc">🚧 Under construction</div>
<div class="menu">
  <a class="btn" href="home.php">Start</a>
  <a class="btn secondary" href="options.php">Options</a>
  <a class="btn secondary" href="https://www.google.ca" target="_blank">Exit</a>
</div>

  <button class="audioToggle" type="button" aria-pressed="false" title="Toggle UI sounds" aria-label="Toggle UI sounds">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
      stroke-linejoin="round" aria-hidden="true">
      <path d="M11 5 L6 9 H3 v6 h3 l5 4 z" />
      <path class="wave1" d="M15 9 q3 3 0 6" />
      <path class="wave2" d="M17.5 6.5 q6 5.5 0 11" />
    </svg>
  </button>

  <script>
    function fitStage() { const s = Math.max(innerWidth / 3840, innerHeight / 2160); document.documentElement.style.setProperty('--s', String(s)); }
    addEventListener('resize', fitStage, { passive: true }); fitStage();

    document.querySelectorAll('.tile img').forEach(img => {
      if (img.complete) img.parentElement.classList.add('ready');
      else img.addEventListener('load', () => img.parentElement.classList.add('ready'), { once: true });
    });

    const spriteImgs = [
      'assets/splash/buttons/start-sprite.png',
      'assets/splash/buttons/options-sprite.png',
      'assets/splash/buttons/exit-sprite.png'
    ];
    let loaded = 0;
    spriteImgs.forEach(src => { const i = new Image(); i.onload = () => { if (++loaded === spriteImgs.length) document.body.classList.add('hasSprites'); }; i.src = src; });

    const order = Array.from(document.querySelectorAll('.btnSprite'));
    let idx = 0;
    function focusBtn(i) { if (!order.length) return; idx = (i + order.length) % order.length; order[idx].focus(); }
    document.addEventListener('keydown', (e) => {
      const tag = (e.target && e.target.tagName || '').toLowerCase();
      if (['input', 'textarea', 'select'].includes(tag)) return;
      if (e.key === 'ArrowDown') { focusBtn(idx + 1); e.preventDefault(); }
      if (e.key === 'ArrowUp') { focusBtn(idx - 1); e.preventDefault(); }
      if (e.key === 'Enter') { order[idx]?.click(); e.preventDefault(); }
      if (e.key === 'Escape') { window.close?.(); }
    });
    document.addEventListener('DOMContentLoaded', () => { if (order[0]) order[0].tabIndex = 0; });

    ; (() => {
      let ac = null, soundOn = false;
      const btn = document.querySelector('.audioToggle');
      const ensureAC = () => { if (!ac) { try { const Ctx = window.AudioContext || window.webkitAudioContext; ac = new Ctx(); } catch (e) { } } return ac; };
      const play = (f = 440, d = 0.06, t = 'sine', g = 0.15) => { const ctx = ensureAC(); if (!ctx || !soundOn) return; const o = ctx.createOscillator(), G = ctx.createGain(); o.type = t; o.frequency.value = f; G.gain.value = g; o.connect(G).connect(ctx.destination); const n = ctx.currentTime; o.start(n); o.stop(n + d); G.gain.setValueAtTime(0.0001, n); G.gain.exponentialRampToValueAtTime(g, n + 0.008); G.gain.exponentialRampToValueAtTime(0.0001, n + d); };
      const tick = () => play(2200, 0.035, 'triangle', 0.10);
      const thump = () => play(120, 0.08, 'sine', 0.18);

      if (btn) {
        btn.addEventListener('click', () => { soundOn = !soundOn; if (soundOn) ensureAC()?.resume?.(); if (soundOn) tick(); btn.setAttribute('aria-pressed', String(soundOn)); });
        btn.addEventListener('keydown', e => { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); btn.click(); } });
      }
      [...document.querySelectorAll('.btnSprite'), ...document.querySelectorAll('.menu .btn')].forEach(el => {
        el.addEventListener('mouseenter', tick, { passive: true });
        el.addEventListener('focus', tick, { passive: true });
        el.addEventListener('mousedown', thump, { passive: true });
        el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') thump(); }, { passive: true });
      });

      const hint = document.querySelector('.kbdHint');
      hint?.addEventListener('animationend', () => hint.remove(), { once: true });
    })();
  </script>
</body>

</html>