<?php
/**
 * login.php v8 — Lädt auch die Rolle aus be_users.role + CSRF Protection
 */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';

if (is_logged_in()) { header('Location: ./index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF-Schutz ──
    if (!wcr_verify_csrf(false)) {
        $error = 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password']      ?? '');

        if ($username !== '' && $password !== '') {
            // v7: Lädt auch die Rolle
            $stmt = $pdo->prepare('SELECT id, password_hash, COALESCE(role, "user") as role FROM be_users WHERE username = :u AND active = 1 LIMIT 1');
            $stmt->execute(['u' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, (string)$user['password_hash'])) {
                login_user((int)$user['id'], (string)$user['role']);
                header('Location: ./index.php');
                exit;
            }
            $error = 'Benutzername oder Passwort falsch.';
        } else {
            $error = 'Bitte beide Felder ausfüllen.';
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>WCR Backoffice – Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f5f5f7; min-height: 100vh;
      display: flex; align-items: center; justify-content: center; padding: 20px;
      -webkit-font-smoothing: antialiased;
    }
    .wrap  { width: 100%; max-width: 400px; }
    .logo  { text-align: center; font-size: 22px; font-weight: 700; color: #1d1d1f; margin-bottom: 24px; }
    .box   { background: #fff; border: 1px solid #d2d2d7; border-radius: 14px; padding: 30px 28px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
    label  { display: block; font-size: 12px; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; margin-top: 18px; }
    label:first-of-type { margin-top: 0; }
    input  { width: 100%; padding: 11px 14px; font-size: 15px; border: 1px solid #d2d2d7; border-radius: 8px; outline: none; transition: border-color .2s, box-shadow .2s; }
    input:focus { border-color: #0071e3; box-shadow: 0 0 0 3px rgba(0,113,227,.12); }
    button { margin-top: 22px; padding: 13px; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; background: #0071e3; color: #fff; border: none; border-radius: 8px; transition: opacity .2s; }
    button:hover { opacity: .88; }
    .err   { color: #c0392b; background: #fff0f0; border: 1px solid #ffd0cc; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 18px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="logo">🏄 WCR Backoffice</div>
    <div class="box">
      <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <?= wcr_csrf_field() ?>
        <label for="u">Benutzername</label>
        <input id="u" name="username" required autofocus autocomplete="username">
        <label for="p">Passwort</label>
        <input id="p" name="password" type="password" required autocomplete="current-password">
        <button type="submit">Einloggen</button>
      </form>
    </div>
  </div>
</body>
</html>
