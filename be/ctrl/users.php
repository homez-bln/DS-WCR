<?php
/**
 * ctrl/users.php v8 — Benutzerverwaltung
 * Komplett neu geschrieben: defensiv, jede DB-Query in try/catch
 * v8: + CSRF-Schutz für alle POST-Aktionen
 */

// Fehler in Log schreiben, NICHT anzeigen (verhindert 500 durch fatale Fehler)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('manage_users');

// ── Sicherstellen dass role-Spalte existiert ──────────────────────────────────────────────────────────────────────
// Kein IF NOT EXISTS (MySQL < 8.0 unterstützt das nicht)
try {
    $pdo->exec("ALTER TABLE be_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
} catch (Exception $e) {
    // Spalte existiert bereits – kein Problem
}

$message = '';
$msgType = '';

// ── POST-Handler mit CSRF-Schutz ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF-Schutz: Alle POST-Aktionen validieren ──
    if (!wcr_verify_csrf(false)) {
        $message = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Benutzer anlegen ──────────────────────────────────────────────────────────────────────
        if ($action === 'create') {
            try {
                $newUser = trim((string)($_POST['new_username'] ?? ''));
                $newPass = (string)($_POST['new_password'] ?? '');
                $newRole = (string)($_POST['new_role'] ?? 'user');

                $allowedRoles = wcr_is_cernal() ? ['cernal','admin','user'] : ['admin','user'];
                if (!in_array($newRole, $allowedRoles, true)) $newRole = 'user';

                if (strlen($newUser) < 3) {
                    $message = 'Benutzername muss mind. 3 Zeichen haben.';
                    $msgType = 'error';
                } elseif (strlen($newPass) < 8) {
                    $message = 'Passwort muss mind. 8 Zeichen haben.';
                    $msgType = 'error';
                } else {
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM be_users WHERE username = ?');
                    $chk->execute([$newUser]);
                    if ((int)$chk->fetchColumn() > 0) {
                        $message = "Benutzer \"{$newUser}\" existiert bereits.";
                        $msgType = 'error';
                    } else {
                        $hash = password_hash($newPass, PASSWORD_BCRYPT);
                        $ins  = $pdo->prepare('INSERT INTO be_users (username, password_hash, role, active) VALUES (?, ?, ?, 1)');
                        $ins->execute([$newUser, $hash, $newRole]);
                        $message = "Benutzer \"{$newUser}\" ({$newRole}) wurde angelegt.";
                        $msgType = 'ok';
                    }
                }
            } catch (Exception $e) {
                $message = 'Datenbankfehler: ' . $e->getMessage();
                $msgType = 'error';
            }
        }

        // ── Toggle active ──────────────────────────────────────────────────────────────────────
        if ($action === 'toggle') {
            try {
                $toggleId = (int)($_POST['user_id'] ?? 0);
                if ($toggleId === wcr_user_id()) {
                    $message = 'Du kannst dich nicht selbst deaktivieren.';
                    $msgType = 'error';
                } else {
                    $pdo->prepare('UPDATE be_users SET active = 1 - active WHERE id = ?')->execute([$toggleId]);
                    $message = 'Status geändert.';
                    $msgType = 'ok';
                }
            } catch (Exception $e) {
                $message = 'Datenbankfehler: ' . $e->getMessage();
                $msgType = 'error';
            }
        }

        // ── Passwort zurücksetzen ──────────────────────────────────────────────────────────────────────
        if ($action === 'reset_pw') {
            try {
                $resetId = (int)($_POST['user_id'] ?? 0);
                $newPw   = (string)($_POST['new_pw'] ?? '');
                if (strlen($newPw) < 8) {
                    $message = 'Neues Passwort zu kurz (min. 8 Zeichen).';
                    $msgType = 'error';
                } else {
                    $hash = password_hash($newPw, PASSWORD_BCRYPT);
                    $pdo->prepare('UPDATE be_users SET password_hash = ? WHERE id = ?')->execute([$hash, $resetId]);
                    $message = 'Passwort geändert.';
                    $msgType = 'ok';
                }
            } catch (Exception $e) {
                $message = 'Datenbankfehler: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
}

// ── Alle User laden ──────────────────────────────────────────────────────────────────────
// Zuerst prüfen ob role-Spalte existiert, dann Query anpassen
$users = [];
$roleColumnExists = false;
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM be_users LIKE 'role'");
    $roleColumnExists = ($colCheck->rowCount() > 0);
} catch (Exception $e) {
    // ignorieren
}

try {
    if ($roleColumnExists) {
        $users = $pdo->query("SELECT id, username, role, active FROM be_users ORDER BY id ASC")->fetchAll();
    } else {
        $rows  = $pdo->query("SELECT id, username, active FROM be_users ORDER BY id ASC")->fetchAll();
        // Rolle als 'user' annehmen wenn Spalte fehlt
        foreach ($rows as &$r) $r['role'] = 'user';
        $users = $rows;
    }
} catch (Exception $e) {
    $message = 'Fehler beim Laden der Benutzer: ' . $e->getMessage();
    $msgType = 'error';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Benutzerverwaltung</title>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>👥 Benutzerverwaltung</h1>
</div>

<?php if ($message): ?>
  <div class="status-banner <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="user-mgmt-layout">

  <!-- LINKE SPALTE: Neuen User anlegen -->
  <div class="user-create-panel">
    <h3>➚ Neuen Benutzer anlegen</h3>
    <form method="POST" class="user-form">
      <?= wcr_csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <label>Benutzername</label>
      <input type="text" name="new_username" minlength="3" required placeholder="z.B. jan">

      <label>Passwort</label>
      <input type="password" name="new_password" minlength="8" required placeholder="mind. 8 Zeichen">

      <label>Rolle</label>
      <select name="new_role">
        <?php if (wcr_is_cernal()): ?>
          <option value="cernal">🔧 Cernal (Vollzugriff + Debug)</option>
        <?php endif; ?>
        <option value="admin">👑 Admin (alles außer Debug)</option>
        <option value="user" selected>👤 User (nur Toggle)</option>
      </select>

      <button type="submit" class="btn-upload">Benutzer anlegen</button>
    </form>

    <div class="role-info">
      <div class="ri-title">Rollen-Übersicht</div>
      <div class="ri-row">
        <span class="role-badge" data-role="cernal">🔧 Cernal</span>
        <span>Vollzugriff + Debug-Panel</span>
      </div>
      <div class="ri-row">
        <span class="role-badge" data-role="admin">👑 Admin</span>
        <span>Preise, Zeiten, Media, Users</span>
      </div>
      <div class="ri-row">
        <span class="role-badge" data-role="user">👤 User</span>
        <span>Nur An/Aus-Toggle</span>
      </div>
    </div>
  </div>

  <!-- RECHTE SPALTE: Bestehende User -->
  <div class="user-list-panel">
    <h3>📋 Vorhandene Benutzer (<?= count($users) ?>)</h3>
    <?php if (empty($users)): ?>
      <div class="empty-state">Keine Benutzer vorhanden.</div>
    <?php else: ?>
      <table class="user-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Benutzername</th>
            <th>Rolle</th>
            <th>Status</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u):
            $isMe     = ((int)$u['id'] === wcr_user_id());
            $isActive = ((int)$u['active'] === 1);
            $role     = $u['role'] ?? 'user';
          ?>
          <tr class="<?= $isActive ? '' : 'inactive-row' ?>">
            <td><?= (int)$u['id'] ?></td>
            <td>
              <?= htmlspecialchars($u['username']) ?>
              <?php if ($isMe): ?><span class="badge-me">Du</span><?php endif; ?>
            </td>
            <td>
              <span class="role-badge" data-role="<?= htmlspecialchars($role) ?>">
                <?php
                  $icons = ['cernal'=>'🔧','admin'=>'👑','user'=>'👤'];
                  echo $icons[$role] ?? '👤';
                ?>
                <?= htmlspecialchars(ucfirst($role)) ?>
              </span>
            </td>
            <td>
              <form method="POST" style="display:inline">
                <?= wcr_csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button type="submit"
                        class="status-pill <?= $isActive ? 'active' : 'inactive' ?>"
                        <?= $isMe ? 'disabled title="Du kannst dich nicht selbst deaktivieren"' : '' ?>>
                  <?= $isActive ? '✅ Aktiv' : '❌ Inaktiv' ?>
                </button>
              </form>
            </td>
            <td>
              <button class="btn-icon" onclick="showResetDialog(<?= (int)$u['id'] ?>,'<?= htmlspecialchars($u['username']) ?>')">
                🔑 Passwort
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<!-- Modal: Passwort zurücksetzen -->
<div id="pw-modal" class="modal" style="display:none">
  <div class="modal-overlay" onclick="document.getElementById('pw-modal').style.display='none'"></div>
  <div class="modal-content">
    <h3>🔑 Passwort zurücksetzen</h3>
    <p>Neues Passwort für <strong id="pw-modal-user"></strong>:</p>
    <form method="POST" id="pw-form">
      <?= wcr_csrf_field() ?>
      <input type="hidden" name="action" value="reset_pw">
      <input type="hidden" name="user_id" id="pw-uid">
      <input type="password" name="new_pw" id="pw-input" placeholder="mind. 8 Zeichen" minlength="8" required autofocus>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('pw-modal').style.display='none'">
          Abbrechen
        </button>
        <button type="submit" class="btn-upload">
          Ändern
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function showResetDialog(uid, username) {
  document.getElementById('pw-modal-user').textContent = username;
  document.getElementById('pw-uid').value = uid;
  document.getElementById('pw-input').value = '';
  document.getElementById('pw-modal').style.display = 'flex';
  setTimeout(() => document.getElementById('pw-input').focus(), 100);
}
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
