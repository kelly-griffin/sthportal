<?php
// admin/license-edit.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';

/**
 * DB handle normalization (matches licenses.php)
 */
if (!isset($db) || !($db instanceof mysqli)) {
    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
        $db = $GLOBALS['db'];
    } elseif (isset($mysqli) && $mysqli instanceof mysqli) {
        $db = $mysqli;
    } elseif (function_exists('db')) {
        $db = db();
    } elseif (function_exists('getDb')) {
        $db = getDb();
    }
}
if (!isset($db) || !($db instanceof mysqli)) {
    die('Database connection handle $db is not set. Check includes/db.php');
}

// --- Admin guard ---
if (empty($_SESSION['is_admin']) || empty($_SESSION['perms']['manage_licenses'])) {
    header('Location: admin-login.php');
    exit;
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function valid_status(string $s): bool { return in_array($s, ['active','demo','blocked'], true); }

function parse_dtlocal(?string $raw): ?string {
    // Accept HTML datetime-local (Y-m-d\TH:i) -> "Y-m-d H:i:s" (Toronto)
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    $tz = new DateTimeZone('America/Toronto');
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw, $tz);
    if (!$dt) return null;
    return $dt->format('Y-m-d H:i:s');
}

/**
 * Convert DB timestamp "Y-m-d H:i:s" to datetime-local value "Y-m-d\TH:i".
 * Static analyzers complained about possible null -> new DateTimeImmutable(...).
 * So we explicitly guard + cast.
 */
function to_dtlocal(?string $dbTs): string {
    if ($dbTs === null || $dbTs === '') return '';
    try {
        $tz = new DateTimeZone('America/Toronto');
        $dt = new DateTimeImmutable((string)$dbTs, $tz);
        return $dt->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

$id = (int)($_GET['id'] ?? 0);
$errors = [];
$saved  = isset($_GET['saved']) && $_GET['saved'] === '1';

// ---- Load existing record (if editing) ----
$license = [
    'id' => 0,
    'portal_id' => '',
    'license_key' => '',
    'licensed_to' => '',
    'email' => '',
    'status' => 'demo',
    'registered_domain' => '',
    'expires_at' => null,
    'last_check' => null,
    'created_at' => null,
    'updated_at' => null,
    'notes' => '',
];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM licenses WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $license = $row;
    } else {
        http_response_code(404);
        exit('License not found');
    }
    $stmt->close();
}

// ---- POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete
    if (($_POST['__action'] ?? '') === 'delete' && $id > 0) {
        $del = $db->prepare("DELETE FROM licenses WHERE id=?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        header('Location: licenses.php?deleted=1');
        exit;
    }

    // Quick extend (adds months to current or now)
    if (($_POST['__action'] ?? '') === 'extend' && isset($_POST['months']) && $id > 0) {
        $months = max(1, (int)$_POST['months']);
        $tz = new DateTimeZone('America/Toronto');
        $base = $license['expires_at']
            ? new DateTimeImmutable((string)$license['expires_at'], $tz)
            : new DateTimeImmutable('now', $tz);
        $newExp = $base->modify("+{$months} months")->format('Y-m-d H:i:s');
        $u = $db->prepare("UPDATE licenses SET expires_at=?, updated_at=NOW() WHERE id=?");
        $u->bind_param('si', $newExp, $id);
        $u->execute();
        $u->close();
        header('Location: license-edit.php?id='.(int)$id.'&saved=1');
        exit;
    }

    // Save/Update
    $data = [
        'portal_id'         => trim((string)($_POST['portal_id'] ?? '')),
        'license_key'       => trim((string)($_POST['license_key'] ?? '')),
        'licensed_to'       => trim((string)($_POST['licensed_to'] ?? '')),
        'email'             => trim((string)($_POST['email'] ?? '')),
        'status'            => (string)($_POST['status'] ?? 'demo'),
        'registered_domain' => trim((string)($_POST['registered_domain'] ?? '')),
        'expires_at'        => parse_dtlocal($_POST['expires_at'] ?? ''),
        'notes'             => trim((string)($_POST['notes'] ?? '')),
    ];

    // Basic validation
    if ($data['license_key'] === '') $errors[] = 'License key is required.';
    if (!valid_status($data['status'])) $errors[] = 'Invalid status.';
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    if (!$errors) {
        if ($id > 0) {
            $sql = "UPDATE licenses
                       SET portal_id=?,
                           license_key=?,
                           licensed_to=?,
                           email=?,
                           status=?,
                           registered_domain=?,
                           expires_at=?,
                           notes=?,
                           updated_at=NOW()
                     WHERE id=?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                'ssssssssi',
                $data['portal_id'],
                $data['license_key'],
                $data['licensed_to'],
                $data['email'],
                $data['status'],
                $data['registered_domain'],
                $data['expires_at'],
                $data['notes'],
                $id
            );
            $stmt->execute();
            $stmt->close();
            header('Location: license-edit.php?id='.(int)$id.'&saved=1');
            exit;
        } else {
            $sql = "INSERT INTO licenses
                        (portal_id, license_key, licensed_to, email, status, registered_domain, expires_at, notes, created_at)
                    VALUES (?,?,?,?,?,?,?,?,NOW())";
            $stmt = $db->prepare($sql);
            $stmt->bind_param(
                'ssssssss',
                $data['portal_id'],
                $data['license_key'],
                $data['licensed_to'],
                $data['email'],
                $data['status'],
                $data['registered_domain'],
                $data['expires_at'],
                $data['notes']
            );
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            header('Location: license-edit.php?id='.(int)$newId.'&saved=1');
            exit;
        }
    } else {
        // repopulate form with submitted values on error
        $license = array_merge($license, $data);
    }
}

// ---- View ----
$isEdit = $id > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $isEdit ? 'Edit License' : 'New License' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --card:#fff; --ink:#222; --muted:#667; --line:#ddd; --bg:#f6f7fb; }
  body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu; background:var(--bg); color:var(--ink); }
  header { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem; }
  .h1 { font-size:1.25rem; font-weight:700; }
  .toolbar { display:flex; gap:.5rem; flex-wrap:wrap; }
  .btn { display:inline-flex; align-items:center; gap:.35rem; padding:.5rem .7rem; border:1px solid var(--line); border-radius:.5rem; background:#fff; text-decoration:none; color:#222; font-size:.95rem; }
  .btn.primary { background:#0b5; border-color:#0b5; color:#fff; }
  .btn.danger { background:#b00; border-color:#b00; color:#fff; }
  .btn.ghost { background:transparent; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:.75rem; margin:0 1.25rem 1.25rem; }
  .content { padding:1rem; }
  form .grid { display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
  label { display:flex; flex-direction:column; gap:.4rem; font-size:.9rem; }
  input[type="text"], input[type="email"], input[type="datetime-local"], select, textarea {
    padding:.55rem .6rem; border:1px solid var(--line); border-radius:.5rem; font-size:.95rem; width:100%;
  }
  textarea { min-height:100px; }
  .row { display:flex; gap:.5rem; flex-wrap:wrap; }
  .muted { color:var(--muted); }
  .notice { background:#eaffea; border:1px solid #bfe6bf; color:#155b18; padding:.6rem .8rem; border-radius:.5rem; }
  .errors { background:#ffecec; border:1px solid #f3b4b4; color:#7a1212; padding:.6rem .8rem; border-radius:.5rem; }
  .small { font-size:.85rem; }
</style>
<script>
function genKey() {
  const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  function block(len){ let s=""; for(let i=0;i<len;i++){ s+=chars[Math.floor(Math.random()*chars.length)]; } return s; }
  const k = [block(5),block(5),block(5),block(5),block(5)].join("-");
  document.getElementById('license_key').value = k;
}
function fillMonths(m) {
  document.getElementById('extend_months').value = m;
  document.getElementById('extend_form').submit();
}
</script>
</head>
<body>

<header>
  <div class="h1"><?= $isEdit ? 'Edit License #'.(int)$license['id'] : 'New License' ?></div>
  <div class="toolbar">
    <a class="btn" href="licenses.php">Back to list</a>
    <?php if ($isEdit): ?>
      <form id="extend_form" method="post" style="display:inline;">
        <input type="hidden" name="__action" value="extend">
        <input type="hidden" id="extend_months" name="months" value="">
        <span class="btn ghost small">Quick extend:</span>
        <a class="btn small" href="#" onclick="fillMonths(6);return false;">+6m</a>
        <a class="btn small" href="#" onclick="fillMonths(12);return false;">+12m</a>
        <a class="btn small" href="#" onclick="fillMonths(24);return false;">+24m</a>
      </form>
    <?php endif; ?>
  </div>
</header>

<div class="card"><div class="content">
  <?php if ($saved): ?>
    <div class="notice">Saved.</div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="errors">
      <strong>Fix the following:</strong>
      <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" action="">
    <div class="grid">
      <label>
        Portal ID (optional)
        <input type="text" name="portal_id" value="<?= h($license['portal_id']) ?>" placeholder="e.g., ABCD-123">
      </label>

      <label>
        License Key
        <div class="row">
          <input id="license_key" type="text" name="license_key" value="<?= h($license['license_key']) ?>" placeholder="Click Generate or paste your own">
          <a class="btn" href="#" onclick="genKey();return false;">Generate</a>
        </div>
        <span class="muted small">Format is flexible; generator uses 5×5 blocks (A–Z, 2–9).</span>
      </label>

      <label>
        Licensed To
        <input type="text" name="licensed_to" value="<?= h($license['licensed_to']) ?>" placeholder="League / Org / Contact">
      </label>

      <label>
        Email
        <input type="email" name="email" value="<?= h($license['email']) ?>" placeholder="user@site.tld">
      </label>

      <label>
        Status
        <select name="status">
          <?php foreach (['active','demo','blocked'] as $s): ?>
            <option value="<?= $s ?>" <?= $license['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Registered Domain
        <input type="text" name="registered_domain" value="<?= h($license['registered_domain']) ?>" placeholder="example.com">
      </label>

      <label>
        Expires (local)
        <input type="datetime-local" name="expires_at" value="<?= h(to_dtlocal($license['expires_at'])) ?>">
        <span class="muted small">Leave blank for no expiry.</span>
      </label>

      <label style="grid-column:1/-1">
        Notes
        <textarea name="notes" placeholder="Internal notes (not shown to users)"><?= h($license['notes']) ?></textarea>
      </label>
    </div>

    <div class="row" style="margin-top:1rem;">
      <button class="btn primary" type="submit">Save</button>
      <a class="btn" href="licenses.php">Cancel</a>
      <?php if ($isEdit): ?>
        <button class="btn danger" type="submit" name="__action" value="delete" onclick="return confirm('Delete this license? This cannot be undone.');">Delete</button>
      <?php endif; ?>
    </div>

    <?php if ($isEdit): ?>
      <p class="muted small" style="margin-top:1rem;">
        Created: <?= h($license['created_at'] ?? '') ?> |
        Updated: <?= h($license['updated_at'] ?? '') ?> |
        Last Check: <?= h($license['last_check'] ?? '') ?>
      </p>
    <?php endif; ?>
  </form>
</div></div>

</body>
</html>
