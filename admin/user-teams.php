<?php
// admin/user-teams.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/../includes/acl.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
@include_once __DIR__ . '/../includes/admin-helpers.php';

// --- Teams JSON path (change if your file lives elsewhere) ---
if (!defined('TEAMS_JSON_PATH')) {
    define('TEAMS_JSON_PATH', __DIR__ . '/../assets/json/teams.json');
}

/**
 * Load team map as [id => name], from JSON first; optional DB fallback if provided.
 * Supports shapes:
 *  A) { "teams": [ { "id": 28, "name": "..." }, ... ] }
 *  B) [ { "id": 28, "name": "..." }, ... ]
 *  C) { "28": "Team Name", ... }
 */
function teams_map(?PDO $pdo = null): array
{
    static $cache = null;
    if ($cache !== null)
        return $cache;

    $cache = [];

    // JSON first
    $path = defined('TEAMS_JSON_PATH') ? TEAMS_JSON_PATH : '';
    if ($path && is_file($path)) {
        $raw = @file_get_contents($path);
        $data = $raw ? json_decode($raw, true) : null;

        if (is_array($data)) {
            if (isset($data['teams']) && is_array($data['teams'])) {
                foreach ($data['teams'] as $t) {
                    if (is_array($t) && isset($t['id'], $t['name'])) {
                        $cache[(int) $t['id']] = (string) $t['name'];
                    }
                }
            } elseif (isset($data[0]) && is_array($data[0]) && isset($data[0]['id'], $data[0]['name'])) {
                foreach ($data as $t) {
                    $cache[(int) $t['id']] = (string) $t['name'];
                }
            } else {
                foreach ($data as $k => $v) {
                    if (is_numeric($k) && is_string($v)) {
                        $cache[(int) $k] = $v;
                    }
                }
            }
        }
    }

    // Optional DB fallback (only if table exists and not already cached)
    if ($pdo) {
        try {
            $ok = $pdo->query("SHOW TABLES LIKE 'teams'");
            if ($ok && $ok->rowCount()) {
                $rs = $pdo->query("SELECT id, name FROM teams");
                foreach ($rs as $row) {
                    $tid = (int) $row['id'];
                    $nm = (string) $row['name'];
                    if ($tid && $nm && !isset($cache[$tid]))
                        $cache[$tid] = $nm;
                }
            }
        } catch (Throwable $e) { /* ignore */
        }
    }

    // Nice alphabetical order for UI lists
    if ($cache) {
        asort($cache, SORT_NATURAL | SORT_FLAG_CASE);
    }

    return $cache;
}


// Safe fallbacks (copied from users.php)
if (!function_exists('h')) {
    function h($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('admin_db')) {
    function admin_db(): mysqli
    {
        return $GLOBALS['db'] ?? $GLOBALS['dbc'] ?? new mysqli();
    }
}
if (!function_exists('require_admin')) {
    function require_admin(): void
    {
    }
}
if (!function_exists('require_perm')) {
    function require_perm(string $p): void
    {
    }
}
if (!function_exists('admin_csrf')) {
    function admin_csrf(): string
    {
        return $_SESSION['csrf'] ?? '';
    }
}

require_admin();
require_perm('manage_users');

$pdo = acl_db();

$userId = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
if ($userId <= 0) {
    echo 'Missing user_id.';
    exit;
}

// Basic user load (adjust table/columns if yours differ)
$user = null;
try {
    $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (Throwable $e) {
    // If your user table is named differently, tweak the query above.
}
if (!$user) {
    echo 'User not found.';
    exit;
}
$userId = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$backUrl = (string) ($_GET['back'] ?? 'users.php'); // default back to the Users list

// Handle actions
$action = $_POST['action'] ?? null;

if ($action === 'add') {
    $teamId = (int) ($_POST['team_id'] ?? 0);
    $role = $_POST['role'] ?? 'gm';
    if ($teamId > 0) {
        $stmt = $pdo->prepare('
      INSERT INTO user_teams (user_id, team_id, role, can_trade, can_sign, can_callups, can_lines)
      VALUES (?, ?, ?, 1, 1, 1, 1)
      ON DUPLICATE KEY UPDATE role = VALUES(role)
    ');
        $stmt->execute([$userId, $teamId, $role]);
    }
}

if ($action === 'remove') {
    $teamId = (int) ($_POST['team_id'] ?? 0);
    if ($teamId > 0) {
        $stmt = $pdo->prepare('DELETE FROM user_teams WHERE user_id = ? AND team_id = ?');
        $stmt->execute([$userId, $teamId]);
    }
}

if ($action === 'update_caps') {
    $teamId = (int) ($_POST['team_id'] ?? 0);
    if ($teamId > 0) {
        $caps = [
            'can_trade' => isset($_POST['can_trade']) ? 1 : 0,
            'can_sign' => isset($_POST['can_sign']) ? 1 : 0,
            'can_callups' => isset($_POST['can_callups']) ? 1 : 0,
            'can_lines' => isset($_POST['can_lines']) ? 1 : 0,
        ];
        $stmt = $pdo->prepare('
      UPDATE user_teams
      SET can_trade = :can_trade, can_sign = :can_sign, can_callups = :can_callups, can_lines = :can_lines
      WHERE user_id = :uid AND team_id = :tid
    ');
        $stmt->execute([
            ':can_trade' => $caps['can_trade'],
            ':can_sign' => $caps['can_sign'],
            ':can_callups' => $caps['can_callups'],
            ':can_lines' => $caps['can_lines'],
            ':uid' => $userId,
            ':tid' => $teamId
        ]);
    }
}

// If you're editing your own teams, refresh your session ACL immediately
if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId) {
    load_user_acl($userId);
}

// Fetch current mappings
$stmt = $pdo->prepare('SELECT team_id, role, can_trade, can_sign, can_callups, can_lines
                       FROM user_teams WHERE user_id = ? ORDER BY team_id');
$stmt->execute([$userId]);
$mappings = $stmt->fetchAll();

// Optional: try to resolve team names. If unknown, we’ll show "Team #ID".
function team_label(PDO $pdo, int $teamId): string
{
    $map = teams_map($pdo);
    return $map[$teamId] ?? ('Team #' . (int) $teamId);
}
// header include (tolerant, copied from users.php)
$loadedHeader = false;
foreach ([
    __DIR__ . '/admin-header.php',
    __DIR__ . '/header-admin.php',
    __DIR__ . '/partials/admin-header.php',
    __DIR__ . '/../includes/admin-header.php',
    __DIR__ . '/../includes/header.php',
] as $p) {
    if (is_file($p)) {
        include $p;
        $loadedHeader = true;
        break;
    }
}
if (!$loadedHeader) {
    echo "<!doctype html><meta charset='utf-8'><title>Manage Teams</title>" .
        "<style>body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;margin:16px}</style>";
}
?>
<h1>Manage Teams</h1>
<p class="small">User: <strong><?= htmlspecialchars($user['name'] ?? ('#' . $userId)) ?></strong> (ID <?= $userId ?>)</p>

<h2>Current Assignments</h2>
<table>
    <thead>
        <tr>
            <th style="width:80px;">Team ID</th>
            <th>Team</th>
            <th style="width:90px;">Role</th>
            <th colspan="4">Capabilities</th>
            <th style="width:220px;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$mappings): ?>
            <tr>
                <td colspan="8"><em>No teams assigned.</em></td>
            </tr>
        <?php else:
            foreach ($mappings as $m):
                $tid = (int) $m['team_id']; ?>
                <tr>
                    <td><?= $tid ?></td>
                    <td><?= htmlspecialchars(team_label($pdo, $tid)) ?></td>
                    <td><?= htmlspecialchars($m['role']) ?></td>
                    <td>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="update_caps">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                            <input type="hidden" name="team_id" value="<?= $tid ?>">

                            <label class="chk"><input type="checkbox" name="can_trade" <?= $m['can_trade'] ? 'checked' : '' ?>>
                                Trade</label>
                            <label class="chk"><input type="checkbox" name="can_sign" <?= $m['can_sign'] ? 'checked' : '' ?>>
                                Sign</label>
                            <label class="chk"><input type="checkbox" name="can_callups" <?= $m['can_callups'] ? 'checked' : '' ?>>
                                Callups</label>
                            <label class="chk"><input type="checkbox" name="can_lines" <?= $m['can_lines'] ? 'checked' : '' ?>>
                                Lines</label>

                            <button class="btn" type="submit">Save</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" class="inline" onsubmit="return confirm('Remove this team from the user?')">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                            <input type="hidden" name="team_id" value="<?= $tid ?>">
                            <button class="btn danger" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
    </tbody>
</table>

<h2>Add Assignment</h2>
<form method="post">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="user_id" value="<?= $userId ?>">
    <label>Team
        <input name="team_id" list="teamlist" inputmode="numeric" pattern="\d+"
            placeholder="Start typing a team… (select fills ID)" required>
        <datalist id="teamlist">
            <?php foreach (teams_map() as $tid => $tname): ?>
                <option value="<?= (int) $tid ?>"><?= h($tname) ?></option>
            <?php endforeach; ?>
        </datalist>
    </label>
    <label>Role
        <select name="role">
            <option value="gm">gm</option>
            <option value="agm">agm</option>
            <option value="coach">coach</option>
            <option value="owner">owner</option>
        </select>
    </label>
    <button class="btn" type="submit">Add</button>
    <span class="small">Tip: IDs come from your teams table; names show if we can resolve them.</span>
</form>

<p style="margin-top:16px;">
    <a class="btn" href="<?= h($backUrl) ?>">← Back to Users</a>
</p>
<?php
if ($loadedHeader === true) {
    foreach ([
        __DIR__ . '/admin-footer.php',
        __DIR__ . '/footer-admin.php',
        __DIR__ . '/partials/admin-footer.php',
        __DIR__ . '/../includes/admin-footer.php',
        __DIR__ . '/../includes/footer.php',
    ] as $p) {
        if (is_file($p)) {
            include $p;
            $loadedHeader = false;
            break;
        }
    }
}
if ($loadedHeader)
    echo "</body></html>";
?>